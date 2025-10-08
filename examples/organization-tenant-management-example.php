<?php

/**
 * ORGANIZATION TENANT MANAGEMENT - EXEMPLO COMPLETO
 *
 * Demonstra como usar o SDK para:
 * - Autenticar como Organization usando Organization API Key
 * - Criar e gerenciar tenants dentro da organização
 * - Provisionar recursos para os tenants criados
 *
 * DIFERENÇA DO SUPER ADMIN:
 * ========================
 * - Super Admin: Acesso total ao sistema, pode criar organizations
 * - Organization Admin: Acesso limitado à sua organization, pode criar tenants
 *
 * AUTENTICAÇÃO:
 * =============
 * Configure no .env ou config do Laravel:
 * - CLUBIFY_CHECKOUT_ORGANIZATION_ID=507f1f77bcf86cd799439011
 * - CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY=clb_org_live_xxxxx
 *
 * USO:
 * ====
 * 1. Configure as variáveis de ambiente
 * 2. Execute: php organization-tenant-management-example.php
 * 3. Monitore os logs para acompanhar o progresso
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\ConflictException;

/**
 * Helper para obter variável de ambiente de múltiplas fontes
 */
function getEnvironmentVariable(string $key, $default = null)
{
    // Tentar $_ENV primeiro (mais confiável)
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    // Tentar $_SERVER
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    // Tentar getenv() como fallback
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    // Retornar valor padrão
    return $default;
}

/**
 * Carregar variáveis de ambiente de arquivo .env se existir
 */
function loadEnvFile(): void
{
    $envPath = __DIR__ . '/../.env';

    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentários
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse da linha KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remover aspas se existirem
            $value = trim($value, '"\'');

            // Definir em $_ENV e $_SERVER
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }

            // Também usar putenv para compatibilidade
            putenv("$key=$value");
        }
    }
}

/**
 * Logging estruturado
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
}

/**
 * Extrai tenant_id de um array (compatível com MongoDB _id e id padrão)
 */
function extractTenantId(array $tenant): ?string
{
    if (empty($tenant)) {
        return null;
    }

    // Tentar _id (MongoDB)
    if (isset($tenant['_id'])) {
        return is_object($tenant['_id']) ? (string)$tenant['_id'] : $tenant['_id'];
    }

    // Tentar id padrão
    if (isset($tenant['id'])) {
        return $tenant['id'];
    }

    // Tentar tenant_id
    if (isset($tenant['tenant_id'])) {
        return $tenant['tenant_id'];
    }

    return null;
}

/**
 * Helper para criar ou encontrar tenant por domínio
 */
function getOrCreateTenant($sdk, $tenantData): array
{
    $tenantName = $tenantData['name'];
    $customDomain = $tenantData['custom_domain'] ?? null;

    logStep("Verificando se tenant '$tenantName' já existe...", 'info');

    try {
        // Tentar encontrar por domínio customizado
        if ($customDomain) {
            try {
                $response = $sdk->organization()->tenant()->findByDomain($customDomain);

                // Verificar se realmente encontrou (success === true)
                if ($response && isset($response['success']) && $response['success'] === true) {
                    logStep("Tenant encontrado pelo domínio '$customDomain'", 'success');

                    // Extrair dados do tenant da resposta
                    $tenantData = $response;
                    if (isset($response['data']) && is_array($response['data'])) {
                        $tenantData = $response['data'];
                    } elseif (isset($response['tenant']) && is_array($response['tenant'])) {
                        $tenantData = $response['tenant'];
                    }

                    return [
                        'tenant' => $tenantData,
                        'tenant_id' => extractTenantId($tenantData),
                        'existed' => true
                    ];
                } elseif ($response && isset($response['success']) && $response['success'] === false) {
                    // Tenant não encontrado (404) - continuar para criação
                    // Silenciosamente continuar
                }
            } catch (Exception $e) {
                // Erro na busca - continuar para criação
                // Silenciosamente continuar
            }
        }

        // Se não encontrou, criar novo tenant
        logStep("Criando novo tenant...", 'info');
        $newTenant = $sdk->organization()->tenant()->create($tenantData);

        logStep("Novo tenant criado com sucesso!", 'success');

        // Ativar o tenant após criação
        $tenantId = extractTenantId($newTenant);
        if ($tenantId) {
            try {
                logStep("Ativando tenant (ID: {$tenantId})...", 'info');
                $activated = $sdk->organization()->tenant()->activateTenant($tenantId);
                if ($activated) {
                    logStep("Tenant ativado com sucesso!", 'success');
                } else {
                    logStep("Aviso: activateTenant retornou false. Endpoint PUT /tenants/{id}/status pode não estar implementado no backend.", 'warning');
                }
            } catch (Exception $e) {
                logStep("Aviso: Erro ao ativar o tenant: " . $e->getMessage(), 'warning');
                logStep("Detalhes: " . get_class($e), 'info');
            }
        }

        return [
            'tenant' => $newTenant,
            'tenant_id' => $tenantId,
            'existed' => false
        ];

    } catch (ConflictException $e) {
        logStep("Conflito detectado: " . $e->getMessage(), 'warning');

        // Tentar buscar o tenant existente
        if ($customDomain) {
            try {
                $response = $sdk->organization()->tenant()->findByDomain($customDomain);

                // Verificar se realmente encontrou
                if ($response && isset($response['success']) && $response['success'] === true) {
                    // Extrair dados do tenant
                    $tenantData = $response;
                    if (isset($response['data']) && is_array($response['data'])) {
                        $tenantData = $response['data'];
                    } elseif (isset($response['tenant']) && is_array($response['tenant'])) {
                        $tenantData = $response['tenant'];
                    }

                    return [
                        'tenant' => $tenantData,
                        'tenant_id' => extractTenantId($tenantData),
                        'existed' => true
                    ];
                }
            } catch (Exception $findError) {
                logStep("Erro ao buscar tenant após conflito: " . $findError->getMessage(), 'warning');
            }
        }

        throw $e;
    }
}

// =======================================================================
// INÍCIO DO SCRIPT PRINCIPAL
// =======================================================================

try {
    echo "=================================================================\n";
    echo "🏢 CLUBIFY CHECKOUT - ORGANIZATION TENANT MANAGEMENT\n";
    echo "=================================================================\n\n";

    // Carregar variáveis de ambiente do arquivo .env (se existir)
    loadEnvFile();

    // ===============================================
    // CONFIGURAÇÕES DO EXEMPLO
    // ===============================================

    $EXAMPLE_CONFIG = [
        'organization' => [
            'id' => getEnvironmentVariable('CLUBIFY_CHECKOUT_ORGANIZATION_ID'),
            'api_key' => getEnvironmentVariable('CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY'),
        ],
        'tenant' => [
            'name' => 'Loja Demo Laravel',
            'subdomain' => 'loja-demo-' . date('YmdHis'),
            'custom_domain' => 'loja-demo-' . date('YmdHis') . '.clubify.com.br',
            'description' => 'Tenant criado via SDK PHP para demonstração',
            'plan' => 'business',
            'settings' => [
                'timezone' => 'America/Sao_Paulo',
                'currency' => 'BRL',
                'language' => 'pt-BR'
            ]
        ],
        'admin_user' => [
            'email' => 'admin@loja-demo.com',
            'firstName' => 'Admin',
            'lastName' => 'Loja Demo',
            'password' => 'P@ssw0rd!',
            'roles' => ['tenant_admin']
        ],
        'product' => [
            'name' => 'Produto Demo Organization',
            'description' => 'Produto criado via SDK como Organization Admin',
            'price' => 9999, // R$ 99,99 em centavos
            'currency' => 'BRL',
            'type' => 'digital'
        ],
        'sdk' => [
            'environment' => getEnvironmentVariable('CLUBIFY_CHECKOUT_ENVIRONMENT', 'staging')
        ]
    ];

    // Validar configurações obrigatórias
    if (empty($EXAMPLE_CONFIG['organization']['id']) || empty($EXAMPLE_CONFIG['organization']['api_key'])) {
        logStep("❌ ERRO: Variáveis de ambiente obrigatórias não configuradas!", 'error');
        echo "\nConfigure as seguintes variáveis:\n";
        echo "  - CLUBIFY_CHECKOUT_ORGANIZATION_ID\n";
        echo "  - CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY\n\n";
        echo "Você pode:\n";
        echo "  1. Exportar no terminal: export CLUBIFY_CHECKOUT_ORGANIZATION_ID='seu-id'\n";
        echo "  2. Criar arquivo .env na pasta sdk/checkout/php/\n";
        echo "  3. Definir diretamente no código (linha 193)\n\n";
        exit(1);
    }

    logStep("Iniciando exemplo de Organization Tenant Management", 'info');
    logStep("Organization ID: {$EXAMPLE_CONFIG['organization']['id']}", 'info');

    // ===============================================
    // 1. AUTENTICAÇÃO COMO ORGANIZATION
    // ===============================================

    echo "\n=== Autenticação como Organization ===\n";

    $sdk = new ClubifyCheckoutSDK([
        'environment' => $EXAMPLE_CONFIG['sdk']['environment']
    ]);

    logStep("SDK inicializado v" . $sdk->getVersion(), 'success');

    // Autenticar usando Organization API Key
    logStep("Autenticando como organization...", 'info');

    $authResult = $sdk->authenticateAsOrganization(
        $EXAMPLE_CONFIG['organization']['id'],
        $EXAMPLE_CONFIG['organization']['api_key']
    );

    if (!$authResult['success']) {
        throw new Exception('Falha na autenticação como organization: ' . ($authResult['error'] ?? 'Unknown error'));
    }

    logStep("✅ Autenticação bem-sucedida!", 'success');
    logStep("   Organization ID: " . $authResult['organization_id'], 'info');
    logStep("   Scope: " . $authResult['scope'], 'info');
    logStep("   Permissions: " . implode(', ', $authResult['permissions']), 'info');

    // Verificar contexto organizacional
    $context = $sdk->getOrganizationContext();
    logStep("   Contexto: Org={$context['organization_id']}, Scope={$context['scope']}", 'info');

    // ===============================================
    // 2. LISTAR TENANTS EXISTENTES
    // ===============================================

    echo "\n=== Tenants Existentes na Organization ===\n";

    try {
        $existingTenants = $sdk->organization()->tenant()->listTenantsByOrganization($EXAMPLE_CONFIG['organization']['id']);
        $tenantsData = $existingTenants['data'] ?? [];

        logStep("Tenants encontrados: " . count($tenantsData), 'info');

        if (count($tenantsData) > 0) {
            logStep("Listando primeiros 5 tenants:", 'info');
            foreach (array_slice($tenantsData, 0, 5) as $tenant) {
                $name = $tenant['name'] ?? 'Sem nome';
                $domain = $tenant['custom_domain'] ?? $tenant['subdomain'] ?? 'sem domínio';
                $status = $tenant['status'] ?? 'unknown';
                logStep("   - $name ($domain) - Status: $status", 'info');
            }
        }
    } catch (Exception $e) {
        logStep("Erro ao listar tenants: " . $e->getMessage(), 'warning');
    }

    // ===============================================
    // 3. CRIAR NOVO TENANT
    // ===============================================

    echo "\n=== Criação de Novo Tenant ===\n";

    $tenantData = [
        'name' => $EXAMPLE_CONFIG['tenant']['name'],
        'subdomain' => $EXAMPLE_CONFIG['tenant']['subdomain'],
        //'custom_domain' => $EXAMPLE_CONFIG['tenant']['custom_domain'],
        'description' => $EXAMPLE_CONFIG['tenant']['description'],
        'plan' => $EXAMPLE_CONFIG['tenant']['plan'],
        'settings' => $EXAMPLE_CONFIG['tenant']['settings'],
        'contact' => [
            'email' => $EXAMPLE_CONFIG['admin_user']['email']
        ]
    ];

    $tenant = null;
    $tenantId = null;

    try {
        $tenant = getOrCreateTenant($sdk, $tenantData);
        $tenantId = $tenant['tenant_id'];

        if ($tenant['existed']) {
            logStep("Tenant existente reutilizado", 'success');
        } else {
            logStep("Novo tenant criado", 'success');
        }

        logStep("Tenant ID: " . $tenantId, 'info');
        logStep("Domínio: " . ($tenant['tenant']['custom_domain'] ?? $tenant['tenant']['subdomain'] ?? 'N/A'), 'info');

    } catch (Exception $e) {
        logStep("Erro na criação do tenant: " . $e->getMessage(), 'error');
        throw $e;
    }

    // ===============================================
    // 4. OPERAÇÕES NO TENANT CRIADO
    // ===============================================

    echo "\n=== Operações no Tenant Criado ===\n";

    logStep("Tenant ID para operações: $tenantId", 'info');
    logStep("Como Organization Admin, você tem acesso a todos os tenants", 'info');

    // ===============================================
    // 5. PROVISIONAR RECURSOS PARA O TENANT
    // ===============================================

    echo "\n=== Provisionamento de Recursos ===\n";

    if ($tenantId) {
        // 5.1. Criar Produto
        try {
            logStep("Criando produto para o tenant...", 'info');

            $productData = $EXAMPLE_CONFIG['product'];
            $product = $sdk->products()->create($productData);

            logStep("✅ Produto criado com sucesso!", 'success');
            logStep("   Nome: " . ($product['name'] ?? $productData['name']), 'info');
            logStep("   Preço: R$ " . number_format($productData['price'] / 100, 2, ',', '.'), 'info');

        } catch (Exception $e) {
            logStep("Erro ao criar produto: " . $e->getMessage(), 'warning');
        }

        // 5.2. Obter API Key do Tenant
        try {
            logStep("Obtendo credenciais do tenant...", 'info');

            // Gerar API Key para o tenant usando o ApiKeyService
            try {
                $apiKeyData = [
                    'name' => 'API Key Principal - ' . date('Y-m-d H:i:s'),
                    'roles'=> ['tenant_admin'],
                    'permissions' => ['checkout:full', 'products:write', 'orders:read'],
                    'rate_limit' => 10000,
                    'expires_at' => null // Não expira
                ];

                $credentials = $sdk->organization()->apiKey()->generateApiKey($tenantId, $apiKeyData);

                if ($credentials && isset($credentials['api_key'])) {
                    $maskedKey = substr($credentials['api_key'], 0, 12) . '...';
                    logStep("✅ API Key gerada com sucesso!", 'success');
                    logStep("   API Key: {$maskedKey}", 'info');
                    logStep("   Key ID: " . ($credentials['id'] ?? 'N/A'), 'info');
                }
            } catch (Exception $apiKeyError) {
                logStep("Nota: API Key pode já existir", 'info');
                logStep("   Detalhes: " . $apiKeyError->getMessage(), 'debug');
            }

        } catch (Exception $e) {
            logStep("Erro ao obter credenciais: " . $e->getMessage(), 'warning');
        }

        // 5.3. Configurar Webhook
        try {
            logStep("Configurando webhook para o tenant...", 'info');

            $webhookUrl = 'https://seu-dominio.com/api/webhooks/clubify';

            if (method_exists($sdk, 'webhooks')) {
                $webhookData = [
                    'url' => $webhookUrl,
                    'events' => [
                        'payment.completed',
                        'payment.failed',
                        'subscription.created',
                        'order.created'
                    ],
                    'active' => true,
                    'name' => 'Webhook Principal'
                ];

                $webhook = $sdk->webhooks()->createWebhook($webhookData);

                if ($webhook) {
                    logStep("✅ Webhook configurado!", 'success');
                    logStep("   URL: " . $webhookUrl, 'info');
                }
            } else {
                logStep("Módulo webhooks não disponível", 'info');
            }

        } catch (Exception $e) {
            logStep("Erro ao configurar webhook: " . $e->getMessage(), 'warning');
        }
    }

    // ===============================================
    // 6. DEMONSTRAÇÃO DE OPERAÇÕES CROSS-TENANT
    // ===============================================

    echo "\n=== Operações Cross-Tenant (Organization Level) ===\n";

    try {
        logStep("Demonstrando operações cross-tenant...", 'info');

        // Listar todos os tenants da organização
        $allTenants = $sdk->organization()->tenant()->listTenantsByOrganization($EXAMPLE_CONFIG['organization']['id']);
        $tenantsData = $allTenants['data'] ?? $allTenants;

        logStep("Total de tenants na organization: " . count($tenantsData), 'info');

        // Mostrar informações dos tenants
        if (count($tenantsData) > 0) {
            logStep("Primeiros 3 tenants da organization:", 'info');
            foreach (array_slice($tenantsData, 0, 3) as $tenant) {
                $name = $tenant['name'] ?? 'Sem nome';
                $status = $tenant['status'] ?? 'unknown';
                $tenantId = extractTenantId($tenant) ?? 'unknown';
                $tenantIdShort = substr($tenantId, -8);
                logStep("   - {$name} (ID: ...{$tenantIdShort}) - Status: {$status}", 'info');
            }

            if (count($tenantsData) > 3) {
                logStep("   ... e mais " . (count($tenantsData) - 3) . " tenant(s)", 'info');
            }
        }

    } catch (Exception $e) {
        logStep("Erro em operações cross-tenant: " . $e->getMessage(), 'warning');
    }

    // ===============================================
    // 7. RELATÓRIO FINAL
    // ===============================================

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RELATÓRIO FINAL\n";
    echo str_repeat("=", 60) . "\n";

    logStep("✅ Execução completa concluída com sucesso!", 'success');

    if ($tenant) {
        logStep("Tenant criado/encontrado:", 'info');
        logStep("   Nome: " . ($tenant['tenant']['name'] ?? 'N/A'), 'info');
        logStep("   ID: " . $tenantId, 'info');
        logStep("   Domínio: " . ($tenant['tenant']['custom_domain'] ?? $tenant['tenant']['subdomain'] ?? 'N/A'), 'info');
        logStep("   Status: " . ($tenant['existed'] ? 'Existente' : 'Novo'), 'info');
    }

    echo "\n📚 Próximos passos:\n";
    echo "  1. Use o Tenant ID para integrar com seu sistema\n";
    echo "  2. Configure os webhooks conforme sua necessidade\n";
    echo "  3. Crie produtos e ofertas para o tenant\n";
    echo "  4. Configure métodos de pagamento\n";
    echo "  5. Customize o checkout para seu tenant\n";

    echo "\n" . str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    logStep("❌ ERRO FATAL: " . $e->getMessage(), 'error');
    logStep("Arquivo: " . $e->getFile() . ":" . $e->getLine(), 'error');

    if (method_exists($e, 'getTraceAsString')) {
        logStep("Stack trace:", 'debug');
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}

?>
