<?php

/**
 * Versão Melhorada do Método de Gestão de Credenciais
 *
 * Substitui o código original da linha 784-821 do laravel-complete-example.php
 * com uma implementação mais robusta e automatizada.
 */

/**
 * Gestão de credenciais de tenant melhorada
 */
function improvedTenantCredentialsManagement($sdk, $tenantId, $config = [])
{
    try {
        logStep("🔑 Iniciando gestão avançada de credenciais...", 'info');

        if (!$tenantId || $tenantId === 'unknown') {
            logStep("Tenant ID inválido, pulando gestão de credenciais", 'warning');
            return null;
        }

        // Configurações padrão
        $defaultConfig = [
            'auto_rotate' => true,
            'max_key_age_days' => 90,
            'grace_period_hours' => 24,
            'force_rotation' => false,
            'create_if_missing' => true,
            'ip_whitelist' => null,
            'allowed_origins' => ['*']
        ];

        $options = array_merge($defaultConfig, $config);

        // Usar o novo método do SDK se disponível
        if (method_exists($sdk->superAdmin(), 'ensureTenantCredentials')) {
            logStep("Usando gestão automatizada de credenciais...", 'info');

            $credentials = $sdk->superAdmin()->ensureTenantCredentials($tenantId, $options);

            if ($credentials) {
                $status = '';
                if ($credentials['is_new'] ?? false) {
                    $status = '🆕 Nova chave criada';
                } elseif ($credentials['is_rotated'] ?? false) {
                    $status = '🔄 Chave rotacionada';
                } else {
                    $status = '✅ Chave existente válida';
                }

                logStep("Credenciais obtidas com sucesso! {$status}", 'success');
                logStep("Role: " . ($credentials['role'] ?? 'N/A'), 'info');
                logStep("Idade: " . ($credentials['key_age_days'] ?? 'N/A') . " dias", 'info');

                return $credentials;
            }
        }

        // Fallback para método manual se o automatizado não estiver disponível
        logStep("Método automatizado não disponível, usando abordagem manual...", 'warning');
        return manualCredentialsManagement($sdk, $tenantId, $options);

    } catch (Exception $e) {
        logStep("Erro na gestão de credenciais: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Gestão manual de credenciais (fallback)
 */
function manualCredentialsManagement($sdk, $tenantId, $options)
{
    try {
        // 1. Tentar buscar credenciais existentes
        $existingCredentials = null;

        if (method_exists($sdk->superAdmin(), 'getTenantApiCredentials')) {
            $existingCredentials = $sdk->superAdmin()->getTenantApiCredentials($tenantId);
        } elseif (method_exists($sdk->superAdmin(), 'getTenantCredentials')) {
            // Fallback para método existente
            $tenantInfo = $sdk->superAdmin()->getTenantCredentials($tenantId);
            if (isset($tenantInfo['api_key'])) {
                $existingCredentials = $tenantInfo;
            }
        }

        if ($existingCredentials) {
            logStep("Credenciais existentes encontradas", 'success');

            $keyAge = $existingCredentials['key_age_days'] ?? 0;
            $maxAge = $options['max_key_age_days'] ?? 90;

            // Verificar se precisa rotacionar
            if (is_numeric($keyAge) && $keyAge > $maxAge) {
                logStep("Chave antiga detectada ({$keyAge} dias, máximo: {$maxAge})", 'warning');

                if ($options['auto_rotate'] && method_exists($sdk->superAdmin(), 'rotateApiKey')) {
                    logStep("Rotacionando chave automaticamente...", 'info');

                    $rotationResult = $sdk->superAdmin()->rotateApiKey(
                        $existingCredentials['api_key_id'],
                        [
                            'gracePeriodHours' => $options['grace_period_hours'] ?? 24,
                            'forceRotation' => $options['force_rotation'] ?? false
                        ]
                    );

                    if ($rotationResult) {
                        logStep("Chave rotacionada com sucesso! 🔄", 'success');
                        return array_merge($existingCredentials, [
                            'is_rotated' => true,
                            'key_age_days' => 0
                        ]);
                    }
                }
            }

            return $existingCredentials;
        }

        // 2. Criar nova chave se não existir e configurado para tal
        if ($options['create_if_missing']) {
            logStep("Nenhuma credencial encontrada. Criando nova chave de API...", 'warning');

            if (method_exists($sdk->superAdmin(), 'createTenantApiKey')) {
                $newCredentials = $sdk->superAdmin()->createTenantApiKey($tenantId, $options);

                if ($newCredentials) {
                    logStep("Nova chave de API criada com sucesso! 🆕", 'success');
                    return array_merge($newCredentials, ['is_new' => true]);
                }
            }

            logStep("Não foi possível criar nova chave de API", 'error');
        }

        return null;

    } catch (Exception $e) {
        logStep("Erro na gestão manual de credenciais: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Validação de credenciais
 */
function validateCredentials($credentials)
{
    if (!$credentials) {
        return false;
    }

    $requiredFields = ['api_key', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($credentials[$field])) {
            logStep("Campo obrigatório ausente: {$field}", 'error');
            return false;
        }
    }

    // Verificar se o role é adequado
    if (($credentials['role'] ?? '') !== 'tenant_admin') {
        logStep("Role inadequado: " . ($credentials['role'] ?? 'N/A'), 'warning');
    }

    return true;
}

/**
 * Exibir resumo das credenciais
 */
function displayCredentialsSummary($credentials)
{
    if (!$credentials) {
        logStep("Nenhuma credencial para exibir", 'warning');
        return;
    }

    logStep("📋 Resumo das Credenciais:", 'info');
    logStep("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
    logStep("API Key ID: " . ($credentials['api_key_id'] ?? 'N/A'), 'info');
    logStep("Role: " . ($credentials['role'] ?? 'N/A'), 'info');
    logStep("Status: " . getCredentialStatus($credentials), 'info');
    logStep("Idade: " . ($credentials['key_age_days'] ?? 'N/A') . " dias", 'info');
    logStep("Criada em: " . ($credentials['created_at'] ?? 'N/A'), 'info');

    if (isset($credentials['api_key'])) {
        $maskedKey = substr($credentials['api_key'], 0, 12) . '...';
        logStep("🔑 API Key: {$maskedKey}", 'info');
    }

    // Exibir permissões se disponíveis
    if (!empty($credentials['permissions'])) {
        $permissionCount = count($credentials['permissions']);
        logStep("🔐 Permissões: {$permissionCount} módulos", 'info');
    }

    logStep("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
}

/**
 * Obter status da credencial
 */
function getCredentialStatus($credentials)
{
    if ($credentials['is_new'] ?? false) {
        return '🆕 Nova';
    }
    if ($credentials['is_rotated'] ?? false) {
        return '🔄 Rotacionada';
    }

    $keyAge = $credentials['key_age_days'] ?? 0;
    if ($keyAge > 90) {
        return '⚠️ Antiga';
    }
    if ($keyAge > 30) {
        return '⏰ Madura';
    }

    return '✅ Válida';
}

// ==========================================
// CÓDIGO DE SUBSTITUIÇÃO
// ==========================================

/**
 * Este código substitui as linhas 784-821 do laravel-complete-example.php
 */
function replacementCode($sdk, $tenantId)
{
    // Configuração personalizada
    $credentialConfig = [
        'auto_rotate' => config('app.example_enable_key_rotation', true),
        'max_key_age_days' => config('app.max_api_key_age_days', 90),
        'grace_period_hours' => config('app.key_rotation_grace_period', 24),
        'force_rotation' => config('app.force_key_rotation', false),
        'create_if_missing' => config('app.auto_create_api_keys', true)
    ];

    // Executar gestão de credenciais melhorada
    $credentials = improvedTenantCredentialsManagement($sdk, $tenantId, $credentialConfig);

    // Validar e exibir resultados
    if (validateCredentials($credentials)) {
        displayCredentialsSummary($credentials);

        // Retornar para uso posterior
        return $credentials;
    } else {
        logStep("Falha na validação das credenciais", 'error');
        return null;
    }
}

/**
 * Função de log compatível
 */
function logStep($message, $level = 'info')
{
    $timestamp = date('Y-m-d H:i:s');
    $emoji = match($level) {
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'info' => 'ℹ️',
        default => '📝'
    };

    echo "[$timestamp] $emoji $message\n";
}

// ==========================================
// EXEMPLO DE USO
// ==========================================

if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    // Simulação de uso
    echo "🚀 Exemplo de Gestão Melhorada de Credenciais\n\n";

    // Mock do SDK para demonstração
    $mockSdk = new class {
        public function superAdmin() {
            return new class {
                public function ensureTenantCredentials($tenantId, $options) {
                    // Simulação de resposta
                    return [
                        'api_key' => 'sk_test_' . bin2hex(random_bytes(16)),
                        'api_key_id' => 'key_' . bin2hex(random_bytes(8)),
                        'secret_key' => bin2hex(random_bytes(32)),
                        'hash_key' => hash('sha256', 'test'),
                        'role' => 'tenant_admin',
                        'permissions' => ['tenants' => ['read', 'write'], 'users' => ['read', 'write']],
                        'created_at' => date('Y-m-d H:i:s'),
                        'key_age_days' => rand(0, 120),
                        'status' => 'active',
                        'is_new' => rand(0, 1) === 1
                    ];
                }
            };
        }
    };

    $testTenantId = 'tenant_' . bin2hex(random_bytes(8));

    echo "Testando com Tenant ID: $testTenantId\n\n";

    $result = replacementCode($mockSdk, $testTenantId);

    echo "\n✅ Teste concluído!\n";
    echo "Resultado: " . ($result ? "Sucesso" : "Falha") . "\n";
}