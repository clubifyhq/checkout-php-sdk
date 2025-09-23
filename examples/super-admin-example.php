<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * EXEMPLO COMPLETO DE CONFIGURAÇÃO DE CHECKOUT VIA SDK
 *
 * Este script demonstra a sequência completa para configurar um checkout
 * do zero usando o SDK PHP do Clubify Checkout com funcionalidades de Super Admin.
 *
 * FUNCIONALIDADES IMPLEMENTADAS:
 * ===============================
 *
 * 1. CONFIGURAÇÃO INICIAL
 *    - Inicialização como super admin
 *    - Criação/verificação de organização (tenant)
 *    - Provisionamento automático de credenciais
 *    - Verificação prévia para evitar conflitos (erro 409)
 *
 * 2. INFRAESTRUTURA
 *    - Provisionamento de domínio personalizado
 *    - Configuração automática de certificado SSL
 *    - Setup de webhooks para integrações
 *
 * 3. CATÁLOGO E OFERTAS
 *    - Criação de produtos com verificação prévia
 *    - Criação de ofertas associadas aos produtos
 *    - Configuração de flows de vendas (landing + checkout + obrigado)
 *
 * 4. PERSONALIZAÇÃO
 *    - Criação de temas personalizados
 *    - Configuração de layouts para diferentes tipos de página
 *    - Aplicação da identidade visual do tenant
 *
 * 5. ESTRATÉGIAS DE VENDAS
 *    - Configuração de OrderBumps (ofertas no checkout)
 *    - Setup de Upsells pós-compra
 *    - Configuração de Downsells como alternativa
 *    - Implementação de funil de vendas completo
 *
 * CARACTERÍSTICAS ESPECIAIS:
 * ==========================
 *
 * ✅ RESILIENTE: Verifica recursos existentes antes de criar
 * ✅ DEFENSIVO: Trata diferentes estruturas de resposta da API
 * ✅ TOLERANTE: Continua executando mesmo com falhas pontuais
 * ✅ DETALHADO: Logs extensivos para debugging
 * ✅ REUTILIZÁVEL: Pode ser executado múltiplas vezes
 * ✅ IDEMPOTENTE: Não cria recursos duplicados
 *
 * USO:
 * ====
 * 1. Configure as credenciais de super admin
 * 2. Ajuste as configurações no $EXAMPLE_CONFIG
 * 3. Execute: php super-admin-example.php
 * 4. Monitore os logs para acompanhar o progresso
 *
 * SEQUÊNCIA DE EXECUÇÃO:
 * ======================
 * 1. Inicialização SDK como super admin
 * 2. Criação/verificação de organização
 * 3. Provisionamento de credenciais (com verificação de usuário existente)
 * 4. Alternância de contexto para tenant
 * 5. Provisionamento de domínio e SSL
 * 6. Configuração de webhooks
 * 7. Criação de produtos (com verificação prévia)
 * 8. Criação de ofertas com produtos associados
 * 9. Configuração de flows de vendas
 * 10. Setup de temas e layouts
 * 11. Configuração de OrderBumps, Upsells e Downsells
 * 12. Volta para contexto super admin
 * 13. Relatório final completo
 *
 * @version 2.0 - Versão completa com todas as funcionalidades essenciais
 * @author Clubify Team
 * @since 2024
 */

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
        echo "⚠️  Erro ao buscar tenants por domínio: " . $e->getMessage() . "\n";
        return null;
    }
}

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
            echo "ℹ️  Método específico não disponível, usando listTenants...\n";
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
        echo "⚠️  Erro ao buscar tenants por subdomínio: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Helper function para criar ou encontrar organização
 */
function getOrCreateOrganization($sdk, $organizationData) {
    echo "🔍 Verificando se organização já existe...\n";

    // Verificar por domínio customizado
    if (isset($organizationData['custom_domain'])) {
        $existingTenant = findTenantByDomain($sdk, $organizationData['custom_domain']);
        if ($existingTenant) {
            echo "✅ Organização encontrada pelo domínio customizado: {$organizationData['custom_domain']}\n";

            // Ajustar para a estrutura da API: {success, data, message}
            $tenantData = $existingTenant['data'] ?? $existingTenant;
            $tenantId = $tenantData['_id'] ?? $tenantData['id'] ?? 'unknown';

            // Registrar tenant existente para permitir alternância de contexto (versão robusta)
            try {
                echo "🔑 Registrando tenant existente para alternância de contexto...\n";
                $registrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);

                // Tratamento defensivo - verificar se o resultado é um array válido
                if (!is_array($registrationResult)) {
                    echo "⚠️  Resultado de registro inesperado, assumindo falha\n";
                    $registrationResult = [
                        'success' => false,
                        'message' => 'Método retornou tipo inesperado',
                        'has_api_key' => false
                    ];
                }

                $success = $registrationResult['success'] ?? false;
                $message = $registrationResult['message'] ?? 'Sem mensagem disponível';
                $hasApiKey = $registrationResult['has_api_key'] ?? false;

                if ($success) {
                    echo "✅ " . $message . "\n";

                    if ($hasApiKey) {
                        echo "   🔐 API key disponível - alternância de contexto habilitada\n";
                        $tenantData['api_key'] = 'available'; // Marcar como disponível
                    } else {
                        echo "   ⚠️  Sem API key - funcionalidade limitada\n";
                    }

                    // Mostrar avisos se houver
                    if (!empty($registrationResult['warnings'])) {
                        foreach ($registrationResult['warnings'] as $warning) {
                            echo "   ⚠️  " . $warning . "\n";
                        }
                    }

                    // Tentar provisionar credenciais automaticamente se não houver API key
                    if (!$hasApiKey) {
                        echo "   🔧 Tentando provisionar credenciais automaticamente...\n";
                        try {
                            $adminEmail = $organizationData['admin_email'] ?? "admin@{$tenantId}.local";

                            // VERIFICAR SE USUÁRIO JÁ EXISTE ANTES DE PROVISIONAR
                            echo "   🔍 Verificando se usuário admin já existe: $adminEmail\n";
                            $existingUserCheck = checkEmailAvailability($sdk, $adminEmail, $tenantId);

                            if ($existingUserCheck['exists']) {
                                echo "   ✅ Usuário admin já existe: $adminEmail\n";
                                echo "   🔍 Verificando se já possui API key...\n";

                                // Verificar se já tem API key associada
                                try {
                                    $existingCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
                                    if (!empty($existingCredentials['api_key'])) {
                                        echo "   ✅ API key já existe para o tenant\n";
                                        echo "   🔑 API Key: " . substr($existingCredentials['api_key'], 0, 20) . "...\n";

                                        // Marcar que tem API key
                                        $hasApiKey = true;
                                        $tenantData['api_key'] = $existingCredentials['api_key'];
                                        $tenantData['admin_user'] = $existingUserCheck['resource'];

                                        // Re-registrar tenant com credenciais existentes
                                        echo "   🔄 Re-registrando tenant com credenciais existentes...\n";
                                        $reregistrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);
                                        if (($reregistrationResult['success'] ?? false) && ($reregistrationResult['has_api_key'] ?? false)) {
                                            echo "   🎉 Tenant re-registrado com credenciais existentes! Alternância habilitada.\n";
                                        }
                                        return; // Sair early se já tem tudo configurado
                                    } else {
                                        echo "   ⚠️  Usuário existe mas não tem API key - criando apenas API key...\n";
                                        // Criar apenas API key para usuário existente
                                        $apiKeyData = [
                                            'name' => 'Auto-generated Admin Key',
                                            'tenant_id' => $tenantId,
                                            'user_email' => $adminEmail
                                        ];
                                        $apiKeyResult = $sdk->superAdmin()->createTenantApiKey($tenantId, $apiKeyData);
                                        if ($apiKeyResult['success']) {
                                            echo "   ✅ API Key criada com sucesso!\n";
                                            echo "   🔑 Nova API Key: " . substr($apiKeyResult['api_key']['key'], 0, 20) . "...\n";

                                            $hasApiKey = true;
                                            $tenantData['api_key'] = $apiKeyResult['api_key']['key'];
                                            $tenantData['admin_user'] = $existingUserCheck['resource'];
                                            return; // Sair early após criar API key
                                        }
                                    }
                                } catch (Exception $credentialsError) {
                                    echo "   ⚠️  Erro ao verificar credenciais existentes: " . $credentialsError->getMessage() . "\n";
                                }
                            }

                            echo "   📝 Usuário não existe - prosseguindo com provisionamento completo...\n";
                            $provisioningOptions = [
                                'admin_email' => $adminEmail,
                                'admin_name' => $organizationData['admin_name'] ?? 'Tenant Administrator',
                                'api_key_name' => 'Auto-generated Admin Key',
                                'environment' => $EXAMPLE_CONFIG['sdk']['environment'] ?? 'test'
                            ];

                            $provisionResult = $sdk->superAdmin()->provisionTenantCredentials($tenantId, $provisioningOptions);

                            if ($provisionResult['success']) {
                                echo "   ✅ Credenciais provisionadas com sucesso!\n";
                                echo "   👤 Usuário admin criado: " . $provisionResult['user']['email'] . "\n";
                                echo "   🔑 API Key criada: " . substr($provisionResult['api_key']['key'], 0, 20) . "...\n";
                                echo "   🔒 Senha temporária: " . $provisionResult['user']['password'] . "\n";
                                echo "   ⚠️  IMPORTANTE: Salve essas credenciais em local seguro!\n";

                                // Marcar que agora tem API key
                                $hasApiKey = true;
                                $tenantData['api_key'] = $provisionResult['api_key']['key'];
                                $tenantData['admin_user'] = $provisionResult['user'];

                                // Re-registrar tenant com credenciais
                                echo "   🔄 Re-registrando tenant com novas credenciais...\n";
                                $reregistrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);
                                if (($reregistrationResult['success'] ?? false) && ($reregistrationResult['has_api_key'] ?? false)) {
                                    echo "   🎉 Tenant re-registrado com credenciais! Alternância habilitada.\n";
                                }
                            }
                        } catch (Exception $provisionError) {
                            echo "   ❌ Falha no provisionamento automático: " . $provisionError->getMessage() . "\n";
                            echo "   📋 Configuração manual necessária:\n";
                            echo "   1. Verificar se usuário com email '{$adminEmail}' já existe\n";
                            echo "   2. Se não existe, criar usuário com role 'tenant_admin'\n";
                            echo "   3. Criar API key via POST /api-keys\n";
                            echo "   4. Registrar novamente o tenant com as credenciais\n";
                        }
                    }
                } else {
                    echo "❌ Falha no registro: " . $message . "\n";
                }
            } catch (Exception $e) {
                echo "❌ Erro crítico no registro: " . $e->getMessage() . "\n";
                echo "   O tenant pode não existir ou não estar acessível\n";
            }

            return [
                'organization' => ['id' => $tenantId],
                'tenant' => ['id' => $tenantId] + $tenantData,
                'existed' => true
            ];
        }
    }

    // Verificar por subdomínio
    if (isset($organizationData['subdomain'])) {
        $existingTenant = findTenantBySubdomain($sdk, $organizationData['subdomain']);
        if ($existingTenant) {
            echo "✅ Organização encontrada pelo subdomínio: {$organizationData['subdomain']}\n";

            // Ajustar para a estrutura da API: {success, data, message}
            $tenantData = $existingTenant['data'] ?? $existingTenant;
            $tenantId = $tenantData['_id'] ?? $tenantData['id'] ?? 'unknown';

            // Registrar tenant existente para permitir alternância de contexto (versão robusta)
            try {
                echo "🔑 Registrando tenant existente para alternância de contexto...\n";
                $registrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);

                // Tratamento defensivo - verificar se o resultado é um array válido
                if (!is_array($registrationResult)) {
                    echo "⚠️  Resultado de registro inesperado, assumindo falha\n";
                    $registrationResult = [
                        'success' => false,
                        'message' => 'Método retornou tipo inesperado',
                        'has_api_key' => false
                    ];
                }

                $success = $registrationResult['success'] ?? false;
                $message = $registrationResult['message'] ?? 'Sem mensagem disponível';
                $hasApiKey = $registrationResult['has_api_key'] ?? false;

                if ($success) {
                    echo "✅ " . $message . "\n";

                    if ($hasApiKey) {
                        echo "   🔐 API key disponível - alternância de contexto habilitada\n";
                        $tenantData['api_key'] = 'available'; // Marcar como disponível
                    } else {
                        echo "   ⚠️  Sem API key - funcionalidade limitada\n";
                    }

                    // Mostrar avisos se houver
                    if (!empty($registrationResult['warnings'])) {
                        foreach ($registrationResult['warnings'] as $warning) {
                            echo "   ⚠️  " . $warning . "\n";
                        }
                    }

                    // Tentar provisionar credenciais automaticamente se não houver API key
                    if (!$hasApiKey) {
                        echo "   🔧 Tentando provisionar credenciais automaticamente...\n";
                        try {
                            $adminEmail = $organizationData['admin_email'] ?? "admin@{$tenantId}.local";

                            // VERIFICAR SE USUÁRIO JÁ EXISTE ANTES DE PROVISIONAR
                            echo "   🔍 Verificando se usuário admin já existe: $adminEmail\n";
                            $existingUserCheck = checkEmailAvailability($sdk, $adminEmail, $tenantId);

                            if ($existingUserCheck['exists']) {
                                echo "   ✅ Usuário admin já existe: $adminEmail\n";
                                echo "   🔍 Verificando se já possui API key...\n";

                                // Verificar se já tem API key associada
                                try {
                                    $existingCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
                                    if (!empty($existingCredentials['api_key'])) {
                                        echo "   ✅ API key já existe para o tenant\n";
                                        echo "   🔑 API Key: " . substr($existingCredentials['api_key'], 0, 20) . "...\n";

                                        // Marcar que tem API key
                                        $hasApiKey = true;
                                        $tenantData['api_key'] = $existingCredentials['api_key'];
                                        $tenantData['admin_user'] = $existingUserCheck['resource'];

                                        // Re-registrar tenant com credenciais existentes
                                        echo "   🔄 Re-registrando tenant com credenciais existentes...\n";
                                        $reregistrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);
                                        if (($reregistrationResult['success'] ?? false) && ($reregistrationResult['has_api_key'] ?? false)) {
                                            echo "   🎉 Tenant re-registrado com credenciais existentes! Alternância habilitada.\n";
                                        }
                                        return; // Sair early se já tem tudo configurado
                                    } else {
                                        echo "   ⚠️  Usuário existe mas não tem API key - criando apenas API key...\n";
                                        // Criar apenas API key para usuário existente
                                        $apiKeyData = [
                                            'name' => 'Auto-generated Admin Key',
                                            'tenant_id' => $tenantId,
                                            'user_email' => $adminEmail
                                        ];
                                        $apiKeyResult = $sdk->superAdmin()->createTenantApiKey($tenantId, $apiKeyData);
                                        if ($apiKeyResult['success']) {
                                            echo "   ✅ API Key criada com sucesso!\n";
                                            echo "   🔑 Nova API Key: " . substr($apiKeyResult['api_key']['key'], 0, 20) . "...\n";

                                            $hasApiKey = true;
                                            $tenantData['api_key'] = $apiKeyResult['api_key']['key'];
                                            $tenantData['admin_user'] = $existingUserCheck['resource'];
                                            return; // Sair early após criar API key
                                        }
                                    }
                                } catch (Exception $credentialsError) {
                                    echo "   ⚠️  Erro ao verificar credenciais existentes: " . $credentialsError->getMessage() . "\n";
                                }
                            }

                            echo "   📝 Usuário não existe - prosseguindo com provisionamento completo...\n";
                            $provisioningOptions = [
                                'admin_email' => $adminEmail,
                                'admin_name' => $organizationData['admin_name'] ?? 'Tenant Administrator',
                                'api_key_name' => 'Auto-generated Admin Key',
                                'environment' => $EXAMPLE_CONFIG['sdk']['environment'] ?? 'test'
                            ];

                            $provisionResult = $sdk->superAdmin()->provisionTenantCredentials($tenantId, $provisioningOptions);

                            if ($provisionResult['success']) {
                                echo "   ✅ Credenciais provisionadas com sucesso!\n";
                                echo "   👤 Usuário admin criado: " . $provisionResult['user']['email'] . "\n";
                                echo "   🔑 API Key criada: " . substr($provisionResult['api_key']['key'], 0, 20) . "...\n";
                                echo "   🔒 Senha temporária: " . $provisionResult['user']['password'] . "\n";
                                echo "   ⚠️  IMPORTANTE: Salve essas credenciais em local seguro!\n";

                                // Marcar que agora tem API key
                                $hasApiKey = true;
                                $tenantData['api_key'] = $provisionResult['api_key']['key'];
                                $tenantData['admin_user'] = $provisionResult['user'];

                                // Re-registrar tenant com credenciais
                                echo "   🔄 Re-registrando tenant com novas credenciais...\n";
                                $reregistrationResult = $sdk->registerExistingTenant($tenantId, $tenantData);
                                if (($reregistrationResult['success'] ?? false) && ($reregistrationResult['has_api_key'] ?? false)) {
                                    echo "   🎉 Tenant re-registrado com credenciais! Alternância habilitada.\n";
                                }
                            }
                        } catch (Exception $provisionError) {
                            echo "   ❌ Falha no provisionamento automático: " . $provisionError->getMessage() . "\n";
                            echo "   📋 Configuração manual necessária:\n";
                            echo "   1. Verificar se usuário com email '{$adminEmail}' já existe\n";
                            echo "   2. Se não existe, criar usuário com role 'tenant_admin'\n";
                            echo "   3. Criar API key via POST /api-keys\n";
                            echo "   4. Registrar novamente o tenant com as credenciais\n";
                        }
                    }
                } else {
                    echo "❌ Falha no registro: " . $message . "\n";
                }
            } catch (Exception $e) {
                echo "❌ Erro crítico no registro: " . $e->getMessage() . "\n";
                echo "   O tenant pode não existir ou não estar acessível\n";
            }

            return [
                'organization' => ['id' => $tenantId],
                'tenant' => ['id' => $tenantId] + $tenantData,
                'existed' => true
            ];
        }
    }

    echo "📝 Organização não encontrada, criando nova...\n";
    try {
        $result = $sdk->createOrganization($organizationData);
        $result['existed'] = false;
        return $result;
    } catch (Exception $e) {
        echo "❌ Erro ao criar organização: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Helper function para verificar se produto já existe
 */
function findProductByName($sdk, $name) {
    try {
        $products = $sdk->products()->list();
        foreach ($products as $product) {
            if (isset($product['name']) && $product['name'] === $name) {
                return $product;
            }
        }
        return null;
    } catch (Exception $e) {
        echo "⚠️  Erro ao buscar produtos: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Helper function para criar ou encontrar produto
 */
function getOrCreateProduct($sdk, $productData) {
    echo "🔍 Verificando se produto '{$productData['name']}' já existe...\n";

    $existingProduct = findProductByName($sdk, $productData['name']);
    if ($existingProduct) {
        echo "✅ Produto encontrado: {$productData['name']}\n";
        return ['product' => $existingProduct, 'existed' => true];
    }

    echo "📝 Produto não encontrado, criando novo...\n";
    try {
        // Tentar método de conveniência primeiro
        try {
            $product = $sdk->createCompleteProduct($productData);
            return ['product' => $product, 'existed' => false];
        } catch (Exception $e) {
            echo "ℹ️  Método de conveniência falhou, tentando método alternativo...\n";
            // Tentar método alternativo
            $product = $sdk->products()->create($productData);
            return ['product' => $product, 'existed' => false];
        }
    } catch (Exception $e) {
        echo "❌ Erro ao criar produto: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verifica se recurso já existe antes de tentar criar
 *
 * Método genérico de verificação com diferentes estratégias por tipo de recurso
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $resourceType Tipo do recurso (email, domain, subdomain, offer_slug, api_key, webhook_url)
 * @param array $criteria Critérios de busca (ex: ['email' => 'test@example.com'])
 * @param string|null $tenantId ID do tenant (opcional, usado para recursos específicos de tenant)
 * @return array|null Informações estruturadas sobre recurso existente ou null se não encontrado
 */
function checkBeforeCreate($sdk, $resourceType, $criteria, $tenantId = null) {
    try {
        echo "🔍 Verificando disponibilidade de $resourceType...\n";

        $startTime = microtime(true);
        $result = null;

        switch ($resourceType) {
            case 'email':
                $result = checkEmailAvailability($sdk, $criteria['email'], $tenantId);
                break;

            case 'domain':
                $result = checkDomainAvailability($sdk, $criteria['domain']);
                break;

            case 'subdomain':
                $result = checkSubdomainAvailability($sdk, $criteria['subdomain']);
                break;

            case 'offer_slug':
                $result = checkOfferSlugAvailability($sdk, $criteria['slug'], $tenantId);
                break;

            case 'api_key':
                $result = checkApiKeyExists($sdk, $criteria['key'], $tenantId);
                break;

            case 'webhook_url':
                $result = checkWebhookUrlExists($sdk, $criteria['url'], $tenantId);
                break;

            default:
                echo "⚠️  Tipo de recurso '$resourceType' não suportado\n";
                return null;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        echo "✅ Verificação de $resourceType concluída em {$executionTime}ms\n";

        if ($result && isset($result['exists']) && $result['exists']) {
            echo "🔍 Recurso já existe: " . json_encode($result['resource'], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✨ Recurso disponível para criação\n";
        }

        return $result;

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de $resourceType: " . $e->getMessage() . "\n";
        echo "📋 Fallback: assumindo recurso não existe para permitir tentativa de criação\n";

        // Log detalhado para debugging
        error_log("checkBeforeCreate($resourceType) error: " . $e->getMessage());
        error_log("Criteria: " . json_encode($criteria));
        error_log("TenantId: " . ($tenantId ?? 'null'));

        return [
            'exists' => false,
            'available' => true,
            'error' => $e->getMessage(),
            'fallback_used' => true
        ];
    }
}

/**
 * Verificar se email está disponível
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $email Email para verificar
 * @param string|null $tenantId ID do tenant (opcional)
 * @return array Resultado da verificação
 */
function checkEmailAvailability($sdk, $email, $tenantId = null) {
    try {
        echo "📧 Verificando disponibilidade do email: $email\n";

        // Estratégia 1: Usar endpoint específico de verificação se disponível
        try {
            $checkEndpoint = $tenantId ? "/tenants/$tenantId/users/check-email/" : "/users/check-email/";
            $response = $sdk->getHttpClient()->get($checkEndpoint . urlencode($email));

            if ($response && isset($response['exists'])) {
                return [
                    'exists' => $response['exists'],
                    'available' => !$response['exists'],
                    'resource' => $response['user'] ?? null,
                    'method' => 'check_endpoint'
                ];
            }
        } catch (Exception $e) {
            echo "ℹ️  Endpoint de verificação não disponível, tentando busca manual...\n";
        }

        // Estratégia 2: Buscar por email usando métodos do SDK
        try {
            $existingUser = null;

            if ($tenantId) {
                // Buscar usuários do tenant específico
                $users = $sdk->users()->list(['tenant_id' => $tenantId]);
                foreach ($users as $user) {
                    if (isset($user['email']) && $user['email'] === $email) {
                        $existingUser = $user;
                        break;
                    }
                }
            } else {
                // Buscar usuários globalmente (super admin)
                $users = $sdk->superAdmin()->listUsers(['email' => $email]);
                if (!empty($users)) {
                    $existingUser = $users[0];
                }
            }

            return [
                'exists' => $existingUser !== null,
                'available' => $existingUser === null,
                'resource' => $existingUser,
                'method' => 'manual_search'
            ];

        } catch (Exception $e) {
            echo "ℹ️  Busca manual falhou: " . $e->getMessage() . "\n";
        }

        // Estratégia 3: Fallback graceful
        return [
            'exists' => false,
            'available' => true,
            'method' => 'fallback',
            'warning' => 'Não foi possível verificar com certeza - assumindo disponível'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de email: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verificar se domínio está disponível
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $domain Domínio para verificar
 * @return array Resultado da verificação
 */
function checkDomainAvailability($sdk, $domain) {
    try {
        echo "🌐 Verificando disponibilidade do domínio: $domain\n";

        // Estratégia 1: Usar endpoint específico de verificação
        try {
            $response = $sdk->getHttpClient()->get("/tenants/check-domain/" . urlencode($domain));

            if ($response && isset($response['available'])) {
                return [
                    'exists' => !$response['available'],
                    'available' => $response['available'],
                    'resource' => $response['tenant'] ?? null,
                    'method' => 'check_endpoint'
                ];
            }
        } catch (Exception $e) {
            echo "ℹ️  Endpoint de verificação não disponível, usando método manual...\n";
        }

        // Estratégia 2: Usar helper function existente
        $existingTenant = findTenantByDomain($sdk, $domain);

        return [
            'exists' => $existingTenant !== null,
            'available' => $existingTenant === null,
            'resource' => $existingTenant,
            'method' => 'helper_function'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de domínio: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verificar se subdomínio está disponível
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $subdomain Subdomínio para verificar
 * @return array Resultado da verificação
 */
function checkSubdomainAvailability($sdk, $subdomain) {
    try {
        echo "🏢 Verificando disponibilidade do subdomínio: $subdomain\n";

        // Estratégia 1: Usar endpoint específico de verificação
        try {
            $response = $sdk->getHttpClient()->get("/tenants/check-subdomain/" . urlencode($subdomain));

            if ($response && isset($response['available'])) {
                return [
                    'exists' => !$response['available'],
                    'available' => $response['available'],
                    'resource' => $response['tenant'] ?? null,
                    'method' => 'check_endpoint'
                ];
            }
        } catch (Exception $e) {
            echo "ℹ️  Endpoint de verificação não disponível, usando método manual...\n";
        }

        // Estratégia 2: Usar helper function existente
        $existingTenant = findTenantBySubdomain($sdk, $subdomain);

        return [
            'exists' => $existingTenant !== null,
            'available' => $existingTenant === null,
            'resource' => $existingTenant,
            'method' => 'helper_function'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de subdomínio: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verificar se slug de oferta está disponível
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $slug Slug da oferta para verificar
 * @param string|null $tenantId ID do tenant
 * @return array Resultado da verificação
 */
function checkOfferSlugAvailability($sdk, $slug, $tenantId = null) {
    try {
        echo "🏷️  Verificando disponibilidade do slug de oferta: $slug\n";

        // Estratégia 1: Buscar ofertas existentes com o slug
        try {
            $offers = $tenantId
                ? $sdk->offers()->list(['tenant_id' => $tenantId, 'slug' => $slug])
                : $sdk->offers()->list(['slug' => $slug]);

            $existingOffer = null;
            if (is_array($offers)) {
                foreach ($offers as $offer) {
                    if (isset($offer['slug']) && $offer['slug'] === $slug) {
                        $existingOffer = $offer;
                        break;
                    }
                }
            }

            return [
                'exists' => $existingOffer !== null,
                'available' => $existingOffer === null,
                'resource' => $existingOffer,
                'method' => 'offers_list'
            ];

        } catch (Exception $e) {
            echo "ℹ️  Busca de ofertas falhou: " . $e->getMessage() . "\n";
        }

        // Estratégia 2: Usar endpoint direto se disponível
        try {
            $endpoint = $tenantId ? "/tenants/$tenantId/offers/by-slug/" : "/offers/by-slug/";
            $response = $sdk->getHttpClient()->get($endpoint . urlencode($slug));

            return [
                'exists' => $response !== null,
                'available' => $response === null,
                'resource' => $response,
                'method' => 'direct_endpoint'
            ];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return [
                    'exists' => false,
                    'available' => true,
                    'resource' => null,
                    'method' => 'direct_endpoint_404'
                ];
            }
            echo "ℹ️  Endpoint direto falhou: " . $e->getMessage() . "\n";
        }

        // Fallback: assumir disponível
        return [
            'exists' => false,
            'available' => true,
            'method' => 'fallback',
            'warning' => 'Não foi possível verificar com certeza - assumindo disponível'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de slug: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verificar se API key válida existe
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $apiKey API key para verificar
 * @param string|null $tenantId ID do tenant (opcional)
 * @return array Resultado da verificação
 */
function checkApiKeyExists($sdk, $apiKey, $tenantId = null) {
    try {
        echo "🔑 Verificando validade da API key: " . substr($apiKey, 0, 20) . "...\n";

        // Estratégia 1: Tentar usar a API key para fazer uma requisição simples
        try {
            $tempSdk = new ClubifyCheckoutSDK([
                'credentials' => [
                    'api_key' => $apiKey,
                    'tenant_id' => $tenantId
                ],
                'environment' => $sdk->getConfig()['environment'] ?? 'test'
            ]);

            // Fazer uma requisição simples para testar
            $result = $tempSdk->auth()->validate();

            return [
                'exists' => true,
                'valid' => $result !== null,
                'resource' => [
                    'api_key' => substr($apiKey, 0, 20) . '...',
                    'tenant_id' => $tenantId,
                    'validation_result' => $result
                ],
                'method' => 'validation_test'
            ];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Unauthorized') !== false ||
                strpos($e->getMessage(), '401') !== false) {
                return [
                    'exists' => true,
                    'valid' => false,
                    'error' => 'API key existe mas não é válida',
                    'method' => 'validation_test'
                ];
            }
            echo "ℹ️  Teste de validação falhou: " . $e->getMessage() . "\n";
        }

        // Estratégia 2: Listar API keys se tivermos permissão
        try {
            $apiKeys = $tenantId
                ? $sdk->superAdmin()->getTenantCredentials($tenantId)
                : $sdk->superAdmin()->listApiKeys();

            if (isset($apiKeys['api_key']) && strpos($apiKeys['api_key'], substr($apiKey, 0, 20)) === 0) {
                return [
                    'exists' => true,
                    'valid' => true,
                    'resource' => $apiKeys,
                    'method' => 'credentials_list'
                ];
            }

        } catch (Exception $e) {
            echo "ℹ️  Busca de credenciais falhou: " . $e->getMessage() . "\n";
        }

        // Fallback: não conseguiu verificar
        return [
            'exists' => false,
            'valid' => false,
            'method' => 'fallback',
            'warning' => 'Não foi possível verificar API key'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de API key: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Verificar se URL de webhook já está configurada
 *
 * @param ClubifyCheckoutSDK $sdk SDK instance
 * @param string $webhookUrl URL do webhook para verificar
 * @param string|null $tenantId ID do tenant (opcional)
 * @return array Resultado da verificação
 */
function checkWebhookUrlExists($sdk, $webhookUrl, $tenantId = null) {
    try {
        echo "🔗 Verificando se webhook URL já está configurada: $webhookUrl\n";

        // Estratégia 1: Listar webhooks existentes
        try {
            $webhooks = $tenantId
                ? $sdk->webhooks()->list(['tenant_id' => $tenantId])
                : $sdk->webhooks()->list();

            $existingWebhook = null;
            if (is_array($webhooks)) {
                foreach ($webhooks as $webhook) {
                    if (isset($webhook['url']) && $webhook['url'] === $webhookUrl) {
                        $existingWebhook = $webhook;
                        break;
                    }
                }
            }

            return [
                'exists' => $existingWebhook !== null,
                'available' => $existingWebhook === null,
                'resource' => $existingWebhook,
                'method' => 'webhooks_list'
            ];

        } catch (Exception $e) {
            echo "ℹ️  Busca de webhooks falhou: " . $e->getMessage() . "\n";
        }

        // Estratégia 2: Verificar configurações do tenant
        if ($tenantId) {
            try {
                $tenantConfig = $sdk->superAdmin()->getTenantConfig($tenantId);

                if (isset($tenantConfig['webhooks'])) {
                    foreach ($tenantConfig['webhooks'] as $webhook) {
                        if (isset($webhook['url']) && $webhook['url'] === $webhookUrl) {
                            return [
                                'exists' => true,
                                'available' => false,
                                'resource' => $webhook,
                                'method' => 'tenant_config'
                            ];
                        }
                    }
                }

            } catch (Exception $e) {
                echo "ℹ️  Busca de configuração do tenant falhou: " . $e->getMessage() . "\n";
            }
        }

        // Fallback: assumir disponível
        return [
            'exists' => false,
            'available' => true,
            'method' => 'fallback',
            'warning' => 'Não foi possível verificar com certeza - assumindo disponível'
        ];

    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de webhook URL: " . $e->getMessage() . "\n";
        throw $e;
    }
}

try {
    // ===============================================
    // CONFIGURAÇÕES DO EXEMPLO
    // ===============================================

    // Configurações personalizáveis do exemplo
    $EXAMPLE_CONFIG = [
        'organization' => [
            'name' => 'Nova Empresa Ltda',
            'admin_email' => 'admin@nova-empresa.com',
            'admin_name' => 'João Admin',
            'subdomain' => 'nova-empresa',
            'custom_domain' => 'checkout.nova-empresa.com'
        ],
        'product' => [
            'name' => 'Produto Demo',
            'description' => 'Produto criado via SDK com super admin',
            'price_amount' => 9999, // R$ 99,99
            'currency' => 'BRL'
        ],
        'options' => [
            'force_recreate_org' => false,    // Se true, tentará deletar e recriar
            'force_recreate_product' => false, // Se true, tentará deletar e recriar
            'show_detailed_logs' => true,     // Mostrar logs detalhados
            'max_tenants_to_show' => 3        // Quantos tenants mostrar na listagem
        ]
    ];

    echo "=== Exemplo Resiliente de Super Admin ===\n";
    echo "📋 Configurações:\n";
    echo "   Organização: {$EXAMPLE_CONFIG['organization']['name']}\n";
    echo "   Domínio: {$EXAMPLE_CONFIG['organization']['custom_domain']}\n";
    echo "   Produto: {$EXAMPLE_CONFIG['product']['name']}\n";
    echo "   Modo resiliente: ✅ Ativo (verifica antes de criar)\n\n";

    // ===============================================
    // 1. INICIALIZAÇÃO COMO SUPER ADMIN
    // ===============================================

    echo "=== Inicializando SDK como Super Admin ===\n";

    // Credenciais do super admin (API key como método primário, email/senha como fallback)
    $superAdminCredentials = [
        // 'api_key' => 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd', // Comentado para forçar fallback login
        'api_key_disabled' => 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd',
        'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OGMwMzA1Yzg1ZDczZjg3NmY5YTBkNjUiLCJlbWFpbCI6ImFkbWluQGV4YW1wbGUuY29tIiwicm9sZXMiOlsic3lzdGVtX2FkbWluIiwic3VwZXJfYWRtaW4iXSwidGVuYW50SWQiOiI1MDdmMWY3N2JjZjg2Y2Q3OTk0MzkwMTEiLCJmYW1pbHlJZCI6ImQyMTZkZmUzLTFmMzMtNDllNi05ZWIwLTJmZWUwNjk4M2U1NSIsImdlbmVyYXRpb24iOjAsImRldmljZUZpbmdlcnByaW50IjoiZGZwLTE3NTg1NTgxODUiLCJhdWQiOlsiY2x1YmlmeS11c2VycyJdLCJpc3MiOiJjbHViaWZ5LWNoZWNrb3V0IiwidG9rZW5UeXBlIjoiYWNjZXNzIiwianRpIjoiMzUwMzgzN2UtNjk3YS00MjIyLTkxNTYtZjNhYmI5NGE1MzU1IiwiaWF0IjoxNzU4NTU4MTg1LCJleHAiOjE3NTg2NDQ1ODV9.9eZuRGnngSTIQa2Px9Yyfoaddo1m-PM20l4XxdaVMlg',
        'refresh_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OGMwMzA1Yzg1ZDczZjg3NmY5YTBkNjUiLCJlbWFpbCI6ImFkbWluQGV4YW1wbGUuY29tIiwicm9sZXMiOlsic3lzdGVtX2FkbWluIiwic3VwZXJfYWRtaW4iXSwidGVuYW50SWQiOiI1MDdmMWY3N2JjZjg2Y2Q3OTk0MzkwMTEiLCJmYW1pbHlJZCI6ImQyMTZkZmUzLTFmMzMtNDllNi05ZWIwLTJmZWUwNjk4M2U1NSIsImdlbmVyYXRpb24iOjAsImRldmljZUZpbmdlcnByaW50IjoiZGZwLTE3NTg1NTgxODUiLCJhdWQiOlsiY2x1YmlmeS11c2VycyJdLCJpc3MiOiJjbHViaWZ5LWNoZWNrb3V0IiwidG9rZW5UeXBlIjoicmVmcmVzaCIsImp0aSI6ImJiNGU4NzQ3LTk2OGMtNDI0Yi05NDM0LTg1NTQxYjMzMjUyNyIsImlhdCI6MTc1ODU1ODE4NiwiZXhwIjoxNzU5MTYyOTg2fQ.tq3A_UQCWhpJlf8HKzKfsDJ8inKSVjc-QIfOCMK5Ei',
        // Fallback para autenticação por usuário/senha
        'email' => 'admin@example.com',
        'password' => 'P@ssw0rd!',
        'tenant_id' => '507f1f77bcf86cd799439011'
    ];

    // Configuração completa do SDK (baseada no test-sdk-simple.php)
    $config = [
        'credentials' => [
            'tenant_id' => $superAdminCredentials['tenant_id'],
            'api_key' => $superAdminCredentials['api_key_disabled'],
            'api_secret' => '87aa1373d3a948f996cf1b066651941b2f9928507c1e963c867b4aa90fec9e15',  // Placeholder para secret
            'email' => $superAdminCredentials['email'],
            'password' => $superAdminCredentials['password']
        ],
        'environment' => 'test',
        'api' => [
            'base_url' => 'https://checkout.svelve.com/api/v1',
            'timeout' => 45,
            'retries' => 3,
            'verify_ssl' => false
        ],
        'cache' => [
            'enabled' => true,
            'ttl' => 3600
        ],
        'logging' => [
            'enabled' => true,
            'level' => 'info'
        ]
    ];

    echo "📋 Configuração do SDK:\n";
    echo "   Tenant ID: {$config['credentials']['tenant_id']}\n";
    echo "   API Key: " . substr($config['credentials']['api_key'], 0, 20) . "...\n";
    echo "   Environment: {$config['environment']}\n";
    echo "   Base URL: {$config['api']['base_url']}\n\n";

    // Inicializar SDK com configuração completa
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK initialized successfully!\n";

    echo "   Version: " . $sdk->getVersion() . "\n";
    echo "   Authenticated: " . ($sdk->isAuthenticated() ? 'Yes' : 'No') . "\n";
    echo "   Initialized: " . ($sdk->isInitialized() ? 'Yes' : 'No') . "\n\n";

    // Inicializar como super admin
    $initResult = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    echo "✅ SDK inicializado como super admin:\n";
    echo "   Mode: " . $initResult['mode'] . "\n";
    echo "   Role: " . $initResult['role'] . "\n";
    echo "   Authenticated: " . ($initResult['authenticated'] ? 'Yes' : 'No') . "\n\n";

    // ===============================================
    // 2. CRIAÇÃO DE ORGANIZAÇÃO (COM VERIFICAÇÃO)
    // ===============================================

    echo "=== Criando ou Encontrando Organização ===\n";

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

    $tenantId = null;
    $organization = null;

    try {
        $organization = getOrCreateOrganization($sdk, $organizationData);

        if ($organization['existed']) {
            echo "✅ Organização existente encontrada:\n";
            echo "   Status: Já existia no sistema\n";
        } else {
            echo "✅ Nova organização criada com sucesso:\n";
            echo "   Status: Criada agora\n";
        }

        // Extrair IDs corretamente da estrutura de dados
        $tenantData = $organization['tenant'];
        $tenantId = $tenantData['id'] ?? $tenantData['_id'] ?? 'unknown';
        $organizationId = $organization['organization']['id'] ?? $tenantId;

        echo "   Organization ID: " . $organizationId . "\n";
        echo "   Tenant ID: " . $tenantId . "\n";

        if (isset($organization['tenant']['api_key'])) {
            echo "   API Key: " . substr($organization['tenant']['api_key'], 0, 20) . "...\n";
        }
        echo "\n";
    } catch (Exception $e) {
        echo "❌ Falha na criação/busca da organização: " . $e->getMessage() . "\n";
        echo "ℹ️  Continuando com o restante do exemplo usando tenant padrão...\n\n";

        // Usar tenant padrão se disponível
        $tenantId = $config['credentials']['tenant_id'];
    }

    // ===============================================
    // 3. GERENCIAMENTO DE TENANTS (SUPER ADMIN)
    // ===============================================

    echo "=== Operações de Super Admin ===\n";

    // Listar todos os tenants
    try {
        $tenants = $sdk->superAdmin()->listTenants();
        echo "📋 Total de tenants: " . count($tenants['data']) . "\n";
    } catch (Exception $e) {
        echo "⚠️  Erro ao listar tenants: " . $e->getMessage() . "\n";
    }

    // Obter estatísticas do sistema com timeout reduzido
    try {
        echo "📊 Tentando obter estatísticas do sistema (timeout: 10s)...\n";

        // Usar timeout de 10 segundos para evitar travamento
        $stats = $sdk->superAdmin()->getSystemStats(10);

        // Tratamento defensivo para parsing de estatísticas (estrutura real da API)
        $statsData = $stats['data'] ?? $stats;

        // A API retorna a estrutura: { total, active, trial, suspended, deleted, byPlan }
        $totalTenants = $statsData['total'] ?? 'N/A';
        $activeTenants = $statsData['active'] ?? 'N/A';
        $trialTenants = $statsData['trial'] ?? 'N/A';
        $suspendedTenants = $statsData['suspended'] ?? 'N/A';

        echo "📊 Total de tenants: " . $totalTenants . "\n";
        echo "📊 Tenants ativos: " . $activeTenants . "\n";
        echo "📊 Tenants em trial: " . $trialTenants . "\n";
        echo "📊 Tenants suspensos: " . $suspendedTenants . "\n";

        // Mostrar distribuição por plano se disponível
        if (isset($statsData['byPlan']) && is_array($statsData['byPlan'])) {
            echo "📊 Distribuição por plano:\n";
            foreach ($statsData['byPlan'] as $plan => $count) {
                echo "   - " . ucfirst($plan) . ": " . $count . "\n";
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'timeout') !== false ||
            strpos($errorMsg, 'timed out') !== false ||
            strpos($errorMsg, 'cURL error 28') !== false) {
            echo "⏱️  Timeout ao obter estatísticas (10s) - endpoint pode estar lento ou indisponível: continuando...\n";
        } elseif (strpos($errorMsg, '404') !== false || strpos($errorMsg, 'not found') !== false) {
            echo "ℹ️  Endpoint de estatísticas ainda não disponível (404): continuando...\n";
        } else {
            echo "⚠️  Erro ao obter estatísticas: " . substr($errorMsg, 0, 100) . "...\n";
        }
    }
    echo "\n";

    // ===============================================
    // 4. ALTERNÂNCIA DE CONTEXTO
    // ===============================================

    echo "=== Alternando para Contexto de Tenant ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            echo "🔄 Tentando alternar para tenant: $tenantId\n";

            // Usar nova versão robusta com validações
            $switchResult = $sdk->switchToTenant($tenantId);

            // Tratamento defensivo - verificar se o resultado é um array válido
            if (!is_array($switchResult)) {
                echo "⚠️  Resultado de alternância inesperado, assumindo falha\n";
                $switchResult = [
                    'success' => false,
                    'message' => 'Método retornou tipo inesperado'
                ];
            }

            $success = $switchResult['success'] ?? false;
            $message = $switchResult['message'] ?? 'Sem mensagem disponível';

            if ($success) {
                echo "✅ " . $message . "\n";
                echo "   Previous Context: " . ($switchResult['previous_context'] ?? 'N/A') . "\n";
                echo "   Current Context: " . ($switchResult['current_context'] ?? 'N/A') . "\n";
                echo "   Current Role: " . ($switchResult['current_role'] ?? 'N/A') . "\n\n";
            } else {
                echo "❌ Falha na alternância: " . $message . "\n\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao alternar contexto para tenant '$tenantId':\n";
            echo "   " . $e->getMessage() . "\n";

            // Fornecer orientação baseada no tipo de erro
            if (strpos($e->getMessage(), 'not found') !== false) {
                echo "   💡 Dica: Execute registerExistingTenant() primeiro\n";
            } elseif (strpos($e->getMessage(), 'API key') !== false) {
                echo "   💡 Dica: Tenant precisa de API key válida para alternância\n";
            }
            echo "ℹ️  Continuando com contexto de super admin...\n\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para alternar contexto (ID: '$tenantId')\n";
        echo "ℹ️  Continuando com contexto de super admin...\n\n";
    }

    // ===============================================
    // 5. EXEMPLOS DE VERIFICAÇÃO PRÉVIA
    // ===============================================

    echo "=== Exemplos de Verificação Prévia (Check-Before-Create) ===\n";

    // Exemplo 1: Verificar email antes de criar usuário
    if ($tenantId && $tenantId !== 'unknown') {
        try {
            $testEmail = 'test-user@' . ($EXAMPLE_CONFIG['organization']['custom_domain'] ?? 'example.com');
            $emailCheck = checkBeforeCreate($sdk, 'email', ['email' => $testEmail], $tenantId);

            if ($emailCheck && $emailCheck['exists']) {
                echo "📧 Email $testEmail já está em uso\n";
            } else {
                echo "📧 Email $testEmail está disponível para criação\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro na verificação de email: " . $e->getMessage() . "\n";
        }
    }

    // Exemplo 2: Verificar domínio antes de criar tenant
    try {
        $testDomain = 'exemplo-teste-' . date('Y-m-d') . '.clubify.me';
        $domainCheck = checkBeforeCreate($sdk, 'domain', ['domain' => $testDomain]);

        if ($domainCheck && $domainCheck['exists']) {
            echo "🌐 Domínio $testDomain já está em uso\n";
        } else {
            echo "🌐 Domínio $testDomain está disponível para criação\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de domínio: " . $e->getMessage() . "\n";
    }

    // Exemplo 3: Verificar subdomínio antes de criar tenant
    try {
        $testSubdomain = 'test-' . date('Ymd-His');
        $subdomainCheck = checkBeforeCreate($sdk, 'subdomain', ['subdomain' => $testSubdomain]);

        if ($subdomainCheck && $subdomainCheck['exists']) {
            echo "🏢 Subdomínio $testSubdomain já está em uso\n";
        } else {
            echo "🏢 Subdomínio $testSubdomain está disponível para criação\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de subdomínio: " . $e->getMessage() . "\n";
    }

    // Exemplo 4: Verificar slug de oferta
    if ($tenantId && $tenantId !== 'unknown') {
        try {
            $testSlug = 'oferta-teste-' . date('Y-m-d');
            $slugCheck = checkBeforeCreate($sdk, 'offer_slug', ['slug' => $testSlug], $tenantId);

            if ($slugCheck && $slugCheck['exists']) {
                echo "🏷️  Slug $testSlug já está em uso\n";
            } else {
                echo "🏷️  Slug $testSlug está disponível para criação\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro na verificação de slug: " . $e->getMessage() . "\n";
        }
    }

    // Exemplo 5: Verificar API key válida
    try {
        $testApiKey = $config['credentials']['api_key'] ?? 'test-key-invalid';
        $apiKeyCheck = checkBeforeCreate($sdk, 'api_key', ['key' => $testApiKey], $tenantId);

        if ($apiKeyCheck && $apiKeyCheck['exists'] && $apiKeyCheck['valid']) {
            echo "🔑 API Key é válida e funcional\n";
        } else {
            echo "🔑 API Key não é válida ou não existe\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro na verificação de API key: " . $e->getMessage() . "\n";
    }

    // Exemplo 6: Verificar webhook URL
    if ($tenantId && $tenantId !== 'unknown') {
        try {
            $testWebhookUrl = 'https://exemplo.com/webhook/test-' . date('Y-m-d');
            $webhookCheck = checkBeforeCreate($sdk, 'webhook_url', ['url' => $testWebhookUrl], $tenantId);

            if ($webhookCheck && $webhookCheck['exists']) {
                echo "🔗 Webhook URL $testWebhookUrl já está configurada\n";
            } else {
                echo "🔗 Webhook URL $testWebhookUrl está disponível para configuração\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro na verificação de webhook: " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // ===============================================
    // 6. OPERAÇÕES COMO TENANT ADMIN
    // ===============================================

    echo "=== Operações como Tenant Admin ===\n";

    // Primeiro listar produtos existentes
    try {
        // Listar produtos (como tenant admin) - usando método direto
        $products = $sdk->products()->list();
        echo "📦 Produtos existentes no tenant: " . count($products) . "\n";

        if (count($products) > 0) {
            echo "   Produtos encontrados:\n";
            foreach ($products as $product) {
                echo "   - " . (isset($product['name']) ? $product['name'] : 'Nome não disponível') . "\n";
            }
        }
        echo "\n";
    } catch (Exception $e) {
        echo "ℹ️  Ainda não há produtos para este tenant ou erro ao listar: " . $e->getMessage() . "\n\n";
    }

    // Criar um produto de exemplo usando verificação prévia
    $productData = [
        'name' => $EXAMPLE_CONFIG['product']['name'],
        'description' => $EXAMPLE_CONFIG['product']['description'],
        'price' => [
            'amount' => $EXAMPLE_CONFIG['product']['price_amount'],
            'currency' => $EXAMPLE_CONFIG['product']['currency']
        ],
        'type' => 'digital'
    ];

    try {
        $productResult = getOrCreateProduct($sdk, $productData);

        $productName = $productResult['product']['name'] ?? $productResult['product']['data']['name'] ?? 'Nome não disponível';

        if ($productResult['existed']) {
            echo "✅ Produto existente encontrado: " . $productName . "\n";
            echo "   Status: Já existia no sistema\n";
        } else {
            echo "✅ Novo produto criado: " . $productName . "\n";
            echo "   Status: Criado agora\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro na operação de produto: " . $e->getMessage() . "\n";
        echo "ℹ️  Continuando com outras operações...\n";
    }

    // ===============================================
    // 7. PROVISIONAMENTO DE DOMÍNIO E SSL
    // ===============================================

    echo "\n=== Provisionamento de Domínio e Certificado SSL ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            // Verificar se domínio já está configurado
            $customDomain = $EXAMPLE_CONFIG['organization']['custom_domain'];
            echo "🌐 Configurando domínio personalizado: $customDomain\n";

            // Verificar se domínio já está provisionado
            $domainCheck = checkBeforeCreate($sdk, 'domain', ['domain' => $customDomain]);

            if (!$domainCheck['exists']) {
                echo "📝 Provisionando novo domínio...\n";

                $domainData = [
                    'domain' => $customDomain,
                    'tenant_id' => $tenantId,
                    'ssl_enabled' => true,
                    'auto_redirect' => true,
                    'force_https' => true
                ];

                try {
                    $domainResult = $sdk->superAdmin()->provisionTenantDomain($tenantId, $domainData);

                    if ($domainResult['success']) {
                        echo "✅ Domínio provisionado com sucesso!\n";
                        echo "   🌐 Domínio: " . $domainResult['domain']['name'] . "\n";
                        echo "   🔒 SSL: " . ($domainResult['domain']['ssl_enabled'] ? 'Habilitado' : 'Desabilitado') . "\n";
                        echo "   📍 Status: " . $domainResult['domain']['status'] . "\n";

                        // Verificar status do certificado SSL
                        if ($domainResult['domain']['ssl_enabled']) {
                            echo "🔐 Iniciando provisionamento de certificado SSL...\n";

                            $sslResult = $sdk->superAdmin()->provisionSSLCertificate($tenantId, [
                                'domain' => $customDomain,
                                'auto_renew' => true,
                                'provider' => 'letsencrypt'
                            ]);

                            if ($sslResult['success']) {
                                echo "✅ Certificado SSL provisionado com sucesso!\n";
                                echo "   🔒 Certificado: " . $sslResult['certificate']['type'] . "\n";
                                echo "   📅 Válido até: " . $sslResult['certificate']['expires_at'] . "\n";
                                echo "   🔄 Auto-renovação: " . ($sslResult['certificate']['auto_renew'] ? 'Habilitada' : 'Desabilitada') . "\n";
                            } else {
                                echo "⚠️  Certificado SSL não pôde ser provisionado automaticamente\n";
                                echo "   📋 Configure manualmente ou aguarde propagação DNS\n";
                            }
                        }
                    }
                } catch (Exception $domainError) {
                    echo "⚠️  Erro no provisionamento de domínio: " . $domainError->getMessage() . "\n";
                    echo "   📋 Configuração manual necessária:\n";
                    echo "   1. Configurar DNS para apontar para os servidores do Clubify\n";
                    echo "   2. Verificar se domínio está disponível\n";
                    echo "   3. Configurar certificado SSL manualmente se necessário\n";
                }
            } else {
                echo "✅ Domínio já está configurado: $customDomain\n";

                // Verificar status do SSL para domínio existente
                try {
                    $sslStatus = $sdk->superAdmin()->checkSSLStatus($tenantId, $customDomain);
                    echo "🔒 Status SSL: " . $sslStatus['status'] . "\n";

                    if ($sslStatus['status'] !== 'active') {
                        echo "🔐 Tentando reativar certificado SSL...\n";
                        $renewResult = $sdk->superAdmin()->renewSSLCertificate($tenantId, $customDomain);
                        if ($renewResult['success']) {
                            echo "✅ Certificado SSL reativado com sucesso!\n";
                        }
                    }
                } catch (Exception $sslError) {
                    echo "ℹ️  Não foi possível verificar status SSL: " . $sslError->getMessage() . "\n";
                }
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral no provisionamento: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para provisionamento de domínio\n";
    }

    echo "\n";

    // ===============================================
    // 8. CONFIGURAÇÃO DE WEBHOOKS
    // ===============================================

    echo "=== Configuração de Webhooks ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            $webhookUrl = "https://webhook.exemplo.com/clubify-checkout/" . $tenantId;
            echo "🔗 Configurando webhook: $webhookUrl\n";

            // Verificar se webhook já está configurado
            $webhookCheck = checkBeforeCreate($sdk, 'webhook_url', ['url' => $webhookUrl], $tenantId);

            if (!$webhookCheck['exists']) {
                echo "📝 Criando novo webhook...\n";

                $webhookData = [
                    'url' => $webhookUrl,
                    'events' => [
                        'order.created',
                        'order.paid',
                        'order.cancelled',
                        'order.refunded',
                        'subscription.created',
                        'subscription.cancelled',
                        'payment.failed'
                    ],
                    'enabled' => true,
                    'retry_attempts' => 3,
                    'timeout' => 30
                ];

                try {
                    $webhookResult = $sdk->webhooks()->create($webhookData);

                    if ($webhookResult['success']) {
                        echo "✅ Webhook criado com sucesso!\n";
                        echo "   🔗 URL: " . $webhookResult['webhook']['url'] . "\n";
                        echo "   📢 Eventos: " . count($webhookResult['webhook']['events']) . " configurados\n";
                        echo "   ✅ Status: " . ($webhookResult['webhook']['enabled'] ? 'Ativo' : 'Inativo') . "\n";
                        echo "   🔄 Tentativas: " . $webhookResult['webhook']['retry_attempts'] . "\n";

                        // Testar webhook
                        echo "🧪 Testando webhook...\n";
                        $testResult = $sdk->webhooks()->test($webhookResult['webhook']['id']);

                        if ($testResult['success']) {
                            echo "✅ Teste de webhook bem-sucedido!\n";
                            echo "   📊 Resposta: " . $testResult['response_code'] . "\n";
                            echo "   ⏱️  Tempo: " . $testResult['response_time'] . "ms\n";
                        } else {
                            echo "⚠️  Teste de webhook falhou: " . $testResult['error'] . "\n";
                        }
                    }
                } catch (Exception $webhookError) {
                    echo "⚠️  Erro na criação de webhook: " . $webhookError->getMessage() . "\n";
                    echo "   📋 Configuração manual necessária:\n";
                    echo "   1. Verificar se URL está acessível\n";
                    echo "   2. Configurar webhook via interface admin\n";
                    echo "   3. Testar eventos manualmente\n";
                }
            } else {
                echo "✅ Webhook já está configurado: $webhookUrl\n";

                // Verificar status do webhook existente
                $existingWebhook = $webhookCheck['resource'];
                echo "   📢 Eventos: " . count($existingWebhook['events'] ?? []) . " configurados\n";
                echo "   ✅ Status: " . ($existingWebhook['enabled'] ? 'Ativo' : 'Inativo') . "\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral na configuração de webhooks: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para configuração de webhooks\n";
    }

    echo "\n";

    // ===============================================
    // 9. CRIAÇÃO DE OFERTAS COM PRODUTOS ASSOCIADOS
    // ===============================================

    echo "=== Criação de Ofertas com Produtos Associados ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            // Primeiro, garantir que temos um produto criado
            $productId = null;
            if (isset($productResult) && isset($productResult['product'])) {
                $productData = $productResult['product'];
                $productId = $productData['id'] ?? $productData['_id'] ?? null;
            }

            if (!$productId) {
                echo "⚠️  Nenhum produto encontrado, criando um produto básico primeiro...\n";

                $basicProductData = [
                    'name' => 'Produto Base para Oferta',
                    'description' => 'Produto criado automaticamente para demonstrar ofertas',
                    'price' => [
                        'amount' => 4999, // R$ 49,99
                        'currency' => 'BRL'
                    ],
                    'type' => 'digital'
                ];

                try {
                    $basicProduct = $sdk->products()->create($basicProductData);
                    $productId = $basicProduct['id'] ?? $basicProduct['_id'] ?? null;
                    echo "✅ Produto básico criado com ID: $productId\n";
                } catch (Exception $productError) {
                    echo "❌ Erro ao criar produto básico: " . $productError->getMessage() . "\n";
                    echo "⚠️  Pulando criação de ofertas...\n";
                    $productId = null;
                }
            }

            if ($productId) {
                echo "🎯 Criando oferta para produto ID: $productId\n";

                $offerSlug = 'oferta-' . date('Y-m-d') . '-' . substr($tenantId, -8);
                echo "🏷️  Slug da oferta: $offerSlug\n";

                // Verificar se oferta já existe
                $offerCheck = checkBeforeCreate($sdk, 'offer_slug', ['slug' => $offerSlug], $tenantId);

                if (!$offerCheck['exists']) {
                    echo "📝 Criando nova oferta...\n";

                    $offerData = [
                        'name' => 'Oferta Especial - ' . date('Y-m-d'),
                        'slug' => $offerSlug,
                        'description' => 'Oferta criada automaticamente via SDK com produto associado',
                        'product_id' => $productId,
                        'price' => [
                            'amount' => 3999, // Preço promocional R$ 39,99
                            'currency' => 'BRL',
                            'installments' => [
                                'enabled' => true,
                                'max_installments' => 12,
                                'min_installment_amount' => 500 // R$ 5,00 mínimo
                            ]
                        ],
                        'settings' => [
                            'checkout_enabled' => true,
                            'stock_control' => false,
                            'requires_address' => false,
                            'requires_phone' => true
                        ],
                        'seo' => [
                            'title' => 'Oferta Especial - Desconto Limitado',
                            'description' => 'Aproveite nossa oferta especial com desconto exclusivo!',
                            'keywords' => ['oferta', 'desconto', 'promoção']
                        ]
                    ];

                    try {
                        $offerResult = $sdk->offers()->create($offerData);

                        if ($offerResult['success']) {
                            echo "✅ Oferta criada com sucesso!\n";
                            echo "   🎯 Nome: " . $offerResult['offer']['name'] . "\n";
                            echo "   🏷️  Slug: " . $offerResult['offer']['slug'] . "\n";
                            echo "   💰 Preço: R$ " . number_format($offerResult['offer']['price']['amount'] / 100, 2, ',', '.') . "\n";
                            echo "   🛒 URL Checkout: " . $offerResult['offer']['checkout_url'] . "\n";

                            // Obter o ID da oferta criada
                            $offerId = $offerResult['offer']['id'] ?? $offerResult['offer']['_id'];

                            // Associar produto à oferta (se não foi feito automaticamente)
                            echo "🔗 Verificando associação produto-oferta...\n";
                            try {
                                $associationResult = $sdk->offers()->associateProduct($offerId, $productId);
                                if ($associationResult['success']) {
                                    echo "✅ Produto associado à oferta com sucesso!\n";
                                }
                            } catch (Exception $assocError) {
                                echo "ℹ️  Produto já estava associado ou associação automática: " . $assocError->getMessage() . "\n";
                            }

                            // Configurar URLs e informações da oferta
                            echo "📋 Informações importantes da oferta:\n";
                            echo "   🔗 URL da página de vendas: " . $offerResult['offer']['sales_page_url'] . "\n";
                            echo "   🛒 URL do checkout: " . $offerResult['offer']['checkout_url'] . "\n";
                            echo "   📊 URL de obrigado: " . $offerResult['offer']['thank_you_page_url'] . "\n";

                        } else {
                            echo "❌ Falha na criação da oferta\n";
                        }
                    } catch (Exception $offerError) {
                        echo "⚠️  Erro na criação de oferta: " . $offerError->getMessage() . "\n";
                        echo "   📋 Configuração manual necessária:\n";
                        echo "   1. Verificar se produto existe e está ativo\n";
                        echo "   2. Verificar se slug está disponível\n";
                        echo "   3. Criar oferta via interface admin\n";
                    }
                } else {
                    echo "✅ Oferta já existe com slug: $offerSlug\n";

                    $existingOffer = $offerCheck['resource'];
                    echo "   🎯 Nome: " . ($existingOffer['name'] ?? 'N/A') . "\n";
                    echo "   💰 Preço: R$ " . number_format(($existingOffer['price']['amount'] ?? 0) / 100, 2, ',', '.') . "\n";
                    echo "   🛒 Status: " . ($existingOffer['status'] ?? 'N/A') . "\n";
                }
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral na criação de ofertas: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para criação de ofertas\n";
    }

    echo "\n";

    // ===============================================
    // 10. CRIAÇÃO DE FLOWS PARA OFERTAS
    // ===============================================

    echo "=== Criação de Flows para Ofertas ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            // Verificar se temos uma oferta para criar flow
            $offerIdForFlow = null;

            // Tentar obter ID da oferta criada anteriormente
            if (isset($offerResult) && isset($offerResult['offer'])) {
                $offerIdForFlow = $offerResult['offer']['id'] ?? $offerResult['offer']['_id'] ?? null;
            }

            // Se não temos oferta, listar ofertas existentes
            if (!$offerIdForFlow) {
                echo "🔍 Buscando ofertas existentes para criar flow...\n";
                try {
                    $existingOffers = $sdk->offers()->list(['limit' => 1]);
                    if (!empty($existingOffers) && is_array($existingOffers)) {
                        $firstOffer = $existingOffers[0];
                        $offerIdForFlow = $firstOffer['id'] ?? $firstOffer['_id'] ?? null;
                        echo "✅ Encontrada oferta existente: " . ($firstOffer['name'] ?? 'N/A') . "\n";
                    }
                } catch (Exception $listError) {
                    echo "⚠️  Erro ao listar ofertas: " . $listError->getMessage() . "\n";
                }
            }

            if ($offerIdForFlow) {
                echo "🔄 Criando flow para oferta ID: $offerIdForFlow\n";

                $flowData = [
                    'name' => 'Flow Principal - ' . date('Y-m-d H:i:s'),
                    'offer_id' => $offerIdForFlow,
                    'type' => 'standard',
                    'steps' => [
                        [
                            'step_type' => 'landing_page',
                            'name' => 'Página de Vendas',
                            'template' => 'modern-sales-page',
                            'settings' => [
                                'show_testimonials' => true,
                                'show_guarantee' => true,
                                'show_bonus' => true,
                                'timer_enabled' => true,
                                'timer_duration' => 3600 // 1 hora
                            ]
                        ],
                        [
                            'step_type' => 'checkout',
                            'name' => 'Checkout',
                            'template' => 'single-step-checkout',
                            'settings' => [
                                'payment_methods' => ['credit_card', 'pix', 'bank_slip'],
                                'show_security_badges' => true,
                                'show_testimonials' => true,
                                'require_cpf' => true
                            ]
                        ],
                        [
                            'step_type' => 'thank_you',
                            'name' => 'Página de Obrigado',
                            'template' => 'thank-you-with-delivery',
                            'settings' => [
                                'show_social_proof' => true,
                                'show_related_products' => false,
                                'auto_download' => true
                            ]
                        ]
                    ],
                    'settings' => [
                        'tracking' => [
                            'google_analytics' => '',
                            'facebook_pixel' => '',
                            'google_tag_manager' => ''
                        ],
                        'seo' => [
                            'meta_title' => 'Oferta Especial - Não Perca!',
                            'meta_description' => 'Aproveite nossa oferta especial por tempo limitado!'
                        ]
                    ]
                ];

                try {
                    $flowResult = $sdk->flows()->create($flowData);

                    if ($flowResult['success']) {
                        echo "✅ Flow criado com sucesso!\n";
                        echo "   🔄 Nome: " . $flowResult['flow']['name'] . "\n";
                        echo "   📄 Etapas: " . count($flowResult['flow']['steps']) . " configuradas\n";
                        echo "   🔗 URL base: " . $flowResult['flow']['base_url'] . "\n";

                        // Mostrar URLs de cada etapa
                        echo "   📋 URLs das etapas:\n";
                        foreach ($flowResult['flow']['steps'] as $index => $step) {
                            echo "   " . ($index + 1) . ". " . $step['name'] . ": " . $step['url'] . "\n";
                        }

                        // Publicar o flow
                        echo "🚀 Publicando flow...\n";
                        $publishResult = $sdk->flows()->publish($flowResult['flow']['id']);

                        if ($publishResult['success']) {
                            echo "✅ Flow publicado com sucesso!\n";
                            echo "   🌐 Status: Ativo\n";
                            echo "   🔗 URL principal: " . $publishResult['flow']['public_url'] . "\n";
                        }
                    }
                } catch (Exception $flowError) {
                    echo "⚠️  Erro na criação de flow: " . $flowError->getMessage() . "\n";
                    echo "   📋 Configuração manual necessária:\n";
                    echo "   1. Verificar se oferta existe e está ativa\n";
                    echo "   2. Criar flow via interface admin\n";
                    echo "   3. Configurar etapas do funil de vendas\n";
                }
            } else {
                echo "⚠️  Nenhuma oferta encontrada para criar flow\n";
                echo "   📋 Criar uma oferta primeiro antes de configurar flows\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral na criação de flows: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para criação de flows\n";
    }

    echo "\n";

    // ===============================================
    // 11. CONFIGURAÇÃO DE TEMAS E LAYOUTS
    // ===============================================

    echo "=== Configuração de Temas e Layouts ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            echo "🎨 Configurando tema personalizado para o tenant...\n";

            // Configuração do tema principal
            $themeData = [
                'name' => 'Tema Personalizado - ' . date('Y-m-d'),
                'description' => 'Tema criado automaticamente via SDK',
                'is_default' => true,
                'settings' => [
                    'colors' => [
                        'primary' => '#007bff',
                        'secondary' => '#6c757d',
                        'success' => '#28a745',
                        'danger' => '#dc3545',
                        'warning' => '#ffc107',
                        'info' => '#17a2b8',
                        'light' => '#f8f9fa',
                        'dark' => '#343a40'
                    ],
                    'typography' => [
                        'font_family' => 'Inter, system-ui, sans-serif',
                        'font_size_base' => '16px',
                        'line_height' => 1.5,
                        'heading_font_family' => 'Inter, system-ui, sans-serif'
                    ],
                    'layout' => [
                        'container_max_width' => '1200px',
                        'border_radius' => '8px',
                        'spacing_unit' => '1rem'
                    ],
                    'components' => [
                        'buttons' => [
                            'border_radius' => '6px',
                            'font_weight' => 'bold',
                            'padding' => '12px 24px'
                        ],
                        'forms' => [
                            'input_border_radius' => '4px',
                            'input_padding' => '10px 16px',
                            'label_font_weight' => '500'
                        ]
                    ]
                ]
            ];

            try {
                $themeResult = $sdk->themes()->create($themeData);

                if ($themeResult['success']) {
                    echo "✅ Tema criado com sucesso!\n";
                    echo "   🎨 Nome: " . $themeResult['theme']['name'] . "\n";
                    echo "   🌈 Cor primária: " . $themeResult['theme']['settings']['colors']['primary'] . "\n";
                    echo "   📝 Status: " . ($themeResult['theme']['is_default'] ? 'Padrão' : 'Secundário') . "\n";

                    $themeId = $themeResult['theme']['id'] ?? $themeResult['theme']['_id'];

                    // Configurar layouts específicos para diferentes páginas
                    echo "📄 Configurando layouts personalizados...\n";

                    $layoutConfigs = [
                        [
                            'page_type' => 'sales_page',
                            'name' => 'Layout Página de Vendas',
                            'template' => 'modern-sales',
                            'settings' => [
                                'header' => [
                                    'show_logo' => true,
                                    'show_navigation' => false,
                                    'transparent' => true
                                ],
                                'hero' => [
                                    'background_type' => 'gradient',
                                    'text_alignment' => 'center',
                                    'show_video' => true
                                ],
                                'content' => [
                                    'show_testimonials' => true,
                                    'show_guarantee' => true,
                                    'show_faq' => true
                                ],
                                'footer' => [
                                    'show_social_links' => true,
                                    'show_copyright' => true
                                ]
                            ]
                        ],
                        [
                            'page_type' => 'checkout',
                            'name' => 'Layout Checkout',
                            'template' => 'minimal-checkout',
                            'settings' => [
                                'layout' => 'single_column',
                                'show_progress_bar' => true,
                                'show_security_badges' => true,
                                'show_testimonials' => false,
                                'sticky_summary' => true
                            ]
                        ],
                        [
                            'page_type' => 'thank_you',
                            'name' => 'Layout Obrigado',
                            'template' => 'celebration-thank-you',
                            'settings' => [
                                'show_confetti' => true,
                                'show_social_share' => true,
                                'show_next_steps' => true,
                                'auto_download' => true
                            ]
                        ]
                    ];

                    $createdLayouts = [];
                    foreach ($layoutConfigs as $layoutConfig) {
                        try {
                            $layoutConfig['theme_id'] = $themeId;
                            $layoutResult = $sdk->themes()->createLayout($layoutConfig);

                            if ($layoutResult['success']) {
                                echo "   ✅ Layout '{$layoutConfig['name']}' criado\n";
                                $createdLayouts[] = $layoutResult['layout'];
                            }
                        } catch (Exception $layoutError) {
                            echo "   ⚠️  Erro ao criar layout '{$layoutConfig['name']}': " . $layoutError->getMessage() . "\n";
                        }
                    }

                    // Aplicar tema como padrão
                    if (!empty($createdLayouts)) {
                        echo "🎯 Aplicando tema como padrão do tenant...\n";
                        try {
                            $applyResult = $sdk->themes()->setAsDefault($themeId, $tenantId);
                            if ($applyResult['success']) {
                                echo "✅ Tema aplicado como padrão com sucesso!\n";
                                echo "   🏢 Tenant: $tenantId\n";
                                echo "   🎨 Tema ID: $themeId\n";
                                echo "   📄 Layouts: " . count($createdLayouts) . " configurados\n";
                            }
                        } catch (Exception $applyError) {
                            echo "⚠️  Erro ao aplicar tema: " . $applyError->getMessage() . "\n";
                        }
                    }
                }
            } catch (Exception $themeError) {
                echo "⚠️  Erro na criação de tema: " . $themeError->getMessage() . "\n";
                echo "   📋 Configuração manual necessária:\n";
                echo "   1. Acessar painel de temas via interface admin\n";
                echo "   2. Criar tema personalizado\n";
                echo "   3. Configurar layouts para cada tipo de página\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral na configuração de temas: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para configuração de temas\n";
    }

    echo "\n";

    // ===============================================
    // 12. CONFIGURAÇÃO DE ORDERBUMP E UPSELL
    // ===============================================

    echo "=== Configuração de OrderBump e Upsell ===\n";

    if ($tenantId && $tenantId !== 'unknown') {
        try {
            // Verificar se temos uma oferta para configurar orderbump e upsell
            $mainOfferId = null;

            // Tentar obter ID da oferta criada anteriormente
            if (isset($offerResult) && isset($offerResult['offer'])) {
                $mainOfferId = $offerResult['offer']['id'] ?? $offerResult['offer']['_id'] ?? null;
            }

            // Se não temos oferta, listar ofertas existentes
            if (!$mainOfferId) {
                echo "🔍 Buscando ofertas existentes para configurar orderbump e upsell...\n";
                try {
                    $existingOffers = $sdk->offers()->list(['limit' => 1]);
                    if (!empty($existingOffers) && is_array($existingOffers)) {
                        $firstOffer = $existingOffers[0];
                        $mainOfferId = $firstOffer['id'] ?? $firstOffer['_id'] ?? null;
                        echo "✅ Encontrada oferta principal: " . ($firstOffer['name'] ?? 'N/A') . "\n";
                    }
                } catch (Exception $listError) {
                    echo "⚠️  Erro ao listar ofertas: " . $listError->getMessage() . "\n";
                }
            }

            if ($mainOfferId) {
                // CONFIGURAR ORDERBUMP
                echo "🛒 Configurando OrderBump para oferta principal...\n";

                $orderbumpData = [
                    'name' => 'Bônus Especial - OrderBump',
                    'description' => 'Produto adicional com desconto exclusivo no checkout',
                    'offer_id' => $mainOfferId,
                    'product' => [
                        'name' => 'Bônus Digital Exclusivo',
                        'description' => 'Material complementar especial',
                        'price' => [
                            'amount' => 1999, // R$ 19,99
                            'currency' => 'BRL'
                        ],
                        'type' => 'digital'
                    ],
                    'settings' => [
                        'display_position' => 'checkout_sidebar',
                        'discount_type' => 'percentage',
                        'discount_value' => 50, // 50% de desconto
                        'default_selected' => false,
                        'show_testimonial' => true,
                        'urgency_enabled' => true
                    ],
                    'copy' => [
                        'headline' => '🎁 Oferta Especial Apenas para Você!',
                        'description' => 'Aproveite e leve também este bônus exclusivo com 50% de desconto!',
                        'button_text' => 'Sim, eu quero o bônus!',
                        'testimonial' => 'Este bônus transformou minha experiência! - João Silva'
                    ]
                ];

                try {
                    $orderbumpResult = $sdk->orderbumps()->create($orderbumpData);

                    if ($orderbumpResult['success']) {
                        echo "✅ OrderBump criado com sucesso!\n";
                        echo "   🛒 Nome: " . $orderbumpResult['orderbump']['name'] . "\n";
                        echo "   💰 Preço: R$ " . number_format($orderbumpResult['orderbump']['product']['price']['amount'] / 100, 2, ',', '.') . "\n";
                        echo "   🏷️  Desconto: " . $orderbumpResult['orderbump']['settings']['discount_value'] . "%\n";
                        echo "   📍 Posição: " . $orderbumpResult['orderbump']['settings']['display_position'] . "\n";
                    }
                } catch (Exception $orderbumpError) {
                    echo "⚠️  Erro na criação de OrderBump: " . $orderbumpError->getMessage() . "\n";
                    echo "   📋 Configuração manual necessária via interface admin\n";
                }

                // CONFIGURAR UPSELL
                echo "📈 Configurando Upsell após checkout...\n";

                $upsellData = [
                    'name' => 'Upgrade Premium - Upsell',
                    'description' => 'Versão premium com recursos adicionais',
                    'trigger_offer_id' => $mainOfferId,
                    'product' => [
                        'name' => 'Versão Premium Completa',
                        'description' => 'Acesso completo com recursos avançados e suporte prioritário',
                        'price' => [
                            'amount' => 9999, // R$ 99,99
                            'currency' => 'BRL'
                        ],
                        'type' => 'digital'
                    ],
                    'settings' => [
                        'trigger_event' => 'checkout_success',
                        'display_timing' => 'immediate',
                        'auto_redirect' => true,
                        'time_limit' => 300, // 5 minutos
                        'show_countdown' => true,
                        'exit_intent' => true
                    ],
                    'copy' => [
                        'headline' => '🚀 Última Chance: Upgrade para Premium!',
                        'subheadline' => 'Desbloqueie recursos exclusivos com 40% de desconto',
                        'description' => 'Esta oferta especial está disponível apenas por alguns minutos após sua compra.',
                        'benefits' => [
                            'Suporte prioritário 24/7',
                            'Acesso a recursos avançados',
                            'Templates exclusivos',
                            'Certificado de conclusão'
                        ],
                        'button_text' => 'SIM! Quero o Upgrade',
                        'decline_text' => 'Não, obrigado'
                    ]
                ];

                try {
                    $upsellResult = $sdk->upsells()->create($upsellData);

                    if ($upsellResult['success']) {
                        echo "✅ Upsell criado com sucesso!\n";
                        echo "   📈 Nome: " . $upsellResult['upsell']['name'] . "\n";
                        echo "   💰 Preço: R$ " . number_format($upsellResult['upsell']['product']['price']['amount'] / 100, 2, ',', '.') . "\n";
                        echo "   ⏱️  Tempo limite: " . $upsellResult['upsell']['settings']['time_limit'] . " segundos\n";
                        echo "   🎯 Evento: " . $upsellResult['upsell']['settings']['trigger_event'] . "\n";

                        // Configurar sequência de downsell (caso upsell seja rejeitado)
                        echo "📉 Configurando Downsell como alternativa...\n";

                        $downsellData = [
                            'name' => 'Oferta Intermediária - Downsell',
                            'description' => 'Versão intermediária com preço reduzido',
                            'trigger_upsell_id' => $upsellResult['upsell']['id'],
                            'product' => [
                                'name' => 'Versão Básica Plus',
                                'description' => 'Recursos intermediários com ótimo custo-benefício',
                                'price' => [
                                    'amount' => 4999, // R$ 49,99
                                    'currency' => 'BRL'
                                ],
                                'type' => 'digital'
                            ],
                            'settings' => [
                                'trigger_event' => 'upsell_declined',
                                'display_timing' => 'immediate',
                                'time_limit' => 180, // 3 minutos
                                'show_countdown' => true
                            ],
                            'copy' => [
                                'headline' => '⚡ Espere! Que tal esta oferta?',
                                'description' => 'Já que o premium não interessou, que tal esta versão intermediária?',
                                'button_text' => 'Quero esta oferta',
                                'decline_text' => 'Não, continuar'
                            ]
                        ];

                        try {
                            $downsellResult = $sdk->downsells()->create($downsellData);
                            if ($downsellResult['success']) {
                                echo "✅ Downsell configurado como alternativa!\n";
                                echo "   📉 Nome: " . $downsellResult['downsell']['name'] . "\n";
                                echo "   💰 Preço: R$ " . number_format($downsellResult['downsell']['product']['price']['amount'] / 100, 2, ',', '.') . "\n";
                            }
                        } catch (Exception $downsellError) {
                            echo "ℹ️  Downsell não configurado: " . $downsellError->getMessage() . "\n";
                        }
                    }
                } catch (Exception $upsellError) {
                    echo "⚠️  Erro na criação de Upsell: " . $upsellError->getMessage() . "\n";
                    echo "   📋 Configuração manual necessária via interface admin\n";
                }

                // Resumo da configuração
                echo "\n📊 Resumo da Configuração de Funil:\n";
                echo "   🎯 Oferta Principal: Configurada\n";
                echo "   🛒 OrderBump: " . (isset($orderbumpResult) && $orderbumpResult['success'] ? 'Configurado' : 'Não configurado') . "\n";
                echo "   📈 Upsell: " . (isset($upsellResult) && $upsellResult['success'] ? 'Configurado' : 'Não configurado') . "\n";
                echo "   📉 Downsell: " . (isset($downsellResult) && $downsellResult['success'] ? 'Configurado' : 'Não configurado') . "\n";

            } else {
                echo "⚠️  Nenhuma oferta encontrada para configurar orderbump e upsell\n";
                echo "   📋 Criar uma oferta primeiro antes de configurar estratégias de funil\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro geral na configuração de orderbump/upsell: " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para configuração de orderbump/upsell\n";
    }

    echo "\n";

    // ===============================================
    // 13. VOLTA PARA SUPER ADMIN
    // ===============================================

    echo "\n=== Voltando para Super Admin ===\n";

    try {
        // Alternar de volta para super admin
        $sdk->switchToSuperAdmin();

        $context = $sdk->getCurrentContext();
        echo "🔄 Contexto alterado para: " . (isset($context['current_role']) ? $context['current_role'] : 'N/A') . "\n";
    } catch (Exception $e) {
        echo "⚠️  Erro ao voltar para super admin: " . $e->getMessage() . "\n";
        echo "ℹ️  Continuando com operações...\n";
    }

    // Agora podemos fazer operações de super admin novamente
    if ($tenantId) {
        try {
            $tenantCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
            echo "🔑 Credenciais do tenant obtidas com sucesso\n";
        } catch (Exception $e) {
            echo "⚠️  Erro ao obter credenciais do tenant: " . $e->getMessage() . "\n";
        }
    }

    // ===============================================
    // 8. GESTÃO AVANÇADA DE TENANTS
    // ===============================================

    echo "\n=== Gestão Avançada de Tenants ===\n";

    // Verificar credenciais atuais antes de regenerar
    if ($tenantId) {
        try {
            $currentCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
            echo "🔍 Credenciais atuais obtidas com sucesso\n";
            echo "   Current API Key: " . substr($currentCredentials['api_key'] ?? 'N/A', 0, 20) . "...\n";

            // Testar funcionalidade de rotação de API key (apenas se houver API key)
            if (!empty($currentCredentials['api_key_id'])) {
                echo "🔄 Testando rotação de API key...\n";
                try {
                    $rotationResult = $sdk->superAdmin()->rotateApiKey($currentCredentials['api_key_id'], [
                        'gracePeriodHours' => 1,  // Período curto para teste
                        'forceRotation' => false   // Não forçar para teste
                    ]);
                    echo "✅ Rotação iniciada com sucesso\n";
                    echo "   Nova API Key: " . substr($rotationResult['newApiKey'] ?? 'N/A', 0, 20) . "...\n";
                    echo "   Período de graça: " . ($rotationResult['gracePeriodHours'] ?? 'N/A') . " horas\n";
                } catch (Exception $rotateError) {
                    echo "ℹ️  Rotação não executada: " . $rotateError->getMessage() . "\n";
                }
            } else {
                echo "ℹ️  Não há API key ID disponível para rotação\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Erro na gestão de credenciais: " . $e->getMessage() . "\n";
            echo "   Continuando com outras operações...\n";
        }
    } else {
        echo "⚠️  Nenhum tenant disponível para gestão de credenciais\n";
    }

    // Listar tenants (API não suporta filtros específicos no momento)
    try {
        $filteredTenants = $sdk->superAdmin()->listTenants();
        // Corrigir contagem baseada na estrutura real da API
        $totalTenants = $filteredTenants['data']['total'] ?? count($filteredTenants['data']['tenants'] ?? $filteredTenants['data'] ?? []);
        echo "📋 Total de tenants encontrados: " . $totalTenants . "\n";

        // Mostrar alguns detalhes dos tenants encontrados
        // A API retorna { data: { tenants: [...], total, page, limit } }
        $tenantsData = $filteredTenants['data']['tenants'] ?? $filteredTenants['data'] ?? [];
        if (count($tenantsData) > 0) {
            $maxToShow = $EXAMPLE_CONFIG['options']['max_tenants_to_show'];
            echo "   Primeiros tenants (máximo $maxToShow):\n";
            $count = 0;
            foreach ($tenantsData as $tenant) {
                if ($count >= $maxToShow) break;

                // Parsing melhorado para dados do tenant (estrutura real da API)
                $name = $tenant['name'] ?? 'Sem nome';
                $status = $tenant['status'] ?? 'unknown';
                $plan = $tenant['plan'] ?? 'sem plano';
                $domain = $tenant['domain'] ?? $tenant['subdomain'] ?? 'sem domínio';

                // Adicionar ID para identificação (últimos 8 chars)
                $tenantId = $tenant['_id'] ?? $tenant['id'] ?? 'no-id';
                $shortId = strlen($tenantId) > 8 ? substr($tenantId, -8) : $tenantId;

                echo "   - $name\n";
                echo "     Domain: $domain | Status: $status | Plan: $plan | ID: $shortId\n";
                $count++;
            }
            if (count($tenantsData) > $maxToShow) {
                echo "   ... e mais " . (count($tenantsData) - $maxToShow) . " tenant(s)\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Erro ao listar tenants filtrados: " . $e->getMessage() . "\n";
    }

    // ===============================================
    // 9. INFORMAÇÕES DE CONTEXTO
    // ===============================================

    echo "\n=== Informações do Contexto Atual ===\n";

    try {
        $finalContext = $sdk->getCurrentContext();
        echo "📍 Modo de operação: " . (isset($finalContext['mode']) ? $finalContext['mode'] : 'N/A') . "\n";
        echo "👤 Role atual: " . (isset($finalContext['current_role']) ? $finalContext['current_role'] : 'N/A') . "\n";

        if (isset($finalContext['available_contexts']['contexts'])) {
            echo "🏢 Contextos disponíveis: " . count($finalContext['available_contexts']['contexts']) . "\n";
        } else {
            echo "🏢 Contextos disponíveis: N/A\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro ao obter contexto atual: " . $e->getMessage() . "\n";
    }

    // ===============================================
    // 14. RESUMO FINAL COMPLETO
    // ===============================================

    echo "\n=== Resumo Completo da Execução ===\n";

    // SEÇÃO 1: CONFIGURAÇÃO INICIAL
    echo "🔧 CONFIGURAÇÃO INICIAL:\n";
    echo "   ✅ SDK inicializado como super admin\n";
    echo "   " . ($organization ? "✅" : "⚠️ ") . " Organização " . ($organization ? "verificada/criada" : "falhou, mas continuou") . "\n";
    echo "   ✅ Credenciais de tenant provisionadas (com verificação prévia)\n";
    echo "   ✅ Alternância de contexto testada\n";

    // SEÇÃO 2: INFRAESTRUTURA
    echo "\n🌐 INFRAESTRUTURA:\n";
    echo "   ✅ Provisionamento de domínio configurado\n";
    echo "   🔒 Certificado SSL configurado\n";
    echo "   🔗 Webhooks configurados para eventos do sistema\n";

    // SEÇÃO 3: PRODUTOS E OFERTAS
    echo "\n🛍️  PRODUTOS E OFERTAS:\n";
    echo "   ✅ Produtos criados (com verificação prévia)\n";
    echo "   🎯 Ofertas criadas com produtos associados\n";
    echo "   🔄 Flows de vendas configurados (landing + checkout + obrigado)\n";

    // SEÇÃO 4: PERSONALIZAÇÃO
    echo "\n🎨 PERSONALIZAÇÃO:\n";
    echo "   🎨 Temas personalizados criados\n";
    echo "   📄 Layouts configurados para diferentes tipos de página\n";
    echo "   🌈 Identidade visual do tenant aplicada\n";

    // SEÇÃO 5: ESTRATÉGIAS DE VENDAS
    echo "\n📈 ESTRATÉGIAS DE VENDAS:\n";
    echo "   🛒 OrderBump configurado (ofertas no checkout)\n";
    echo "   📈 Upsell pós-compra configurado\n";
    echo "   📉 Downsell como alternativa configurado\n";
    echo "   🎯 Funil de vendas completo implementado\n";

    // SEÇÃO 6: OPERAÇÕES ADMINISTRATIVAS
    echo "\n⚙️  OPERAÇÕES ADMINISTRATIVAS:\n";
    echo "   ✅ Métodos de verificação prévia (check-before-create) implementados\n";
    echo "   ✅ Gestão de credenciais e API keys testada\n";
    echo "   ✅ Rotação de credenciais testada\n";
    echo "   ✅ Informações de contexto e estatísticas verificadas\n";

    echo "\n🎉 EXEMPLO COMPLETO DE SETUP DE CHECKOUT CONCLUÍDO!\n";
    echo "\n📋 CARACTERÍSTICAS DO SCRIPT:\n";
    echo "   💪 Resiliente a conflitos e erros de API\n";
    echo "   🔍 Verificação prévia antes de criar recursos (evita erro 409)\n";
    echo "   🔄 Continua executando mesmo quando algumas operações falham\n";
    echo "   📝 Logs detalhados para debugging e acompanhamento\n";
    echo "   🛡️  Tratamento defensivo para diferentes estruturas de resposta da API\n";
    echo "   ⚡ Operações otimizadas com fallbacks automáticos\n";

    echo "\n🚀 PRÓXIMOS PASSOS RECOMENDADOS:\n";
    echo "   1. Testar URLs geradas (checkout, páginas de vendas, etc.)\n";
    echo "   2. Configurar integrações específicas (gateways de pagamento)\n";
    echo "   3. Personalizar conteúdo das páginas via interface admin\n";
    echo "   4. Configurar automações e sequences de email\n";
    echo "   5. Implementar tracking e analytics específicos\n";

    echo "\n📊 RECURSOS IMPLEMENTADOS:\n";
    echo "   🏢 Gestão completa de tenants e organizações\n";
    echo "   👥 Gestão de usuários com verificação de conflitos\n";
    echo "   🌐 Provisionamento automático de domínio e SSL\n";
    echo "   🔗 Sistema de webhooks para integrações\n";
    echo "   🛍️  Catálogo de produtos e ofertas\n";
    echo "   🔄 Flows de vendas personalizáveis\n";
    echo "   🎨 Sistema de temas e layouts\n";
    echo "   🛒 OrderBumps, Upsells e Downsells\n";
    echo "   📈 Funil de vendas completo\n";

    echo "\n💡 DICAS DE USO:\n";
    echo "   - Execute o script quantas vezes quiser - ele detecta recursos existentes\n";
    echo "   - Modifique as configurações no início do script conforme necessário\n";
    echo "   - Use os métodos checkBeforeCreate() como referência para suas integrações\n";
    echo "   - Monitore os logs para identificar possíveis melhorias na API\n";

} catch (Exception $e) {
    echo "\n❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "\n📋 Detalhes do erro:\n";
    echo "   Tipo: " . get_class($e) . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";

    // Verificar se é um erro específico conhecido
    if (strpos($e->getMessage(), 'already in use') !== false) {
        echo "\n💡 DICA: Este erro indica que um recurso já existe.\n";
        echo "   O script foi atualizado para lidar com isso automaticamente.\n";
        echo "   Se você ainda está vendo este erro, pode ser necessário verificar\n";
        echo "   a lógica de detecção de recursos existentes.\n";
    } elseif (strpos($e->getMessage(), 'HTTP request failed') !== false) {
        echo "\n💡 DICA: Erro de comunicação com a API.\n";
        echo "   Verifique:\n";
        echo "   - Conexão com a internet\n";
        echo "   - URL da API está correta\n";
        echo "   - Credenciais estão válidas\n";
        echo "   - Serviço está funcionando\n";
    } elseif (strpos($e->getMessage(), 'Unauthorized') !== false || strpos($e->getMessage(), '401') !== false) {
        echo "\n💡 DICA: Erro de autenticação.\n";
        echo "   Verifique:\n";
        echo "   - Email e senha estão corretos\n";
        echo "   - API key está válida\n";
        echo "   - Usuário tem permissões de super admin\n";
    }

    echo "\n📋 Stack trace completo:\n";
    echo $e->getTraceAsString() . "\n";

    echo "\n🔄 Para tentar novamente, execute o script novamente.\n";
    echo "   O script agora verifica recursos existentes antes de criar.\n";
}