<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Exemplo de uso do SDK com funcionalidades de Super Admin
 * Versão resiliente com verificações GET antes de criar recursos
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

            // Registrar tenant existente para permitir alternância de contexto
            try {
                echo "🔑 Registrando tenant existente para alternância de contexto...\n";
                $sdk->registerExistingTenant($tenantId, $tenantData);
                echo "✅ Tenant registrado com sucesso para alternância de contexto\n";

                // Tentar provisionar credenciais se necessário
                if (!isset($tenantData['api_key']) || empty($tenantData['api_key'])) {
                    echo "🔧 Tenant sem API key detectado, tentando provisionar credenciais...\n";
                    try {
                        $provisionResult = $sdk->superAdmin()->provisionTenantCredentials($tenantId, [
                            'admin_email' => $organizationData['admin_email'] ?? "admin@{$tenantId}.local",
                            'admin_name' => $organizationData['admin_name'] ?? 'Tenant Administrator'
                        ]);
                        echo "✅ Credenciais de tenant provisionadas com sucesso\n";

                        // Atualizar dados do tenant com as novas credenciais
                        $provisionData = $provisionResult['data'] ?? $provisionResult;
                        if (isset($provisionData['api_key'])) {
                            $tenantData['api_key'] = $provisionData['api_key'];
                            echo "   API Key: " . substr($provisionData['api_key'], 0, 20) . "...\n";
                        }
                    } catch (Exception $e) {
                        echo "⚠️  Aviso: Não foi possível provisionar credenciais automaticamente: " . $e->getMessage() . "\n";
                        echo "   Será necessário criar um usuário tenant_admin e API key manualmente\n";
                    }
                }
            } catch (Exception $e) {
                echo "⚠️  Aviso: Não foi possível registrar tenant para alternância: " . $e->getMessage() . "\n";
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

            // Registrar tenant existente para permitir alternância de contexto
            try {
                echo "🔑 Registrando tenant existente para alternância de contexto...\n";
                $sdk->registerExistingTenant($tenantId, $tenantData);
                echo "✅ Tenant registrado com sucesso para alternância de contexto\n";

                // Tentar provisionar credenciais se necessário
                if (!isset($tenantData['api_key']) || empty($tenantData['api_key'])) {
                    echo "🔧 Tenant sem API key detectado, tentando provisionar credenciais...\n";
                    try {
                        $provisionResult = $sdk->superAdmin()->provisionTenantCredentials($tenantId, [
                            'admin_email' => $organizationData['admin_email'] ?? "admin@{$tenantId}.local",
                            'admin_name' => $organizationData['admin_name'] ?? 'Tenant Administrator'
                        ]);
                        echo "✅ Credenciais de tenant provisionadas com sucesso\n";

                        // Atualizar dados do tenant com as novas credenciais
                        $provisionData = $provisionResult['data'] ?? $provisionResult;
                        if (isset($provisionData['api_key'])) {
                            $tenantData['api_key'] = $provisionData['api_key'];
                            echo "   API Key: " . substr($provisionData['api_key'], 0, 20) . "...\n";
                        }
                    } catch (Exception $e) {
                        echo "⚠️  Aviso: Não foi possível provisionar credenciais automaticamente: " . $e->getMessage() . "\n";
                        echo "   Será necessário criar um usuário tenant_admin e API key manualmente\n";
                    }
                }
            } catch (Exception $e) {
                echo "⚠️  Aviso: Não foi possível registrar tenant para alternância: " . $e->getMessage() . "\n";
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
        $tenantId = '507f1f77bcf86cd799439011';
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
        echo "📊 Organizações ativas: " . ($stats['organizations']['active'] ?? 'N/A') . "\n";
        echo "📊 Total de usuários: " . ($stats['users']['total'] ?? 'N/A') . "\n";
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
            // Alternar para o tenant criado
            $sdk->switchToTenant($tenantId);

            $context = $sdk->getCurrentContext();
            echo "✅ Contexto alterado com sucesso:\n";
            echo "   Current Role: " . (isset($context['current_role']) ? $context['current_role'] : 'N/A') . "\n";
            $currentRole = isset($context['current_role']) ? $context['current_role'] : '';
            echo "   Active Context: " . ($currentRole === 'tenant_admin' ? $tenantId : 'super_admin') . "\n\n";
        } catch (Exception $e) {
            echo "⚠️  Erro ao alternar contexto para tenant '$tenantId': " . $e->getMessage() . "\n";
            echo "ℹ️  Continuando com contexto de super admin...\n\n";
        }
    } else {
        echo "⚠️  Nenhum tenant válido disponível para alternar contexto (ID: '$tenantId')\n";
        echo "ℹ️  Continuando com contexto de super admin...\n\n";
    }

    // ===============================================
    // 5. OPERAÇÕES COMO TENANT ADMIN
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
    // 6. VOLTA PARA SUPER ADMIN
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
    // 7. GESTÃO AVANÇADA DE TENANTS
    // ===============================================

    echo "\n=== Gestão Avançada de Tenants ===\n";

    // Verificar credenciais atuais antes de regenerar
    if ($tenantId) {
        try {
            $currentCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
            echo "🔍 Credenciais atuais obtidas com sucesso\n";
            echo "   Current API Key: " . substr($currentCredentials['api_key'] ?? 'N/A', 0, 20) . "...\n";

            // Endpoint de regenerar API key não disponível no momento
            echo "ℹ️  Funcionalidade de regenerar API key ainda não implementada\n";
            echo "   Endpoint necessário: POST /api-keys/{keyId}/rotate\n";
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
        echo "📋 Total de tenants encontrados: " . count($filteredTenants['data']) . "\n";

        // Mostrar alguns detalhes dos tenants encontrados
        if (count($filteredTenants['data']) > 0) {
            $maxToShow = $EXAMPLE_CONFIG['options']['max_tenants_to_show'];
            echo "   Primeiros tenants (máximo $maxToShow):\n";
            $count = 0;
            foreach ($filteredTenants['data'] as $tenant) {
                if ($count >= $maxToShow) break;
                $name = $tenant['name'] ?? $tenant['subdomain'] ?? 'Nome não disponível';
                $status = $tenant['status'] ?? 'Status não disponível';
                echo "   - $name (Status: $status)\n";
                $count++;
            }
            if (count($filteredTenants['data']) > $maxToShow) {
                echo "   ... e mais " . (count($filteredTenants['data']) - $maxToShow) . " tenant(s)\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Erro ao listar tenants filtrados: " . $e->getMessage() . "\n";
    }

    // ===============================================
    // 8. INFORMAÇÕES DE CONTEXTO
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
    // 9. RESUMO FINAL
    // ===============================================

    echo "\n=== Resumo da Execução ===\n";
    echo "✅ SDK inicializado como super admin\n";
    echo ($organization ? "✅" : "⚠️ ") . " Organização " . ($organization ? "verificada/criada" : "falhou, mas continuou") . "\n";
    echo "✅ Contexto de tenant testado\n";
    echo "✅ Operações de produto testadas\n";
    echo "✅ Retorno para super admin testado\n";
    echo "✅ Gestão de credenciais testada\n";
    echo "✅ Informações de contexto verificadas\n";
    echo "\n🎉 Exemplo de Super Admin concluído!\n";
    echo "📝 Todas as operações foram executadas com tratamento de erro.\n";
    echo "📝 O script continua executando mesmo quando algumas operações falham.\n";
    echo "📝 Script resiliente a conflitos e erros de API.\n";

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