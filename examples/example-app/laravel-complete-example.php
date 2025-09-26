<?php

/**
 * EXAMPLE COMPLETO DE SUPER ADMIN - LARAVEL INTEGRATION
 *
 * Script completo que replica todas as funcionalidades do super-admin-example.php
 * mas usando o sistema de configuração nativo do Laravel através de config().
 *
 * FUNCIONALIDADES COMPLETAS:
 * ==========================
 * ✅ Usa sistema de configuração do Laravel (config/)
 * ✅ ✨ NOVO: Login com super admin usando EMAIL/PASSWORD (padrão da API)
 * ✅ 🔄 Fallback: API key para robustez
 * ✅ Criação/verificação de organizações
 * ✅ Provisionamento de credenciais
 * ✅ Verificação prévia para evitar conflitos
 * ✅ Criação de produtos
 * ✅ Listagem de tenants
 * ✅ Alternância de contexto
 * ✅ Relatório final completo
 *
 * DIFERENÇA DO ORIGINAL:
 * ======================
 * - Usa config() do Laravel ao invés de .env direto
 * - Bootstrap completo do Laravel
 * - Acesso a todos os serviços do Laravel
 * - Configuração através de arquivos em /config/
 *
 * ✨ AUTENTICAÇÃO SUPER ADMIN:
 * ===========================
 * PADRÃO: Email/Password (alinhado com DTOs da API)
 * - CLUBIFY_CHECKOUT_SUPER_ADMIN_EMAIL=admin@empresa.com
 * - CLUBIFY_CHECKOUT_SUPER_ADMIN_PASSWORD=senha_segura
 * - CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ID=507f1f77bcf86cd799439011
 *
 * FALLBACK: API Key (para robustez)
 * - CLUBIFY_CHECKOUT_SUPER_ADMIN_API_KEY=clb_live_key_here
 *
 * USO:
 * ====
 * 1. Configure as variáveis no .env e /config/ do Laravel
 * 2. Execute: php laravel-complete-example.php
 * 3. Monitore os logs para acompanhar o progresso
 */

// Usar o autoloader do Laravel (local)
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap completo do Laravel com tratamento inteligente de erros
try {
    $app = require_once __DIR__ . '/bootstrap/app.php';

    // Tentar bootstrap completo primeiro
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

} catch (Exception $e) {
    // Se falhar por ServiceProvider, tentar bootstrap parcial
    if (str_contains($e->getMessage(), 'ServiceProvider') || str_contains($e->getMessage(), 'not found')) {
        echo "⚠️  Aviso: Problema com ServiceProvider detectado - usando bootstrap alternativo\n";

        // Bootstrap alternativo: carregar só o essencial
        $app = require_once __DIR__ . '/bootstrap/app.php';

        // Registrar apenas os service providers essenciais manualmente
        $app->register(Illuminate\Foundation\Providers\FoundationServiceProvider::class);
        $app->register(Illuminate\Config\ConfigServiceProvider::class);
        $app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);

        // Fazer boot dos providers registrados
        $app->boot();

    } else {
        throw $e;
    }
}

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\ConflictException;
use Clubify\Checkout\Services\ConflictResolverService;

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
        $response = $sdk->superAdmin()->getTenantByDomain($domain);

        // Debug: mostrar estrutura da resposta
        logStep("Debug - Estrutura da resposta: " . json_encode(array_keys($response), JSON_UNESCAPED_SLASHES), 'debug');
        if (isset($response['tenant'])) {
            logStep("Debug - Chaves do tenant: " . json_encode(array_keys($response['tenant']), JSON_UNESCAPED_SLASHES), 'debug');
        }

        // Com as mudanças no user-management-service, agora temos uma estrutura padronizada
        if (isset($response['success']) && $response['success'] === true && isset($response['tenant'])) {
            // Tenant encontrado com nova estrutura
            return $response;
        } elseif (isset($response['success']) && $response['success'] === false) {
            // Tenant não encontrado, mas não é erro - é esperado
            logStep("Tenant não encontrado para domínio '$domain': " . ($response['message'] ?? 'N/A'), 'info');
            return null;
        } elseif ($response && !isset($response['success'])) {
            // Formato antigo (retrocompatibilidade)
            return $response;
        }

        // Fallback: buscar em lista de tenants se não encontrou diretamente
        $tenants = $sdk->superAdmin()->listTenants();
        foreach ($tenants['data'] as $tenant) {
            if (isset($tenant['domain']) && $tenant['domain'] === $domain) {
                return ['tenant' => $tenant, 'success' => true];
            }
            if (isset($tenant['custom_domain']) && $tenant['custom_domain'] === $domain) {
                return ['tenant' => $tenant, 'success' => true];
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

            // Extrair tenant_id do retorno da API (nova estrutura)
            $tenantId = null;
            $tenantData = null;

            // Nova estrutura padronizada
            if (isset($existingTenant['tenant'])) {
                $tenantData = $existingTenant['tenant'];
                // Para MongoDB, _id é um objeto, precisa converter para string
                if (isset($tenantData['_id'])) {
                    $tenantId = is_object($tenantData['_id']) ? (string)$tenantData['_id'] : $tenantData['_id'];
                } elseif (isset($tenantData['id'])) {
                    $tenantId = $tenantData['id'];
                } else {
                    $tenantId = null;
                }
            }
            
            // Debug log para verificar o que estamos extraindo
            logStep("Debug - Tenant ID extraído: " . ($tenantId ?? 'NULL') . " do campo: " .
                (isset($tenantData['_id']) ? '_id' :
                (isset($tenantData['id']) ? 'id' : 'nenhum')), 'debug');

            return [
                'organization' => $tenantData,
                'tenant_id' => $tenantId,
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
 * Obter ou criar usuário administrador para o tenant
 */
function getOrCreateUserManagement($sdk, $userData, $tenantId): array
{
    $userEmail = $userData['email'];
    $firstName = $userData['firstName'] ?? '';
    $lastName = $userData['lastName'] ?? '';

    logStep("Verificando se usuário admin '$userEmail' já existe...", 'info');

    try {
        // Tentar encontrar usuário existente - busca com tenantId específico
        $existingUser = $sdk->userManagement()->findUserByEmail($userEmail, $tenantId);

        if ($existingUser && isset($existingUser['user']) && isset($existingUser['user']['id'])) {
            $user = $existingUser['user'];
            logStep("Usuário admin já existe: $userEmail", 'success');
            logStep("ID do usuário: " . $user['id'], 'info');

            // Verificar se usuário tem as roles corretas para o tenant
            $hasCorrectRoles = checkUserRoles($user, $userData['roles'] ?? ['tenant_admin'], $tenantId);

            if (!$hasCorrectRoles) {
                logStep("Atualizando roles do usuário existente...", 'info');
                try {
                    $updatedUser = $sdk->userManagement()->updateUserRoles($user['id'], [
                        'tenantId' => $tenantId,
                        'roles' => $userData['roles'] ?? ['tenant_admin']
                    ]);
                    logStep("Roles do usuário atualizadas com sucesso", 'success');
                } catch (Exception $roleError) {
                    logStep("Erro ao atualizar roles: " . $roleError->getMessage(), 'warning');
                }
            }

            return [
                'user' => $user,
                'user_id' => $user['id'],
                'existed' => true,
                'created' => false
            ];
        }

        // Se chegou aqui, usuário não foi encontrado - criar novo
        // (Continua para a lógica de criação abaixo)

    } catch (\Clubify\Checkout\Modules\UserManagement\Exceptions\UserNotFoundException $e) {
        // Usuário não encontrado - normal, continuar para criação
        // (Continua para a lógica de criação abaixo)
    }

    // Lógica de criação de usuário (consolidada)
    logStep("Criando novo usuário para tenant: $tenantId", 'info');

    // Criar novo usuário com formato correto da API conforme user-management-service
    $newUserData = [
        'email' => $userEmail,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'password' => $userData['password'] ?? generateSecurePassword(),
        'roles' => $userData['roles'] ?? ['tenant_admin'],
        // tenantId (camelCase) quando super admin (nova exceção implementada)
        'tenantId' => $tenantId,
        // profile como objeto aninhado
        'profile' => [
            'language' => $userData['profile']['language'] ?? 'pt-BR',
            'timezone' => $userData['profile']['timezone'] ?? 'America/Sao_Paulo'
        ],
        // preferences como objeto aninhado
        'preferences' => [
            'emailNotifications' => true,
            'smsNotifications' => false,
            'marketing' => false
        ]
    ];

    try {
        $createResult = $sdk->userManagement()->createUser($newUserData, $tenantId);

        if ($createResult && $createResult['success'] && isset($createResult['user_id'])) {
            $newUser = $createResult['user'];
            $userId = $createResult['user_id'];

            logStep("Novo usuário admin criado com sucesso!", 'success');
            logStep("ID do usuário: " . $userId, 'info');
            logStep("Email: " . ($newUser['email'] ?? $userEmail), 'info');

            if (!isset($userData['password'])) {
                logStep("Senha gerada automaticamente - usuário deve alterar no primeiro login", 'warning');
            }

            return [
                'user' => $newUser,
                'user_id' => $userId,
                'existed' => false,
                'created' => true
            ];
        } else {
            throw new Exception('Falha na criação do usuário - resposta inválida da API');
        }

    } catch (Exception $createError) {
        // Tratamento de erro na criação do usuário
        $errorMessage = $createError->getMessage();

        // Verificar se é erro de usuário já existente
        if (str_contains($errorMessage, 'already exists') ||
            str_contains($errorMessage, 'User with this email already exists') ||
            str_contains($errorMessage, 'Conflict')) {

            logStep("Usuário já existe (409 Conflict). Buscando usuário existente...", 'warning');

            try {
                $existingUser = $sdk->userManagement()->findUserByEmail($userEmail, $tenantId);

                if ($existingUser && isset($existingUser['user'])) {
                    $user = $existingUser['user'];
                    $foundEmail = $user['email'] ?? 'unknown';

                    if ($foundEmail !== $userEmail) {
                        logStep("Email encontrado ($foundEmail) difere do buscado ($userEmail)", 'warning');
                    }

                    logStep("Usuário existente encontrado", 'success');
                    return [
                        'user' => $user,
                        'user_id' => $user['id'] ?? $user['_id'],
                        'existed' => true,
                        'created' => false,
                        'found_via_retry' => true,
                        'email_mismatch' => $foundEmail !== $userEmail ? true : false,
                        'found_email' => $foundEmail
                    ];
                }
            } catch (Exception $retryError) {
                logStep("Busca falhou: " . $retryError->getMessage(), 'error');
            }

            // Se chegamos aqui, nenhum método funcionou
            logStep("INCONSISTÊNCIA DETECTADA: API disse que usuário existe mas não conseguimos localizar", 'error');
            logStep("Email: $userEmail | TenantId: $tenantId", 'error');
            logStep("Possíveis causas: cache invalidado, contexto incorreto, ou timing issue", 'error');

            // Retornar um resultado que indica inconsistência mas permite continuidade
            return [
                'user' => ['email' => $userEmail, 'id' => 'inconsistent-state'],
                'user_id' => 'inconsistent-state',
                'existed' => true,
                'created' => false,
                'inconsistent' => true,
                'error' => 'User exists but could not be located'
            ];

        } elseif ($createError instanceof \Clubify\Checkout\Exceptions\ValidationException) {
            logStep("❌ Erro de validação na criação do usuário:", 'error');
            logStep("   Mensagem: $errorMessage", 'error');

            $validationErrors = $createError->getValidationErrors();
            if (!empty($validationErrors)) {
                logStep("   Campos com erro de validação:", 'error');
                foreach ($validationErrors as $field => $errors) {
                    if (is_array($errors)) {
                        foreach ($errors as $error) {
                            logStep("      - $field: $error", 'error');
                        }
                    } else {
                        logStep("      - $field: $errors", 'error');
                    }
                }
            }
        } else {
            logStep("❌ Erro na criação do usuário: $errorMessage", 'error');
        }

        // Retornar erro para indicar falha na criação
        return [
            'user' => ['email' => $userEmail],
            'user_id' => null,
            'existed' => false,
            'created' => false,
            'error' => $errorMessage
        ];
    }
}

/**
 * Verificar se usuário tem as roles corretas para o tenant
 */
function checkUserRoles($user, $requiredRoles, $tenantId): bool
{
    if (!isset($user['roles']) || !is_array($user['roles'])) {
        return false;
    }

    // Verificar se usuário tem pelo menos uma das roles necessárias para o tenant
    foreach ($user['roles'] as $userRole) {
        if (is_array($userRole)) {
            // Formato: ['role' => 'tenant_admin', 'tenantId' => 'xxx']
            if (isset($userRole['tenantId']) && $userRole['tenantId'] === $tenantId) {
                if (in_array($userRole['role'] ?? '', $requiredRoles)) {
                    return true;
                }
            }
        } elseif (in_array($userRole, $requiredRoles)) {
            // Formato simples de roles
            return true;
        }
    }

    return false;
}

/**
 * Gerar senha segura automaticamente
 */
function generateSecurePassword($length = 12): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charactersLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $password;
}

/**
 * Verificação prévia antes de criar recursos
 */
function checkBeforeCreate($sdk, $resourceType, $data, $tenantId = null): ?array
{
    try {
        switch ($resourceType) {
            case 'email':
                return $sdk->customers()->findByEmail($data['email']);

            case 'domain':
                return findTenantByDomain($sdk, $data['domain']) ? ['exists' => true] : ['exists' => false];

            case 'product':
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

/**
 * BLOCO A - FUNÇÕES HELPER INDEPENDENTES
 * =====================================
 */

/**
 * Helper function para encontrar tenant por subdomínio
 */
function findTenantBySubdomain($sdk, $subdomain) {
    try {
        // Primeiro tenta usar o método específico do SDK (mais eficiente)
        try {
            $tenant = $sdk->organization()->tenant()->getTenantBySubdomain($subdomain);
            if ($tenant) {
                return $tenant;
            }
        } catch (Exception $e) {
            logStep("Método específico não disponível, usando listTenants...", 'info');
        }

        // Fallback: busca manual (API não suporta filtros específicos)
        $tenants = $sdk->superAdmin()->listTenants();
        foreach ($tenants['data'] as $tenant) {
            if (isset($tenant['subdomain']) && $tenant['subdomain'] === $subdomain) {
                return $tenant;
            }
        }
        return null;
    } catch (Exception $e) {
        logStep("Erro ao buscar tenants por subdomínio: " . $e->getMessage(), 'warning');
        return null;
    }
}

/**
 * Verificar disponibilidade de email para um tenant específico
 */
function checkEmailAvailability($sdk, $email, $tenantId = null) {
    try {
        logStep("Verificando disponibilidade do email: $email", 'debug');

        // Usar método corrigido do SDK
        $result = $sdk->userManagement()->findUserByEmail($email, $tenantId);

        if ($result && isset($result['user'])) {
            logStep("Email já está em uso", 'debug');
            return [
                'exists' => true,
                'user' => $result['user']
            ];
        }

        return ['exists' => false];

    } catch (Exception $e) {
        // Email não encontrado é esperado
        logStep("Email disponível (não encontrado)", 'debug');
        return ['exists' => false];
    }
}

// =======================================================================
// INÍCIO DO SCRIPT PRINCIPAL
// =======================================================================

try {
    echo "=================================================================\n";
    echo "🚀 CLUBIFY CHECKOUT - LARAVEL SUPER ADMIN COMPLETE EXAMPLE\n";
    echo "=================================================================\n\n";

    // Limpar credenciais antigas em cache que podem estar corrompidas
    logStep("Limpando cache de credenciais...", 'info');
    $credentialsPath = storage_path('app/clubify/credentials');
    if (is_dir($credentialsPath)) {
        $files = glob($credentialsPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        logStep("Cache de credenciais limpo", 'success');
    }

    // ===============================================
    // CONFIGURAÇÕES DO EXEMPLO (VIA CONFIG LARAVEL)
    // ===============================================





    $EXAMPLE_CONFIG = [
        'organization' => [
            'name' => config('app.example_org_name', 'Nova Empresa de Testes Ltda'),
            'admin_email' => config('app.example_admin_email', 'admin@nova-empresa-testes.com'),
            'admin_name' => config('app.example_admin_name', 'João Admin Tes'),
            'subdomain' => config('app.example_subdomain', 'nova-empresa-teste'),
            'custom_domain' => config('app.example_custom_domain', 'checkout.nova-empresa-teste.com')
        ],
        'product' => [
            'name' => config('app.example_product_name', 'Produto Demo Laravel Teste'),
            'description' => config('app.example_product_desc', 'Produto criado via SDK integrado com Laravel'),
            'price_amount' => (int) config('app.example_product_price', 9999), // R$ 99,99 em centavos
            'currency' => config('app.example_product_currency', 'BRL'),
            'type' => 'digital'
        ],
        'sdk' => [
            'environment' => config('clubify-checkout.environment', 'sandbox')
        ],
        'options' => [
            'force_recreate_org' => (bool) config('app.example_force_recreate_org', false),
            'force_recreate_product' => (bool) config('app.example_force_recreate_product', false),
            'show_detailed_logs' => (bool) config('app.example_show_detailed_logs', true),
            'max_tenants_to_show' => (int) config('app.example_max_tenants_show', 3),
            'skip_super_admin_init' => (bool) config('app.example_skip_super_admin_init', false)
        ],
        'super_admin' => [
            'email' => config('clubify-checkout.super_admin.email'),
            'password' => config('clubify-checkout.super_admin.password'),
            'tenant_id' => config('clubify-checkout.super_admin.tenant_id')
        ],
        'user' => [
            "email" => "tenant.admin-super-tese@nova-empresa.com",
            "firstName" => "User Tenant teste",
            "lastName" => "Admin",
            "password" => "P@ssw0rd!",
            "roles" => ["tenant_admin"],
            "profile" => [ 
                "language" => "pt-BR", 
                "timezone" => "America/Sao_Paulo" 
            ]
        ]
    ];

    logStep("Iniciando exemplo completo de Super Admin (Laravel Integration)", 'info');
    logStep("Organização: {$EXAMPLE_CONFIG['organization']['name']} | Ambiente: " . config('app.env', 'unknown'), 'info');

    // ===============================================
    // 1. INICIALIZAÇÃO COMO SUPER ADMIN
    // ===============================================

    logStep("Inicializando SDK como Super Admin", 'info');


    // Usar configuração diretamente do Laravel (já estruturada)
    $config = config('clubify-checkout');

    // Inicializar SDK com configuração completa
    $sdk = new ClubifyCheckoutSDK($config);
    logStep("SDK inicializado v" . $sdk->getVersion(), 'success');

    // ✨ NOVO: Inicializar como super admin usando EMAIL/PASSWORD
    try {
        logStep("Inicializando como super admin com email/password...", 'info');

        if (method_exists($sdk, 'setHttpTimeout')) {
            $sdk->setHttpTimeout(30);
        }

        // Credenciais do super admin (padrão: email/password)
        $superAdminCredentials = $EXAMPLE_CONFIG['super_admin'];

        // Debug: mostrar qual método está sendo usado
        if (!empty($superAdminCredentials['email'])) {
            logStep("   Método: Email/Password (padrão da API)", 'info');
            logStep("   Email: " . $superAdminCredentials['email'], 'info');
        } elseif (!empty($superAdminCredentials['username'])) {
            logStep("   Método: Username/Password (legacy - tratado como email)", 'info');
            logStep("   Username: " . $superAdminCredentials['username'], 'info');
        } elseif (!empty($superAdminCredentials['api_key'])) {
            logStep("   Método: API Key (fallback)", 'info');
            logStep("   API Key: " . substr($superAdminCredentials['api_key'], 0, 10) . "...", 'info');
        }

        $initResult = $sdk->initializeAsSuperAdmin($superAdminCredentials);

        if ($initResult['success']) {
            logStep("✅ SDK inicializado como super admin", 'success');
            logStep("   Modo: " . $initResult['mode'], 'info');
            logStep("   Role: " . $initResult['role'], 'info');
        } else {
            logStep("❌ Falha na inicialização como super admin", 'error');
        }

    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logStep("❌ Erro ao inicializar como super admin: " . $errorMsg, 'error');

        // Sugestões de troubleshooting
        if (str_contains($errorMsg, 'authentication') || str_contains($errorMsg, 'login')) {
            logStep("💡 Sugestão: Verifique as credenciais no .env:", 'info');
            logStep("   - CLUBIFY_CHECKOUT_SUPER_ADMIN_EMAIL", 'info');
            logStep("   - CLUBIFY_CHECKOUT_SUPER_ADMIN_PASSWORD", 'info');
            logStep("   - CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ID", 'info');
        }
    }

    // ===============================================
    // 2. CRIAÇÃO DE ORGANIZAÇÃO (COM VERIFICAÇÃO)
    // ===============================================

    echo "\n=== Criando ou Encontrando Organização ===\n";

    $organizationData = [
        // Campos obrigatórios para createOrganization
        'name' => $EXAMPLE_CONFIG['organization']['name'],
        'admin_email' => $EXAMPLE_CONFIG['organization']['admin_email'],
        'admin_name' => $EXAMPLE_CONFIG['organization']['admin_name'],

        // Campos opcionais para configuração do tenant
        'subdomain' => $EXAMPLE_CONFIG['organization']['subdomain'],
        'custom_domain' => $EXAMPLE_CONFIG['organization']['custom_domain'],
        'description' => 'Organização criada via SDK PHP para demonstração das funcionalidades do Clubify Checkout',
        'plan' => 'starter',
        'country' => 'BR',
        'industry' => 'technology',

        // Configurações da organização
        'settings' => [
            'timezone' => 'America/Sao_Paulo',
            'currency' => 'BRL',
            'language' => 'pt-BR'
        ],

        // Features habilitadas
        'features' => [
            'analytics' => true,
            'payments' => true,
            'subscriptions' => true,
            'webhooks' => true
        ],

        // Dados de contato de suporte (opcional)
        'support_email' => $EXAMPLE_CONFIG['organization']['admin_email']
    ];


    $tenantAdminUserData = [
        "email" => $EXAMPLE_CONFIG['user']['email'],
        "firstName" => $EXAMPLE_CONFIG['user']['firstName'],
        "lastName" => $EXAMPLE_CONFIG['user']['lastName'],
        "password" => $EXAMPLE_CONFIG['user']['password'],
        "roles" => $EXAMPLE_CONFIG['user']['roles'],
        "profile" => $EXAMPLE_CONFIG['user']['profile']
    ];

    $tenantId = null;
    $organization = null;

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
        logStep("Erro na criação de organização: " . $e->getMessage(), 'error');
        logStep("Detalhes do erro: " . $e->getFile() . ':' . $e->getLine(), 'error');
    }



    // Sub-seção: Gestão de Credenciais Avançada (Versão Melhorada)
    try {
        logStep("🔑 Iniciando gestão automatizada de credenciais...", 'info');

        if (!$tenantId || $tenantId === 'unknown') {
            logStep("Tenant ID inválido ou não encontrado, pulando gestão de credenciais", 'warning');
        } else {
            // Configuração para gestão de credenciais (usando configurações instaladas automaticamente)
            $credentialConfig = [
                'auto_rotate' => $_ENV['CLUBIFY_ENABLE_KEY_ROTATION'] ?? config('app.example_enable_key_rotation', true),
                'max_key_age_days' => $_ENV['CLUBIFY_MAX_API_KEY_AGE_DAYS'] ?? config('app.max_api_key_age_days', 90),
                'grace_period_hours' => $_ENV['CLUBIFY_KEY_ROTATION_GRACE_PERIOD'] ?? config('app.key_rotation_grace_period', 24),
                'force_rotation' => $_ENV['CLUBIFY_FORCE_KEY_ROTATION'] ?? config('app.force_key_rotation', false),
                'create_if_missing' => $_ENV['CLUBIFY_AUTO_CREATE_API_KEYS'] ?? config('app.auto_create_api_keys', true),
                'key_config' => [
                    'name' => "Laravel Example App - " . date('Y-m-d H:i:s'),
                    'description' => 'Auto-generated tenant admin key for Laravel example'
                ],
                'ip_whitelist' => $_ENV['CLUBIFY_IP_WHITELIST'] ?? null,
                'allowed_origins' => explode(',', $_ENV['CLUBIFY_ALLOWED_ORIGINS'] ?? '*')
            ];

            // Tentar usar método automatizado se disponível
            if (method_exists($sdk->superAdmin(), 'ensureTenantCredentials')) {
                logStep("Usando gestão automatizada de credenciais...", 'info');

                $credentials = $sdk->superAdmin()->ensureTenantCredentials($tenantId, $credentialConfig);

                if ($credentials) {
                    // Determinar status da credencial
                    $status = '';
                    if ($credentials['is_new'] ?? false) {
                        $status = '🆕 Nova chave criada automaticamente';
                    } elseif ($credentials['is_rotated'] ?? false) {
                        $status = '🔄 Chave rotacionada automaticamente';
                    } else {
                        $status = '✅ Chave existente válida';
                    }

                    logStep("Credenciais obtidas com sucesso! {$status}", 'success');
                    logStep("Role: " . ($credentials['role'] ?? 'N/A'), 'info');
                    logStep("Idade: " . ($credentials['key_age_days'] ?? 'N/A') . " dias", 'info');
                    logStep("API Key ID: " . ($credentials['api_key_id'] ?? 'N/A'), 'info');

                    // Exibir chave mascarada para debug
                    if (isset($credentials['api_key'])) {
                        $maskedKey = substr($credentials['api_key'], 0, 12) . '...';
                        logStep("🔑 API Key: {$maskedKey}", 'info');
                    }

                    // Log de permissões se disponível
                    if (!empty($credentials['permissions'])) {
                        $permissionCount = count($credentials['permissions']);
                        logStep("🔐 Permissões configuradas: {$permissionCount} módulos", 'info');
                    }

                } else {
                    logStep("Falha ao obter credenciais através do método automatizado", 'error');
                }

            } else {
                    logStep("Método getTenantCredentials não disponível no SDK", 'error');
                
            }
        }

    } catch (Exception $e) {
        logStep("Erro na gestão de credenciais: " . $e->getMessage(), 'error');
        logStep("Detalhes do erro: " . $e->getFile() . ':' . $e->getLine(), 'error');
    }

exit(1);


    try {

        // ===============================================
        // 3. PROVISIONAMENTO DE CREDENCIAIS
        // ===============================================

        echo "\n=== Provisionamento de Credenciais ===\n";

        $userManagement = null;

        if ($tenantId && $tenantId !== 'unknown') {
            try {
                $userManagement = getOrCreateUserManagement($sdk, $tenantAdminUserData, $tenantId);

                // Verificar se houve erro
                if (isset($userManagement['error'])) {
                    logStep("Erro na criação/verificação do usuário: " . $userManagement['error'], 'error');
                } else {
                    if ($userManagement['existed']) {
                        logStep("Usuário admin existente encontrado", 'success');
                    } else {
                        logStep("Novo usuário admin criado", 'success');
                    }
                }
            } catch (Exception $e) {
                logStep("Erro na criação/verificação do usuário: " . $e->getMessage(), 'error');
            }
        }

    } catch (Exception $e) {
        logStep("Erro na operação de organização: " . $e->getMessage(), 'error');
    }

    // ===============================================
    // 4. ALTERNÂNCIA PARA CONTEXTO DO TENANT
    // ===============================================

    if ($tenantId && $tenantId !== 'unknown') {
        echo "\n=== Alternando para Contexto do Tenant ===\n";

        try {
            logStep("Alternando para tenant: $tenantId", 'info');

            $switchResult = $sdk->superAdmin()->switchToTenant($tenantId);

            if ($switchResult['success'] ?? false) {
                logStep("Contexto alternado com sucesso!", 'success');
                logStep("   Current Tenant: " . ($switchResult['current_tenant_id'] ?? 'N/A'), 'info');
                logStep("   Role: " . ($switchResult['current_role'] ?? 'tenant_admin'), 'info');
            } else {
                logStep("Falha na alternância de contexto", 'error');
                logStep("   Erro: " . ($switchResult['error'] ?? 'Unknown error'), 'error');
            }

        } catch (Exception $e) {
            logStep("Erro ao alternar contexto: " . $e->getMessage(), 'warning');
        }
    }



    // ===============================================
    // 4. INFRAESTRUTURA AVANÇADA (BLOCO B)
    // ===============================================

    echo "\n=== Infraestrutura Avançada ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        // Sub-seção: Provisionamento de Domínio e SSL
        logStep("Verificando provisionamento de infraestrutura...", 'info');

        try {
            $customDomain = $EXAMPLE_CONFIG['organization']['custom_domain'];

            if (method_exists($sdk->userManagement(), 'configureDomain')) {
                $domainResult = $sdk->userManagement()->configureDomain($tenantId, [
                    'domain' => $customDomain,
                    'auto_ssl' => true,
                    'environment' => config('clubify-checkout.environment', 'sandbox')
                ]);
                logStep("Domínio provisionado: $customDomain", 'success');
            } else {
                logStep("Provisionamento automático não disponível", 'info');
            }

        } catch (Exception $e) {
            logStep("Erro no provisionamento: " . $e->getMessage(), 'warning');
        }


    } else {
        logStep("Nenhum tenant válido para infraestrutura", 'warning');
    }

    logStep("Seção de infraestrutura concluída", 'success');
    // ===============================================
    // 5. LISTAGEM DE TENANTS
    // ===============================================


    // Sub-seção: Estatísticas do Sistema
    try {
        if (method_exists($sdk->superAdmin(), 'getSystemStats')) {
            $stats = $sdk->superAdmin()->getSystemStats(5);

            if ($stats && isset($stats['data'])) {
                $totalTenants = $stats['data']['total_tenants'] ?? 'N/A';
                $activeTenants = $stats['data']['active_tenants'] ?? 'N/A';
                logStep("Tenants: $totalTenants total, $activeTenants ativos", 'info');
            }
        }
    } catch (Exception $e) {
        logStep("Erro ao obter estatísticas: " . $e->getMessage(), 'warning');
    }


    echo "\n=== Listagem de Tenants Disponíveis ===\n";

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
                $plan = $tenant['plan'] ?? 'sem plano';
                $domain = $tenant['domain'] ?? $tenant['subdomain'] ?? 'sem domínio';
                $tenantIdShort = substr($tenant['_id'] ?? $tenant['id'] ?? 'no-id', -8);

                logStep("   - $name", 'info');
                logStep("     Domain: $domain | Status: $status | Plan: $plan | ID: $tenantIdShort", 'debug');
                $count++;
            }

            if (count($tenantsData) > $maxToShow) {
                logStep("   ... e mais " . (count($tenantsData) - $maxToShow) . " tenants", 'info');
            }
        }

    } catch (Exception $e) {
        logStep("Erro ao listar tenants: " . $e->getMessage(), 'warning');
    }

    // ===============================================
    // 6. GESTÃO DE PRODUTOS (CONTEXTO TENANT ADMIN)
    // ===============================================

    echo "\n=== Gestão de Produtos ===\n";

    // VERIFICAÇÃO: Produtos devem ser gerenciados como tenant admin
    $currentContext = $sdk->getCurrentContext();
    $currentMode = $currentContext['mode'] ?? 'unknown';

    if ($currentMode === 'super_admin' && $tenantId) {
        try {
            $switchResult = $sdk->superAdmin()->switchToTenant($tenantId);
            if ($switchResult['success'] ?? false) {
                logStep("Contexto alternado para Tenant Admin", 'success');
            }
        } catch (Exception $switchError) {
            logStep("Erro ao alternar contexto: " . $switchError->getMessage(), 'error');
        }
    }

    try {
        $products = $sdk->products()->list();
        $productsData = $products['data'] ?? [];
        logStep("Produtos encontrados: " . count($productsData), 'info');

    } catch (Exception $e) {
        logStep("Erro ao listar produtos: " . $e->getMessage(), 'warning');
    }

    // Criar produto de exemplo usando verificação prévia
    $productData = [
        'name' => $EXAMPLE_CONFIG['product']['name'],
        'description' => $EXAMPLE_CONFIG['product']['description'],
        'price' => $EXAMPLE_CONFIG['product']['price_amount'], // Preço em centavos (inteiro)
        'currency' => $EXAMPLE_CONFIG['product']['currency'],
        'type' => 'digital'
    ];


    // ===============================================
    // BLOCO E - MELHORIAS AVANÇADAS DE PRODUTOS
    // ===============================================

    try {
        // Usar método avançado se disponível
        $useAdvancedMethod = config('app.example_use_advanced_products', false);

        if ($useAdvancedMethod && method_exists($sdk->products(), 'createComplete')) {
            logStep("Usando método avançado para produto", 'info');

            // Dados expandidos para createComplete
            $productDataComplete = $productData + [
                'metadata' => [
                    'created_via' => 'laravel_sdk',
                    'environment' => config('app.env'),
                    'tenant_context' => $tenantId,
                    'laravel_version' => app()->version()
                ],
                'seo' => [
                    'title' => $productData['name'] . ' - Compre Agora',
                    'description' => $productData['description'],
                    'keywords' => ['produto', 'digital', 'laravel', 'clubify']
                ],
                'images' => [
                    config('app.example_product_image', '/images/default-product.jpg')
                ],
                'categories' => [
                    config('app.example_product_category', 'Digital Products')
                ],
                'features' => [
                    'Acesso imediato após compra',
                    'Suporte 24/7',
                    'Garantia de 30 dias'
                ]
            ];

            $productResult = $sdk->products()->createComplete($productDataComplete);
            logStep("Produto criado com método avançado", 'success');

        } else {
            // Usar método tradicional melhorado
            $productResult = getOrCreateProduct($sdk, $productData);
        }

        $productName = $productResult['product']['name'] ??
                      $productResult['product']['data']['name'] ??
                      $productData['name'] ?? 'Nome não disponível';

        if ($productResult['existed'] ?? false) {
            logStep("Produto existente encontrado: " . $productName, 'success');
        } else {
            logStep("Novo produto criado: " . $productName, 'success');

            // Adicionar tags se suportado
            $product = $productResult['product'];
            $productId = $product['id'] ?? $product['_id'] ?? null;

            if ($productId) {
                $productTags = config('app.example_product_tags', ['laravel', 'sdk', 'auto']);
                if (!empty($productTags) && method_exists($sdk->products(), 'addTags')) {
                    try {
                        $sdk->products()->addTags($productId, $productTags);
                        logStep("Tags adicionadas: " . implode(', ', $productTags), 'info');
                    } catch (Exception $tagError) {
                        logStep("Erro ao adicionar tags: " . $tagError->getMessage(), 'debug');
                    }
                }
            }
        }


    } catch (Exception $e) {
        logStep("Erro na operação de produto: " . $e->getMessage(), 'warning');

        $errorCode = $e->getCode();
        if ($errorCode === 401) {
            logStep("Erro de autorização - verifique contexto", 'error');
        } elseif ($errorCode === 409) {
            logStep("Conflito - produto pode já existir", 'warning');
        }
    }

    // ===============================================
    // 7. CONFIGURAÇÃO DE WEBHOOKS (BLOCO C)
    // ===============================================

    echo "\n=== Configuração de Webhooks ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            logStep("Configurando webhooks...", 'info');

            $baseUrl = config('app.url');
            $environment = config('app.env');

            // Detecção de URL para webhook
            if ($environment === 'local' || str_contains($baseUrl, 'localhost')) {
                $webhookUrl = config('clubify-checkout.webhook.url',
                    config('clubify-checkout.webhook.dev_url',
                        'https://your-ngrok-url.ngrok.io/api/webhooks/clubify'
                    )
                );

                if (str_contains($webhookUrl, 'your-ngrok-url')) {
                    logStep("Configure CLUBIFY_WEBHOOK_DEV_URL no .env", 'warning');
                }

            } else {
                $webhookUrl = config('clubify-checkout.webhook.url',
                    str_replace('http://', 'https://', $baseUrl) . '/api/webhooks/clubify'
                );

                if (!str_starts_with($webhookUrl, 'https://')) {
                    $webhookUrl = str_replace('http://', 'https://', $webhookUrl);
                }
            }

            $webhookSecret = config('clubify-checkout.webhook.secret', 'webhook-secret-key1234567890123456');
            $webhookEvents = config('clubify-checkout.webhook.events', [
                'payment.completed',
                'payment.failed',
                'subscription.created',
                'subscription.cancelled',
                'order.created',
                'order.completed'
            ]);

            logStep("Webhook URL: $webhookUrl", 'info');

            // Verificar se o módulo webhooks está disponível
            if (method_exists($sdk, 'webhooks')) {
                try {
                    $existingWebhooks = $sdk->webhooks()->listWebhooks();
                    $webhookExists = false;

                    $webhooksData = $existingWebhooks['data'] ?? $existingWebhooks;
                    if (is_array($webhooksData)) {
                        foreach ($webhooksData as $webhook) {
                            if (isset($webhook['url']) && $webhook['url'] === $webhookUrl) {
                                $webhookExists = true;
                                logStep("Webhook já existe para esta URL", 'success');
                                break;
                            }
                        }
                    }

                    // Criar webhook se não existir
                    if (!$webhookExists) {
                        $webhookData = [
                            'url' => $webhookUrl,
                            'secret' => $webhookSecret,
                            'events' => $webhookEvents,
                            'active' => true,
                            'name' => 'Laravel Integration Webhook'
                        ];

                        $webhookResult = $sdk->webhooks()->createWebhook($webhookData);

                        if ($webhookResult && isset($webhookResult['id'])) {
                            logStep("Webhook criado com sucesso! ID: " . $webhookResult['id'], 'success');
                        }
                    }

                } catch (Exception $webhookError) {
                    logStep("Erro ao configurar webhook: " . $webhookError->getMessage(), 'warning');
                }

            } else {
                logStep("Módulo webhooks não disponível - configuração manual necessária", 'warning');
            }

        } catch (Exception $e) {
            logStep("Erro na configuração de webhooks: " . $e->getMessage(), 'warning');
        }

    } else {
        logStep("Nenhum tenant válido para webhooks", 'warning');
    }

    // ===============================================
    // 8. OFERTAS E FUNIS DE VENDAS (BLOCO D)
    // ===============================================

    echo "\n=== Configuração de Ofertas e Funis de Vendas ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            logStep("Configurando ofertas e funis...", 'info');

            // Verificar se SDK tem módulo de ofertas
            if (method_exists($sdk, 'offer')) {

                // Configurações de oferta via config Laravel
                $offerConfig = [
                    'name' => config('app.example_offer_name', 'Oferta Especial Laravel'),
                    'description' => config('app.example_offer_desc', 'Oferta criada via SDK Laravel'),
                    'type' => 'single_product',
                    'active' => true,
                    'settings' => [
                        'max_purchases' => 100,
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'currency' => config('clubify-checkout.currency', 'BRL')
                    ]
                ];

                // Usar produto existente se disponível
                if (isset($productResult['product'])) {
                    $product = $productResult['product'];
                    $productId = $product['id'] ?? $product['_id'] ?? null;

                    if ($productId) {
                        $offerConfig['products'] = [$productId];
                        logStep("Usando produto existente para oferta: $productId", 'info');

                        try {
                            logStep("Criando oferta com produto associado...", 'info');
                            $offerResult = $sdk->offer()->createOffer($offerConfig);

                            if ($offerResult && isset($offerResult['id'])) {
                                $offerId = $offerResult['id'];
                                logStep("Oferta criada com sucesso!", 'success');
                                logStep("ID: $offerId", 'info');

                                // Configuração de Tema
                                try {
                                    logStep("Configurando tema da oferta...", 'info');
                                    $themeConfig = [
                                        'primary_color' => config('app.example_theme_primary', '#007bff'),
                                        'secondary_color' => config('app.example_theme_secondary', '#6c757d'),
                                        'font_family' => config('app.example_theme_font', 'Roboto, sans-serif'),
                                        'logo_url' => config('app.example_theme_logo', ''),
                                        'custom_css' => config('app.example_theme_css', ''),
                                        'layout_style' => 'modern'
                                    ];

                                    if (method_exists($sdk->offer(), 'configureTheme')) {
                                        $themeResult = $sdk->offer()->configureTheme($offerId, $themeConfig);
                                        if ($themeResult) {
                                            logStep("Tema configurado com sucesso!", 'success');
                                            logStep("Cor primária: " . $themeConfig['primary_color'], 'info');
                                        }
                                    } else {
                                        logStep("Método configureTheme não disponível", 'info');
                                    }

                                } catch (Exception $themeError) {
                                    logStep("Erro na configuração do tema: " . $themeError->getMessage(), 'warning');
                                }

                                // Configuração de Layout
                                try {
                                    logStep("Configurando layout da oferta...", 'info');
                                    $layoutConfig = [
                                        'type' => config('app.example_layout_type', 'single_column'),
                                        'show_testimonials' => config('app.example_layout_testimonials', true),
                                        'show_guarantee' => config('app.example_layout_guarantee', true),
                                        'show_timer' => config('app.example_layout_timer', false),
                                        'sections' => [
                                            'header' => ['enabled' => true, 'position' => 'top'],
                                            'product_info' => ['enabled' => true, 'position' => 'center'],
                                            'testimonials' => ['enabled' => true, 'position' => 'bottom'],
                                            'footer' => ['enabled' => true, 'position' => 'bottom']
                                        ]
                                    ];

                                    if (method_exists($sdk->offer(), 'configureLayout')) {
                                        $layoutResult = $sdk->offer()->configureLayout($offerId, $layoutConfig);
                                        if ($layoutResult) {
                                            logStep("Layout configurado com sucesso!", 'success');
                                            logStep("Tipo: " . $layoutConfig['type'], 'info');
                                        }
                                    } else {
                                        logStep("Método configureLayout não disponível", 'info');
                                    }

                                } catch (Exception $layoutError) {
                                    logStep("Erro na configuração do layout: " . $layoutError->getMessage(), 'warning');
                                }

                                // Configuração de Upsell
                                try {
                                    logStep("Configurando upsell para a oferta...", 'info');

                                    // Criar produto adicional para upsell se não existir
                                    $upsellProductData = [
                                        'name' => config('app.example_upsell_product', 'Produto Upsell Laravel'),
                                        'description' => 'Produto adicional para upsell criado via Laravel SDK',
                                        'price' => config('app.example_upsell_price', 4999), // R$ 49,99
                                        'currency' => config('clubify-checkout.currency', 'BRL'),
                                        'type' => 'digital'
                                    ];

                                    $upsellProductResult = getOrCreateProduct($sdk, $upsellProductData);
                                    $upsellProduct = $upsellProductResult['product'];
                                    $upsellProductId = $upsellProduct['id'] ?? $upsellProduct['_id'] ?? null;

                                    if ($upsellProductId) {
                                        $upsellConfig = [
                                            'name' => 'Oferta Especial Upsell',
                                            'product_id' => $upsellProductId,
                                            'discount_percent' => config('app.example_upsell_discount', 20),
                                            'position' => 'after_payment',
                                            'active' => true,
                                            'settings' => [
                                                'show_timer' => true,
                                                'timer_minutes' => 10,
                                                'max_attempts' => 1
                                            ]
                                        ];

                                        if (method_exists($sdk->offer(), 'addUpsell')) {
                                            $upsellResult = $sdk->offer()->addUpsell($offerId, $upsellConfig);
                                            if ($upsellResult) {
                                                logStep("Upsell configurado com sucesso!", 'success');
                                                logStep("Produto upsell: " . $upsellProductData['name'], 'info');
                                                logStep("Desconto: " . $upsellConfig['discount_percent'] . "%", 'info');
                                            }
                                        } else {
                                            logStep("Método addUpsell não disponível", 'info');
                                        }
                                    }

                                } catch (Exception $upsellError) {
                                    logStep("Erro na configuração do upsell: " . $upsellError->getMessage(), 'warning');
                                }

                            } else {
                                logStep("Falha na criação da oferta", 'error');
                            }

                        } catch (Exception $offerError) {
                            logStep("Erro na criação da oferta: " . $offerError->getMessage(), 'warning');
                        }

                    } else {
                        logStep("ID do produto não disponível para criar oferta", 'warning');
                    }
                } else {
                    logStep("Nenhum produto disponível para criar oferta", 'warning');
                }

            } else {
                logStep("Módulo offer não disponível - configure via interface", 'warning');
            }

        } catch (Exception $e) {
            logStep("Erro na configuração de ofertas: " . $e->getMessage(), 'warning');
        }

    } else {
        logStep("Nenhum tenant válido para ofertas", 'warning');
    }

    // ===============================================
    // 9. VOLTA PARA CONTEXTO SUPER ADMIN
    // ===============================================

    echo "\n=== Voltando para Contexto Super Admin ===\n";

    try {
        $sdk->superAdmin()->switchToSuperAdmin();
        logStep("Retornado para contexto Super Admin", 'success');

    } catch (Exception $e) {
        logStep("Erro ao voltar para super admin: " . $e->getMessage(), 'warning');
    }

    // ===============================================
    // 10. ADMINISTRAÇÃO AVANÇADA E AUDITORIA (BLOCO F)
    // ===============================================

    echo "\n=== Administração Avançada e Auditoria ===\n";

    try {
        logStep("Iniciando auditoria e administração avançada...", 'info');

        // Coletar métricas finais
        $finalMetrics = [
            'execution_start_time' => $timestamp ?? date('Y-m-d H:i:s'),
            'execution_end_time' => date('Y-m-d H:i:s'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'sdk_version' => method_exists($sdk, 'getVersion') ? $sdk->getVersion() : 'unknown',
            'environment' => config('app.env'),
            'tenant_id' => $tenantId,
            'organization_existed' => $organization['existed'] ?? false
        ];

        // Auditoria de credenciais (se disponível)
        if ($tenantId && method_exists($sdk->superAdmin(), 'getTenantCredentials')) {
            try {
                $auditCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
                logStep("Auditoria de credenciais concluída", 'success');

                if (isset($auditCredentials['api_key'])) {
                    $keyAge = $auditCredentials['key_age_days'] ?? 'N/A';
                    $keyStatus = $auditCredentials['status'] ?? 'active';
                    logStep("   Status da API Key: $keyStatus (idade: $keyAge dias)", 'info');

                    // Alerta de segurança para chaves antigas
                    if (is_numeric($keyAge) && $keyAge > 365) {
                        logStep("   ⚠️  ALERTA DE SEGURANÇA: API Key muito antiga ($keyAge dias)", 'warning');
                        logStep("   → Considere rotacionar a chave regularmente", 'info');
                    }
                }

            } catch (Exception $auditError) {
                logStep("Erro na auditoria de credenciais: " . $auditError->getMessage(), 'warning');
            }
        }

        // Usar switchToSuperAdmin() com verificação robusta
        logStep("Garantindo retorno ao contexto Super Admin...", 'info');
        try {
            if (method_exists($sdk->superAdmin(), 'switchToSuperAdmin')) {
                $switchResult = $sdk->superAdmin()->switchToSuperAdmin();
                logStep("switchToSuperAdmin() executado com sucesso", 'success');

                // Verificar se realmente mudou o contexto
                $finalContext = $sdk->getCurrentContext();
                $finalMode = $finalContext['mode'] ?? 'unknown';

                if ($finalMode === 'super_admin') {
                    logStep("   ✅ Contexto confirmado: Super Admin", 'success');
                } else {
                    logStep("   ⚠️  Contexto atual: $finalMode (esperado: super_admin)", 'warning');
                }

                $finalMetrics['final_context'] = $finalMode;
            } else {
                logStep("Método switchToSuperAdmin não disponível", 'info');
                $finalMetrics['final_context'] = 'unchanged';
            }

        } catch (Exception $switchError) {
            logStep("Erro ao retornar para Super Admin: " . $switchError->getMessage(), 'warning');
            $finalMetrics['switch_error'] = $switchError->getMessage();
        }

        // Relatório de recursos criados
        $resourcesCreated = [];
        if (isset($organization) && !($organization['existed'] ?? false)) {
            $resourcesCreated[] = 'Organização';
        }
        if (isset($productResult) && !($productResult['existed'] ?? false)) {
            $resourcesCreated[] = 'Produto';
        }
        if (isset($offerResult)) {
            $resourcesCreated[] = 'Oferta';
        }
        if (isset($webhookResult)) {
            $resourcesCreated[] = 'Webhook';
        }

        if (!empty($resourcesCreated)) {
            logStep("Recursos criados nesta execução: " . implode(', ', $resourcesCreated), 'success');
        } else {
            logStep("Nenhum recurso novo criado (recursos existentes reutilizados)", 'info');
        }

    } catch (Exception $adminError) {
        logStep("Erro na administração avançada: " . $adminError->getMessage(), 'warning');
    }

    // ===============================================
    // 11. RELATÓRIO FINAL EXPANDIDO
    // ===============================================

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RELATÓRIO FINAL - LARAVEL SUPER ADMIN COMPLETE\n";
    echo str_repeat("=", 60) . "\n";

    logStep("Execução completa concluída com sucesso!", 'success');

    if (isset($finalMetrics['execution_start_time']) && isset($finalMetrics['execution_end_time'])) {
        $startTime = strtotime($finalMetrics['execution_start_time']);
        $endTime = strtotime($finalMetrics['execution_end_time']);
        $duration = $endTime - $startTime;
        logStep("Tempo de execução: {$duration}s", 'info');
    }
    logStep("Laravel v" . (app()->version()) . " | PHP v" . PHP_VERSION . " | Ambiente: " . config('app.env'), 'info');

    echo "\nSistema pronto para uso!\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    logStep("ERRO FATAL: " . $e->getMessage(), 'error');
    logStep("Arquivo: " . $e->getFile() . ":" . $e->getLine(), 'error');

    if (method_exists($e, 'getTraceAsString')) {
        logStep("Stack trace:", 'debug');
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}