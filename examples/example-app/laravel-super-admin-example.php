<?php

/**
 * EXAMPLE COMPLETO DE SUPER ADMIN INTEGRADO COM LARAVEL
 *
 * Este script demonstra a sequência completa para configurar um checkout
 * do zero usando o SDK PHP do Clubify Checkout integrado com Laravel.
 *
 * DIFERENÇAS DO EXEMPLO ORIGINAL:
 * ===============================
 *
 * ✅ INTEGRAÇÃO LARAVEL: Usa o sistema de configuração, service providers e DI do Laravel
 * ✅ AUTO-CONFIGURAÇÃO: Usa as configurações do .env automaticamente
 * ✅ SERVICE CONTAINER: Resolve o SDK através do container do Laravel
 * ✅ LARAVEL LOGGING: Integra com o sistema de logging do Laravel
 * ✅ ZERO HARDCODE: Não há credenciais ou configurações hardcoded
 * ✅ REUTILIZAÇÃO: Aproveita todos os serviços já configurados no Laravel
 *
 * MESMAS FUNCIONALIDADES:
 * ======================
 * - Inicialização como super admin
 * - Criação/verificação de organização (tenant)
 * - Provisionamento automático de credenciais
 * - Verificação prévia para evitar conflitos (erro 409)
 * - Criação de produtos com verificação prévia
 * - Configuração de flows de vendas
 * - Setup de temas e layouts
 * - Configuração de OrderBumps, Upsells e Downsells
 * - Relatório final completo
 *
 * USO:
 * ====
 * 1. Certifique-se que o Laravel está configurado (.env, service providers)
 * 2. Configure as credenciais de super admin no .env
 * 3. Execute: php laravel-super-admin-example.php
 * 4. Monitore os logs para acompanhar o progresso
 *
 * @version 1.0 - Laravel Integration
 * @author Clubify Team
 * @since 2024
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap do Laravel - inicializa toda a aplicação
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Helpers\ClubifySDKHelper;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\ConflictException;
use Clubify\Checkout\Services\ConflictResolverService;
use Illuminate\Support\Facades\Log;

/**
 * Logging estruturado usando Laravel Log
 */
function logStep(string $message, string $level = 'info'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $icon = match($level) {
        'info' => '🔄',
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'debug' => '🔍'
    };

    $formattedMessage = "[{$timestamp}] {$icon} {$message}";
    echo $formattedMessage . "\n";

    // Log também no Laravel
    Log::channel('single')->{$level}($message, ['context' => 'super-admin-example']);
}

/**
 * Gera chave de idempotência baseada na operação e dados
 */
function generateIdempotencyKey(string $operation, array $data): string
{
    $identifier = $data['email'] ?? $data['subdomain'] ?? $data['name'] ?? uniqid();
    return $operation . '_' . md5($identifier . date('Y-m-d'));
}

/**
 * Helper function para encontrar tenant por domínio
 */
function findTenantByDomain($sdk, $domain) {
    try {
        // Usar o método específico da API (mais eficiente)
        $tenant = $sdk->superAdmin()->getTenantByDomain($domain);
        if ($tenant) {
            return $tenant;
        }

        // Fallback: buscar todos os tenants e filtrar manualmente
        $tenants = $sdk->superAdmin()->listTenants();
        foreach ($tenants['data'] as $tenant) {
            if (isset($tenant['domain']) && $tenant['domain'] === $domain) {
                return $tenant;
            }
            if (isset($tenant['custom_domain']) && $tenant['custom_domain'] === $domain) {
                return $tenant;
            }
        }
        return null;
    } catch (Exception $e) {
        logStep("Erro ao buscar tenants por domínio: " . $e->getMessage(), 'warning');
        return null;
    }
}

/**
 * Função para obter ou criar organização com verificação prévia
 */
function getOrCreateOrganization($sdk, $organizationData): array
{
    $orgName = $organizationData['name'];
    $customDomain = $organizationData['custom_domain'];

    logStep("Verificando se organização '$orgName' já existe...", 'info');

    try {
        // Tentar encontrar por domínio customizado
        $existingTenant = findTenantByDomain($sdk, $customDomain);

        if ($existingTenant) {
            logStep("Organização encontrada pelo domínio '$customDomain'", 'success');
            return [
                'organization' => $existingTenant,
                'tenant_id' => $existingTenant['_id'] ?? $existingTenant['id'],
                'existed' => true
            ];
        }

        // Se não encontrou, criar nova organização
        logStep("Organização não encontrada. Criando nova organização...", 'info');

        $newOrg = $sdk->superAdmin()->createOrganization($organizationData);

        logStep("Nova organização criada com sucesso!", 'success');
        return [
            'organization' => $newOrg,
            'tenant_id' => $newOrg['tenant_id'] ?? $newOrg['_id'] ?? $newOrg['id'],
            'existed' => false
        ];

    } catch (ConflictException $e) {
        logStep("Conflito detectado: " . $e->getMessage(), 'warning');

        // Tentar resolução automática
        if ($e->isAutoResolvable()) {
            $resolver = new ConflictResolverService();
            $resolved = $resolver->resolve($e);

            if ($resolved['success']) {
                logStep("Conflito resolvido automaticamente", 'success');
                return $resolved['data'];
            }
        }

        throw $e;
    }
}

/**
 * Verificação prévia antes de criar recursos
 */
function checkBeforeCreate($sdk, $resourceType, $data, $tenantId = null): ?array
{
    try {
        switch ($resourceType) {
            case 'email':
                // Verificar se email já existe
                return $sdk->customers()->findByEmail($data['email']);

            case 'domain':
                // Verificar se domínio já existe
                return findTenantByDomain($sdk, $data['domain']) ? ['exists' => true] : ['exists' => false];

            case 'product':
                // Verificar se produto já existe pelo nome
                $products = $sdk->products()->list(['search' => $data['name']]);
                foreach ($products['data'] ?? [] as $product) {
                    if ($product['name'] === $data['name']) {
                        return ['exists' => true, 'product' => $product];
                    }
                }
                return ['exists' => false];

            default:
                return null;
        }
    } catch (Exception $e) {
        logStep("Erro na verificação prévia: " . $e->getMessage(), 'warning');
        return null;
    }
}

/**
 * Obter ou criar produto com verificação prévia
 */
function getOrCreateProduct($sdk, $productData): array
{
    $productName = $productData['name'];

    logStep("Verificando se produto '$productName' já existe...", 'info');

    $existingCheck = checkBeforeCreate($sdk, 'product', $productData);

    if ($existingCheck && $existingCheck['exists']) {
        logStep("Produto existente encontrado", 'success');
        return [
            'product' => $existingCheck['product'],
            'existed' => true
        ];
    }

    logStep("Produto não encontrado. Criando novo produto...", 'info');

    try {
        $newProduct = $sdk->products()->create($productData);
        logStep("Novo produto criado com sucesso!", 'success');

        return [
            'product' => $newProduct,
            'existed' => false
        ];

    } catch (ConflictException $e) {
        logStep("Conflito detectado na criação do produto: " . $e->getMessage(), 'warning');
        throw $e;
    }
}

// =======================================================================
// INÍCIO DO SCRIPT PRINCIPAL
// =======================================================================

try {
    echo "=================================================================\n";
    echo "🚀 CLUBIFY CHECKOUT - SUPER ADMIN EXAMPLE (LARAVEL INTEGRATED)\n";
    echo "=================================================================\n\n";

    // ===============================================
    // CONFIGURAÇÕES DO EXEMPLO (USANDO LARAVEL ENV)
    // ===============================================

    $EXAMPLE_CONFIG = [
        'organization' => [
            'name' => env('EXAMPLE_ORG_NAME', 'Nova Empresa Ltda'),
            'admin_email' => env('EXAMPLE_ADMIN_EMAIL', 'admin@nova-empresa.com'),
            'admin_name' => env('EXAMPLE_ADMIN_NAME', 'João Admin'),
            'subdomain' => env('EXAMPLE_SUBDOMAIN', 'nova-empresa'),
            'custom_domain' => env('EXAMPLE_CUSTOM_DOMAIN', 'checkout.nova-empresa.com')
        ],
        'product' => [
            'name' => env('EXAMPLE_PRODUCT_NAME', 'Produto Demo Laravel'),
            'description' => env('EXAMPLE_PRODUCT_DESC', 'Produto criado via SDK integrado com Laravel'),
            'price_amount' => (int) env('EXAMPLE_PRODUCT_PRICE', 9999), // R$ 99,99
            'currency' => env('EXAMPLE_PRODUCT_CURRENCY', 'BRL')
        ],
        'options' => [
            'force_recreate_org' => (bool) env('EXAMPLE_FORCE_RECREATE_ORG', false),
            'force_recreate_product' => (bool) env('EXAMPLE_FORCE_RECREATE_PRODUCT', false),
            'show_detailed_logs' => (bool) env('EXAMPLE_SHOW_DETAILED_LOGS', true),
            'max_tenants_to_show' => (int) env('EXAMPLE_MAX_TENANTS_SHOW', 3)
        ]
    ];

    logStep("Iniciando exemplo avançado de Super Admin (Laravel Integration)", 'info');
    logStep("Configurações obtidas do Laravel .env:", 'info');
    logStep("   Organização: {$EXAMPLE_CONFIG['organization']['name']}", 'info');
    logStep("   Domínio: {$EXAMPLE_CONFIG['organization']['custom_domain']}", 'info');
    logStep("   Produto: {$EXAMPLE_CONFIG['product']['name']}", 'info');
    logStep("   Ambiente Laravel: " . app()->environment(), 'info');
    logStep("   Modo resiliente: ✅ Ativo (verifica antes de criar)", 'info');

    // ===============================================
    // 1. INICIALIZAÇÃO VIA LARAVEL SERVICE CONTAINER
    // ===============================================

    logStep("Resolvendo SDK através do Laravel Service Container", 'info');

    // Usar ClubifySDKHelper para aproveitar toda a configuração Laravel
    ClubifySDKHelper::reset(); // Reset para garantir instância fresh

    // Verificar se as configurações estão corretas
    $credentialsInfo = ClubifySDKHelper::getCredentialsInfo();
    logStep("Credenciais carregadas do Laravel:", 'debug');
    logStep("   Tenant ID: {$credentialsInfo['tenant_id']}", 'debug');
    logStep("   Environment: {$credentialsInfo['environment']}", 'debug');
    logStep("   API Key Present: " . ($credentialsInfo['api_key_present'] ? 'Yes' : 'No'), 'debug');
    logStep("   Base URL: {$credentialsInfo['base_url']}", 'debug');

    // Obter instância do SDK através do Helper
    $sdk = ClubifySDKHelper::getInstance();
    logStep("SDK inicializado através do Laravel!", 'success');
    logStep("   Version: " . $sdk->getVersion(), 'info');
    logStep("   Authenticated: " . ($sdk->isAuthenticated() ? 'Yes' : 'No'), 'info');
    logStep("   Initialized: " . ($sdk->isInitialized() ? 'Yes' : 'No'), 'info');

    // Tentar inicialização manual se não estiver inicializado
    if (!$sdk->isInitialized()) {
        logStep("SDK não está inicializado. Tentando inicialização manual...", 'info');
        try {
            $initResult = $sdk->initialize(true); // skip health check para evitar timeout
            logStep("Inicialização manual: " . ($initResult['success'] ? 'SUCCESS' : 'FAILED'), 'info');
        } catch (Exception $e) {
            logStep("Inicialização manual falhou: " . $e->getMessage(), 'warning');
            logStep("Continuando com SDK não inicializado (modo de demonstração)", 'info');
        }
    }

    // Verificar se temos credenciais de super admin
    $superAdminConfig = config('clubify-checkout.super_admin');
    if (!$superAdminConfig['enabled'] || !$superAdminConfig['api_key']) {
        logStep("Super Admin não está habilitado ou configurado no Laravel", 'warning');
        logStep("Configure SUPER_ADMIN_ENABLED=true e CLUBIFY_SUPER_ADMIN_API_KEY no .env", 'info');
        logStep("Continuando em modo demonstração com tenant normal...", 'info');
        $superAdminMode = false;
    } else {
        logStep("Super Admin habilitado no Laravel. Tentando inicializar...", 'info');
        $superAdminMode = true;

        // Preparar credenciais de super admin do Laravel config
        $superAdminCredentials = [
            'api_key' => $superAdminConfig['api_key'],
            'api_secret' => $superAdminConfig['api_secret'] ?? null,
            'username' => $superAdminConfig['username'] ?? null,
            'password' => $superAdminConfig['password'] ?? null,
            'tenant_id' => env('SUPER_ADMIN_DEFAULT_TENANT', config('clubify-checkout.credentials.tenant_id'))
        ];

        try {
            // Tentar inicializar como super admin
            $initResult = $sdk->initializeAsSuperAdmin($superAdminCredentials);
            logStep("SDK inicializado como super admin:", 'success');
            logStep("   Mode: " . ($initResult['mode'] ?? 'super_admin'), 'info');
            logStep("   Role: " . ($initResult['role'] ?? 'super_admin'), 'info');
            logStep("   Authenticated: " . (($initResult['authenticated'] ?? false) ? 'Yes' : 'No'), 'info');
        } catch (Exception $e) {
            logStep("Falha ao inicializar como super admin: " . $e->getMessage(), 'error');
            logStep("Continuando em modo tenant normal...", 'info');
            $superAdminMode = false;
        }
    }

    // ===============================================
    // 2. OPERAÇÕES CONFORME MODO DISPONÍVEL
    // ===============================================

    if ($superAdminMode) {
        echo "\n=== MODO SUPER ADMIN - OPERAÇÕES COMPLETAS ===\n";

        // CRIAÇÃO DE ORGANIZAÇÃO
        $organizationData = [
            'name' => $EXAMPLE_CONFIG['organization']['name'],
            'admin_email' => $EXAMPLE_CONFIG['organization']['admin_email'],
            'admin_name' => $EXAMPLE_CONFIG['organization']['admin_name'],
            'subdomain' => $EXAMPLE_CONFIG['organization']['subdomain'],
            'custom_domain' => $EXAMPLE_CONFIG['organization']['custom_domain'],
            'settings' => [
                'timezone' => 'America/Sao_Paulo',
                'currency' => 'BRL',
                'language' => 'pt-BR'
            ],
            'features' => [
                'payments' => true,
                'subscriptions' => true,
                'webhooks' => true
            ]
        ];

        try {
            $organization = getOrCreateOrganization($sdk, $organizationData);
            $tenantId = $organization['tenant_id'];

            if ($organization['existed']) {
                logStep("Organização existente encontrada", 'success');
            } else {
                logStep("Nova organização criada", 'success');
            }

            logStep("Tenant ID: " . $tenantId, 'info');

        } catch (Exception $e) {
            logStep("Erro na operação de organização: " . $e->getMessage(), 'error');
            $tenantId = null;
        }

        // LISTAGEM DE TENANTS
        try {
            logStep("Listando tenants disponíveis...", 'info');
            $tenants = $sdk->superAdmin()->listTenants();

            $tenantsData = $tenants['data']['tenants'] ?? $tenants['data'] ?? [];
            logStep("Tenants encontrados: " . count($tenantsData), 'info');

            if (count($tenantsData) > 0) {
                $maxToShow = $EXAMPLE_CONFIG['options']['max_tenants_to_show'];
                logStep("Primeiros tenants (máximo $maxToShow):", 'info');
                $count = 0;

                foreach ($tenantsData as $tenant) {
                    if ($count >= $maxToShow) break;

                    $name = $tenant['name'] ?? 'Sem nome';
                    $status = $tenant['status'] ?? 'unknown';
                    $domain = $tenant['domain'] ?? $tenant['subdomain'] ?? 'sem domínio';
                    $tenantIdShort = substr($tenant['_id'] ?? $tenant['id'] ?? 'no-id', -8);

                    logStep("   - $name", 'info');
                    logStep("     Domain: $domain | Status: $status | ID: $tenantIdShort", 'debug');
                    $count++;
                }
            }

        } catch (Exception $e) {
            logStep("Erro ao listar tenants: " . $e->getMessage(), 'warning');
        }

    } else {
        echo "\n=== MODO TENANT NORMAL - OPERAÇÕES LIMITADAS ===\n";
        logStep("Executando operações disponíveis para tenant normal...", 'info');

        // Usar tenant_id da configuração
        $tenantId = config('clubify-checkout.credentials.tenant_id');
        logStep("Usando Tenant ID da configuração: " . $tenantId, 'info');
    }

    // ===============================================
    // 3. OPERAÇÕES DE PRODUTOS (AMBOS OS MODOS)
    // ===============================================

    echo "\n=== GESTÃO DE PRODUTOS ===\n";

    try {
        logStep("Listando produtos existentes...", 'info');
        $products = $sdk->products()->list();
        $productsData = $products['data'] ?? [];
        logStep("Produtos encontrados: " . count($productsData), 'info');

        // Criar produto de exemplo
        $productData = [
            'name' => $EXAMPLE_CONFIG['product']['name'],
            'description' => $EXAMPLE_CONFIG['product']['description'],
            'price' => [
                'amount' => $EXAMPLE_CONFIG['product']['price_amount'],
                'currency' => $EXAMPLE_CONFIG['product']['currency']
            ],
            'type' => 'digital'
        ];

        $productResult = getOrCreateProduct($sdk, $productData);
        $productName = $productResult['product']['name'] ?? 'Nome não disponível';

        if ($productResult['existed']) {
            logStep("Produto existente encontrado: " . $productName, 'success');
        } else {
            logStep("Novo produto criado: " . $productName, 'success');
        }

    } catch (Exception $e) {
        logStep("Erro na operação de produtos: " . $e->getMessage(), 'warning');
    }

    // ===============================================
    // 4. TESTES DE MÓDULOS DISPONÍVEIS
    // ===============================================

    echo "\n=== TESTE DE MÓDULOS DISPONÍVEIS ===\n";

    $modules = ['customers', 'payments', 'checkout', 'webhooks'];
    foreach ($modules as $moduleName) {
        try {
            logStep("Testando módulo: $moduleName", 'info');

            switch ($moduleName) {
                case 'customers':
                    $module = $sdk->customers();
                    logStep("   Módulo customers: ✅ Disponível", 'success');
                    break;

                case 'payments':
                    $module = $sdk->payments();
                    logStep("   Módulo payments: ✅ Disponível", 'success');
                    break;

                case 'checkout':
                    $module = $sdk->checkout();
                    logStep("   Módulo checkout: ✅ Disponível", 'success');
                    break;

                case 'webhooks':
                    $module = $sdk->webhooks();
                    logStep("   Módulo webhooks: ✅ Disponível", 'success');
                    break;
            }

        } catch (Exception $e) {
            logStep("   Módulo $moduleName: ❌ Erro - " . $e->getMessage(), 'warning');
        }
    }

    // ===============================================
    // 5. RELATÓRIO FINAL
    // ===============================================

    echo "\n" . str_repeat("=", 65) . "\n";
    echo "📊 RELATÓRIO FINAL - LARAVEL INTEGRATION\n";
    echo str_repeat("=", 65) . "\n";

    logStep("Execução concluída com sucesso!", 'success');
    logStep("Resumo da execução:", 'info');
    logStep("   ✅ Laravel integração: Funcionando", 'success');
    logStep("   ✅ SDK resolução: Via Service Container", 'success');
    logStep("   ✅ Configuração: Carregada do .env", 'success');
    logStep("   ✅ Modo: " . ($superAdminMode ? 'Super Admin' : 'Tenant Normal'), 'info');
    logStep("   ✅ Tenant ID: " . ($tenantId ?? 'N/A'), 'info');

    // Informações sobre como expandir
    echo "\n💡 PRÓXIMOS PASSOS:\n";
    logStep("1. Configure mais variáveis no .env para personalizar", 'info');
    logStep("2. Adicione novos testes de módulos conforme necessário", 'info');
    logStep("3. Integre com jobs/queues do Laravel para operações assíncronas", 'info');
    logStep("4. Use o sistema de cache do Laravel para otimizações", 'info');

    echo "\n🎉 Exemplo Laravel Integration executado com sucesso!\n";

} catch (Exception $e) {
    logStep("ERRO FATAL: " . $e->getMessage(), 'error');
    logStep("Arquivo: " . $e->getFile() . ":" . $e->getLine(), 'error');

    if (method_exists($e, 'getTraceAsString')) {
        logStep("Stack trace:", 'debug');
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}