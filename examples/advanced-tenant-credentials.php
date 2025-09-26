<?php

declare(strict_types=1);

/**
 * Gestão Avançada de Credenciais de Tenant
 *
 * Este exemplo demonstra como:
 * - Buscar credenciais existentes de um tenant
 * - Criar automaticamente chaves de API se não existirem
 * - Configurar permissões completas com role tenant_admin
 * - Gerenciar rotação de chaves antigas
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\SDKException;

/**
 * Gestão completa de credenciais para um tenant
 */
function ensureTenantCredentials(ClubifyCheckoutSDK $sdk, string $tenantId, array $options = []): array
{
    $logger = function($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $emoji = match($level) {
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'info' => 'ℹ️',
            default => '📝'
        };
        echo "[$timestamp] $emoji $message\n";
    };

    try {
        $logger("Iniciando gestão de credenciais para tenant: $tenantId", 'info');

        // 1. Buscar credenciais existentes
        $existingCredentials = getExistingCredentials($sdk, $tenantId, $logger);

        if ($existingCredentials) {
            $logger("Credenciais existentes encontradas", 'success');

            // Verificar se precisa rotacionar
            $needsRotation = checkCredentialsRotation($existingCredentials, $options, $logger);

            if ($needsRotation && ($options['auto_rotate'] ?? false)) {
                return rotateCredentials($sdk, $tenantId, $existingCredentials, $options, $logger);
            }

            return $existingCredentials;
        }

        // 2. Criar novas credenciais se não existirem
        $logger("Nenhuma credencial encontrada. Criando nova chave de API...", 'warning');
        return createTenantApiKey($sdk, $tenantId, $options, $logger);

    } catch (Exception $e) {
        $logger("Erro na gestão de credenciais: " . $e->getMessage(), 'error');
        throw new SDKException("Failed to ensure tenant credentials: " . $e->getMessage(), 0, $e);
    }
}

/**
 * Busca credenciais existentes do tenant
 */
function getExistingCredentials(ClubifyCheckoutSDK $sdk, string $tenantId, callable $logger): ?array
{
    try {
        // Tentar buscar através do método específico
        if (method_exists($sdk->superAdmin(), 'getTenantCredentials')) {
            $credentials = $sdk->superAdmin()->getTenantCredentials($tenantId);

            if ($credentials && isset($credentials['api_key'])) {
                $logger("Credenciais encontradas via getTenantCredentials", 'info');
                return $credentials;
            }
        }

        // Fallback: buscar através da listagem de chaves
        if (method_exists($sdk->superAdmin(), 'listApiKeys')) {
            $allKeys = $sdk->superAdmin()->listApiKeys([
                'tenant_id' => $tenantId,
                'status' => 'active',
                'role' => 'tenant_admin'
            ]);

            if (!empty($allKeys['api_keys'])) {
                $primaryKey = $allKeys['api_keys'][0]; // Primeira chave ativa
                $logger("Credenciais encontradas via listApiKeys", 'info');

                return [
                    'api_key' => $primaryKey['key'] ?? null,
                    'api_key_id' => $primaryKey['id'] ?? null,
                    'secret_key' => $primaryKey['secret'] ?? null,
                    'hash_key' => $primaryKey['hash'] ?? null,
                    'role' => $primaryKey['role'] ?? 'tenant_admin',
                    'permissions' => $primaryKey['permissions'] ?? [],
                    'created_at' => $primaryKey['created_at'] ?? null,
                    'key_age_days' => calculateKeyAge($primaryKey['created_at'] ?? null)
                ];
            }
        }

        return null;

    } catch (Exception $e) {
        $logger("Erro ao buscar credenciais existentes: " . $e->getMessage(), 'warning');
        return null;
    }
}

/**
 * Cria uma nova chave de API para o tenant com permissões completas
 */
function createTenantApiKey(ClubifyCheckoutSDK $sdk, string $tenantId, array $options, callable $logger): array
{
    try {
        $logger("Criando nova chave de API para tenant...", 'info');

        // Configuração padrão para chave de tenant admin
        $keyConfig = array_merge([
            'name' => "Tenant Admin Key - " . date('Y-m-d H:i:s'),
            'description' => 'Auto-generated tenant admin key with full permissions',
            'role' => 'tenant_admin',
            'tenant_id' => $tenantId,
            'permissions' => [
                // Permissões completas para todos os serviços
                'tenants' => ['read', 'write', 'delete'],
                'users' => ['read', 'write', 'delete'],
                'orders' => ['read', 'write', 'cancel', 'refund'],
                'products' => ['read', 'write', 'delete', 'publish'],
                'payments' => ['process', 'refund', 'view', 'export'],
                'analytics' => ['view', 'export', 'configure'],
                'webhooks' => ['read', 'write', 'delete', 'test'],
                'api_keys' => ['read', 'write', 'rotate'],
                'settings' => ['read', 'write', 'configure']
            ],
            'scopes' => [
                'tenant:admin',
                'api:full',
                'webhooks:manage',
                'analytics:read',
                'payments:process'
            ],
            'rate_limits' => [
                'requests_per_minute' => 1000,
                'requests_per_hour' => 50000,
                'requests_per_day' => 1000000
            ],
            'expires_at' => null, // Sem expiração
            'ip_whitelist' => $options['ip_whitelist'] ?? null,
            'allowed_origins' => $options['allowed_origins'] ?? ['*']
        ], $options['key_config'] ?? []);

        // Criar a chave via SDK
        if (method_exists($sdk->superAdmin(), 'createApiKey')) {
            $result = $sdk->superAdmin()->createApiKey($keyConfig);

            if ($result['success'] ?? false) {
                $logger("Chave de API criada com sucesso!", 'success');
                $logger("ID da chave: " . ($result['key_id'] ?? 'N/A'), 'info');

                return [
                    'api_key' => $result['api_key'] ?? null,
                    'api_key_id' => $result['key_id'] ?? null,
                    'secret_key' => $result['secret_key'] ?? null,
                    'hash_key' => $result['hash_key'] ?? null,
                    'role' => 'tenant_admin',
                    'permissions' => $keyConfig['permissions'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'key_age_days' => 0,
                    'is_new' => true
                ];
            }
        }

        throw new SDKException('Failed to create API key via SDK');

    } catch (Exception $e) {
        $logger("Erro ao criar chave de API: " . $e->getMessage(), 'error');
        throw $e;
    }
}

/**
 * Verifica se as credenciais precisam ser rotacionadas
 */
function checkCredentialsRotation(array $credentials, array $options, callable $logger): bool
{
    $maxAge = $options['max_key_age_days'] ?? 90;
    $keyAge = $credentials['key_age_days'] ?? 0;

    if (is_numeric($keyAge) && $keyAge > $maxAge) {
        $logger("Chave antiga detectada: $keyAge dias (máximo: $maxAge)", 'warning');
        return true;
    }

    return false;
}

/**
 * Rotaciona as credenciais do tenant
 */
function rotateCredentials(ClubifyCheckoutSDK $sdk, string $tenantId, array $oldCredentials, array $options, callable $logger): array
{
    try {
        $logger("Iniciando rotação de credenciais...", 'info');

        $gracePeriodHours = $options['grace_period_hours'] ?? 24;

        if (method_exists($sdk->superAdmin(), 'rotateApiKey')) {
            $rotationResult = $sdk->superAdmin()->rotateApiKey($oldCredentials['api_key_id'], [
                'gracePeriodHours' => $gracePeriodHours,
                'forceRotation' => $options['force_rotation'] ?? false,
                'preserve_permissions' => true
            ]);

            if ($rotationResult['success'] ?? false) {
                $logger("Credenciais rotacionadas com sucesso!", 'success');
                $logger("Período de graça: $gracePeriodHours horas", 'info');

                return array_merge($oldCredentials, [
                    'api_key' => $rotationResult['new_api_key'] ?? $oldCredentials['api_key'],
                    'secret_key' => $rotationResult['new_secret_key'] ?? $oldCredentials['secret_key'],
                    'hash_key' => $rotationResult['new_hash_key'] ?? $oldCredentials['hash_key'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'key_age_days' => 0,
                    'is_rotated' => true,
                    'old_key_expires_at' => $rotationResult['old_key_expires_at'] ?? null
                ]);
            }
        }

        // Fallback: criar nova chave e revogar a antiga
        $logger("Rotação direta não disponível. Criando nova chave...", 'warning');
        $newCredentials = createTenantApiKey($sdk, $tenantId, $options, $logger);

        // Tentar revogar a chave antiga após período de graça
        if (method_exists($sdk->superAdmin(), 'scheduleKeyRevocation')) {
            $sdk->superAdmin()->scheduleKeyRevocation($oldCredentials['api_key_id'], [
                'delay_hours' => $gracePeriodHours
            ]);
            $logger("Revogação da chave antiga agendada para $gracePeriodHours horas", 'info');
        }

        return $newCredentials;

    } catch (Exception $e) {
        $logger("Erro na rotação de credenciais: " . $e->getMessage(), 'error');
        throw $e;
    }
}

/**
 * Calcula a idade da chave em dias
 */
function calculateKeyAge(?string $createdAt): int
{
    if (!$createdAt) {
        return 0;
    }

    try {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        return (int) $created->diff($now)->days;
    } catch (Exception $e) {
        return 0;
    }
}

// ==========================================
// EXEMPLO DE USO
// ==========================================

if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        // Configurar SDK
        $config = [
            'api_key' => getenv('CLUBIFY_API_KEY') ?: 'your-super-admin-key',
            'base_url' => getenv('CLUBIFY_BASE_URL') ?: 'https://checkout.svelve.com',
            'environment' => 'production'
        ];

        $sdk = new ClubifyCheckoutSDK($config);

        // ID do tenant (normalmente obtido de contexto)
        $tenantId = $argv[1] ?? 'your-tenant-id';

        if ($tenantId === 'your-tenant-id') {
            echo "❌ Uso: php " . basename(__FILE__) . " <tenant-id>\n";
            exit(1);
        }

        // Opções de configuração
        $options = [
            'auto_rotate' => true,
            'max_key_age_days' => 90,
            'grace_period_hours' => 24,
            'force_rotation' => false,
            'ip_whitelist' => null, // ['192.168.1.0/24', '10.0.0.0/8']
            'allowed_origins' => ['*'],
            'key_config' => [
                'name' => "Auto-Generated Tenant Admin Key",
                'description' => "Full-access key for tenant operations"
            ]
        ];

        // Executar gestão de credenciais
        echo "🚀 Iniciando gestão de credenciais avançada...\n\n";

        $credentials = ensureTenantCredentials($sdk, $tenantId, $options);

        echo "\n📋 Resumo das Credenciais:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "API Key ID: " . ($credentials['api_key_id'] ?? 'N/A') . "\n";
        echo "Role: " . ($credentials['role'] ?? 'N/A') . "\n";
        echo "Idade: " . ($credentials['key_age_days'] ?? 'N/A') . " dias\n";
        echo "Status: " . (($credentials['is_new'] ?? false) ? '🆕 Nova' :
                         (($credentials['is_rotated'] ?? false) ? '🔄 Rotacionada' : '✅ Existente')) . "\n";
        echo "Criada em: " . ($credentials['created_at'] ?? 'N/A') . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        if (isset($credentials['api_key'])) {
            echo "🔑 API Key: " . substr($credentials['api_key'], 0, 12) . "...\n";
        }

        echo "\n✅ Gestão de credenciais concluída com sucesso!\n";

    } catch (Exception $e) {
        echo "\n❌ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}