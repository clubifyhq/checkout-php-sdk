<?php

/**
 * Enhanced Super Admin Example with Conflict Resolution
 *
 * Este exemplo demonstra o uso do SDK com resolução automática de conflitos 409
 * e padrões idempotentes para operações de criação de recursos.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\ConflictException;
use Clubify\Checkout\Services\ConflictResolverService;

// Configuração do exemplo
$EXAMPLE_CONFIG = [
    'credentials' => [
        'super_admin_tenant_id' => 'SUPER_ADMIN',
        'super_admin_api_key' => 'sk_test_super_admin_key_123',
        'base_url' => 'https://checkout.svelve.com/api/v1'
    ],
    'organization' => [
        'name' => 'Nova Empresa Teste',
        'admin_email' => 'admin@nova-empresa.com',
        'admin_name' => 'Administrador Teste',
        'subdomain' => 'nova-empresa-teste',
        'custom_domain' => 'checkout.nova-empresa.com'
    ]
];

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
    echo "[{$timestamp}] {$icon} {$message}\n";
}

function generateIdempotencyKey(string $operation, array $data): string
{
    $identifier = $data['email'] ?? $data['subdomain'] ?? $data['name'] ?? uniqid();
    return $operation . '_' . md5($identifier . date('Y-m-d'));
}

try {
    logStep("Iniciando exemplo avançado com resolução de conflitos", 'info');

    // ===============================================
    // 1. INICIALIZAÇÃO DO SDK COM SUPER ADMIN
    // ===============================================

    $config = [
        'credentials' => $EXAMPLE_CONFIG['credentials'],
        'cache' => [
            'adapter' => 'array',
            'ttl' => 3600
        ],
        'logging' => [
            'level' => 'info',
            'channels' => ['file']
        ],
        'retry' => [
            'max_attempts' => 3,
            'delay' => 1000,
            'backoff' => 'exponential'
        ],
        // Habilita resolução automática de conflitos
        'conflict_resolution' => [
            'auto_resolve' => true,
            'strategy' => 'retrieve_existing'
        ]
    ];

    logStep("Inicializando SDK com credenciais de super admin", 'info');
    $sdk = new ClubifyCheckoutSDK($config);
    $sdk->initializeAsSuperAdmin(
        $EXAMPLE_CONFIG['credentials']['super_admin_tenant_id'],
        $EXAMPLE_CONFIG['credentials']['super_admin_api_key']
    );

    logStep("SDK inicializado com sucesso", 'success');

    // ===============================================
    // 2. CRIAÇÃO IDEMPOTENTE DE ORGANIZAÇÃO
    // ===============================================

    logStep("=== Criação de Organização com Resolução de Conflitos ===", 'info');

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
        ]
    ];

    // Gerar chave de idempotência para a organização
    $orgIdempotencyKey = generateIdempotencyKey('create_organization', $organizationData);

    logStep("Verificando se organização já existe antes da criação", 'debug');

    try {
        // Usar novo método com resolução automática de conflitos
        $organization = $sdk->organization()->createIdempotent($organizationData, $orgIdempotencyKey);

        logStep("Organização criada/obtida com sucesso: " . $organization['name'], 'success');
        logStep("Tenant ID: " . $organization['tenant_id'], 'info');

        $tenantId = $organization['tenant_id'];

    } catch (ConflictException $e) {
        logStep("Conflito detectado: " . $e->getMessage(), 'warning');
        logStep("Tipo de conflito: " . $e->getConflictType(), 'debug');

        // Mostrar sugestões de resolução
        foreach ($e->getResolutionSuggestions() as $suggestion) {
            logStep("💡 Sugestão: " . $suggestion, 'info');
        }

        // Tentar resolução automática se disponível
        if ($e->isAutoResolvable()) {
            logStep("Tentando resolução automática...", 'info');

            $resolver = new ConflictResolverService($sdk->getHttpClient(), $sdk->getLogger());
            $organization = $resolver->resolve($e);

            logStep("Conflito resolvido automaticamente", 'success');
            $tenantId = $organization['tenant_id'];
        } else {
            throw $e;
        }
    }

    // ===============================================
    // 3. TROCA DE CONTEXTO PARA O TENANT
    // ===============================================

    logStep("Trocando contexto para o tenant criado", 'info');

    $switchResult = $sdk->switchToTenant($tenantId, [
        'admin_email' => $EXAMPLE_CONFIG['organization']['admin_email'],
        'admin_name' => $EXAMPLE_CONFIG['organization']['admin_name']
    ]);

    if (!$switchResult['success']) {
        throw new \Exception("Falha na troca de contexto: " . $switchResult['message']);
    }

    logStep("Contexto alterado com sucesso", 'success');

    // ===============================================
    // 4. CRIAÇÃO IDEMPOTENTE DE USUÁRIO ADMIN
    // ===============================================

    logStep("=== Criação de Usuário Admin com Resolução de Conflitos ===", 'info');

    $adminUserData = [
        'email' => $EXAMPLE_CONFIG['organization']['admin_email'],
        'name' => $EXAMPLE_CONFIG['organization']['admin_name'],
        'role' => 'tenant_admin',
        'permissions' => [
            'manage_settings',
            'manage_users',
            'manage_products',
            'manage_offers',
            'view_analytics'
        ]
    ];

    $userIdempotencyKey = generateIdempotencyKey('create_admin_user', $adminUserData);

    try {
        logStep("Verificando se usuário admin já existe", 'debug');

        // Usar método melhorado com check-before-create
        $adminUser = $sdk->userManagement()->createUserIdempotent(
            $adminUserData,
            $userIdempotencyKey,
            true // autoResolveConflicts
        );

        logStep("Usuário admin criado/obtido com sucesso: " . $adminUser['email'], 'success');
        logStep("User ID: " . $adminUser['id'], 'info');

    } catch (ConflictException $e) {
        logStep("Conflito de usuário detectado: " . $e->getMessage(), 'warning');

        // Verificar se o usuário já existe e pode ser recuperado
        if ($e->getConflictType() === 'email_exists' && $e->getExistingResourceId()) {
            logStep("Recuperando usuário existente: ID " . $e->getExistingResourceId(), 'info');

            $adminUser = $sdk->userManagement()->getUserById($e->getExistingResourceId());
            logStep("Usuário recuperado com sucesso", 'success');
        } else {
            throw $e;
        }
    }

    // ===============================================
    // 5. CRIAÇÃO IDEMPOTENTE DE API KEY
    // ===============================================

    logStep("=== Criação de API Key com Verificação ===", 'info');

    try {
        // Verificar se já existe uma API key ativa
        $existingKeys = $sdk->userManagement()->listApiKeys([
            'user_id' => $adminUser['id'],
            'status' => 'active'
        ]);

        if (!empty($existingKeys['data'])) {
            logStep("API Key ativa já existe, utilizando existente", 'info');
            $apiKey = $existingKeys['data'][0];
        } else {
            $apiKeyData = [
                'name' => 'Tenant Admin Key',
                'user_id' => $adminUser['id'],
                'permissions' => [
                    'read:users',
                    'write:users',
                    'read:products',
                    'write:products',
                    'read:offers',
                    'write:offers'
                ],
                'expires_in' => 86400 * 365 // 1 ano
            ];

            $keyIdempotencyKey = generateIdempotencyKey('create_api_key', $apiKeyData);
            $apiKey = $sdk->userManagement()->createApiKeyIdempotent($apiKeyData, $keyIdempotencyKey);

            logStep("Nova API Key criada com sucesso", 'success');
        }

        logStep("API Key ID: " . $apiKey['id'], 'info');

    } catch (\Exception $e) {
        logStep("Erro na criação de API Key: " . $e->getMessage(), 'error');
        throw $e;
    }

    // ===============================================
    // 6. CONFIGURAÇÃO DE WEBHOOK COM VALIDAÇÃO
    // ===============================================

    logStep("=== Configuração de Webhooks ===", 'info');

    $webhookData = [
        'url' => 'https://nova-empresa.com/webhooks/clubify',
        'events' => [
            'payment.successful',
            'payment.failed',
            'subscription.created',
            'subscription.cancelled'
        ],
        'secret' => 'webhook_secret_' . uniqid(),
        'active' => true
    ];

    try {
        // Verificar se webhook já existe para a URL
        $existingWebhooks = $sdk->webhooks()->list([
            'url' => $webhookData['url']
        ]);

        if (!empty($existingWebhooks['data'])) {
            logStep("Webhook já configurado para esta URL", 'info');
            $webhook = $existingWebhooks['data'][0];
        } else {
            $webhookIdempotencyKey = generateIdempotencyKey('create_webhook', $webhookData);
            $webhook = $sdk->webhooks()->createIdempotent($webhookData, $webhookIdempotencyKey);

            logStep("Webhook configurado com sucesso", 'success');
        }

        logStep("Webhook ID: " . $webhook['id'], 'info');

    } catch (\Exception $e) {
        logStep("Erro na configuração de webhook: " . $e->getMessage(), 'error');
        // Webhook não é crítico, continuar execução
    }

    // ===============================================
    // 7. RESUMO FINAL
    // ===============================================

    logStep("=== Resumo da Configuração ===", 'success');
    logStep("✅ Organização: " . $organization['name'], 'success');
    logStep("✅ Tenant ID: " . $tenantId, 'success');
    logStep("✅ Admin User: " . $adminUser['email'], 'success');
    logStep("✅ API Key: " . substr($apiKey['key'], 0, 12) . '...', 'success');

    if (isset($webhook)) {
        logStep("✅ Webhook: " . $webhook['url'], 'success');
    }

    logStep("Configuração completa realizada com sucesso!", 'success');
    logStep("Total de operações com resolução automática de conflitos", 'info');

    // Salvar credenciais para uso posterior
    $credentials = [
        'tenant_id' => $tenantId,
        'api_key' => $apiKey['key'],
        'admin_user_id' => $adminUser['id'],
        'organization_name' => $organization['name'],
        'setup_completed_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents(__DIR__ . '/tenant_credentials.json', json_encode($credentials, JSON_PRETTY_PRINT));
    logStep("Credenciais salvas em tenant_credentials.json", 'info');

} catch (\Exception $e) {
    logStep("❌ Erro crítico: " . $e->getMessage(), 'error');

    if ($e instanceof ConflictException) {
        logStep("Detalhes do conflito:", 'error');
        logStep("- Tipo: " . $e->getConflictType(), 'error');
        logStep("- Campos: " . implode(', ', $e->getConflictFields()), 'error');

        if ($e->getCheckEndpoint()) {
            logStep("- Endpoint de verificação: " . $e->getCheckEndpoint(), 'info');
        }

        if ($e->getRetrievalEndpoint()) {
            logStep("- Endpoint de recuperação: " . $e->getRetrievalEndpoint(), 'info');
        }
    }

    exit(1);
}

?>