<?php

/**
 * Organization Complete Workflow - Exemplo Completo
 *
 * Demonstra um fluxo completo usando Organization API Keys:
 * 1. Autenticação como organização com tenant context
 * 2. Criação de produto
 * 3. Criação de oferta
 * 4. Criação de flow de checkout
 * 5. Alternância de tenant (quando necessário)
 *
 * Este exemplo mostra como usar os recursos do SDK após autenticar
 * como organização, criando um fluxo completo de vendas.
 *
 * IMPORTANTE - TENANT CONTEXT:
 * ============================
 * Mesmo com Organization API Key (scope='organization'), você DEVE
 * especificar um tenant_id na autenticação para operações que exigem
 * contexto de tenant, como:
 * - Criação/listagem de produtos
 * - Criação/configuração de ofertas
 * - Criação de flows de checkout
 * - Gestão de pedidos e clientes
 *
 * Operações que NÃO exigem tenant context:
 * - Listagem de tenants da organização
 * - Criação de novos tenants
 * - Configurações globais de organização
 * - Relatórios consolidados cross-tenant
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "🚀 Organization Complete Workflow - Exemplo Completo\n";
echo "===================================================\n\n";

// Configurações (em produção, use variáveis de ambiente)
$organizationId = '68d94e3a878451ed8bb9d873';
$organizationApiKey = 'clb_org_test_d78b95b90bfb3ba1b8606578dba3e1e7';
$tenantId = '68dab606378f93bd3931cdc0'; // Tenant específico para as operações

// ===== PASSO 1: Autenticação como Organização =====
echo "🔐 PASSO 1: Autenticação como Organização\n";
echo "-------------------------------------------\n";

try {
    $sdk = new ClubifyCheckoutSDK([
        'environment' => 'sandbox'
    ]);

    // Autenticar usando Organization API Key especificando o tenant
    // Nota: O SDK automaticamente configura o token de acesso e headers
    // (Authorization, X-Organization-Id, X-Tenant-Id) para todas as requisições subsequentes
    //
    // IMPORTANTE: Mesmo com organization scope, precisamos especificar o tenant
    // para operações que exigem contexto de tenant (produtos, ofertas, flows, etc)
    $authResult = $sdk->authenticateWithOrganizationApiKey($organizationId, $organizationApiKey, $tenantId);

    if ($authResult['success']) {
        echo "✅ Autenticação bem-sucedida!\n";
        echo "   Organization ID: " . $authResult['organization_id'] . "\n";
        echo "   Scope: " . $authResult['scope'] . "\n";
        echo "   Access Token: " . substr($authResult['access_token'], 0, 20) . "...\n";
        echo "   Expires In: " . $authResult['expires_in'] . " segundos\n";

        // Verificar tenants acessíveis
        $accessibleTenants = $authResult['accessible_tenants'] ?? [];
        if (empty($accessibleTenants)) {
            echo "   Acesso: TODOS os tenants da organização\n";
        } else {
            echo "   Tenants Acessíveis: " . count($accessibleTenants) . "\n";
        }
    } else {
        echo "❌ Falha na autenticação\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ Erro na autenticação: " . $e->getMessage() . "\n";
    exit(1);
}


echo "\n";

// ===== PASSO 2: Verificar Contexto Organizacional =====
echo "🏢 PASSO 2: Verificar Contexto Organizacional\n";
echo "----------------------------------------------\n";

try {
    // O SDK já está autenticado com organization scope e tenant definido
    // Nota: Usamos $authResult diretamente pois $sdk->getOrganizationContext()
    // cria uma nova instância do OrganizationAuthManager sem os dados da autenticação
    echo "✅ Contexto configurado:\n";
    echo "   Organization ID: " . $authResult['organization_id'] . "\n";
    echo "   Tenant ID: " . ($authResult['tenant_id'] ?? 'não especificado') . "\n";
    echo "   Scope: " . $authResult['scope'] . "\n";
    echo "   Permissions: " . count($authResult['permissions']) . " permissões\n";

    // Verificar tenants acessíveis
    if (!empty($authResult['accessible_tenants'])) {
        $accessibleCount = count($authResult['accessible_tenants']);
        if ($authResult['accessible_tenants'][0] === '*') {
            echo "   ✅ Acesso: TODOS os tenants da organização\n";
        } else {
            echo "   Tenants Acessíveis: $accessibleCount\n";
            foreach (array_slice($authResult['accessible_tenants'], 0, 3) as $tenant) {
                $tid = is_array($tenant) ? ($tenant['id'] ?? $tenant['_id'] ?? 'unknown') : $tenant;
                $tname = is_array($tenant) ? ($tenant['name'] ?? 'Unknown') : $tid;
                echo "     - $tname (ID: " . substr($tid, -8) . ")\n";
            }
        }
    }

    // Mostrar permissões principais
    if (!empty($authResult['permissions'])) {
        echo "   Principais Permissões:\n";
        foreach (array_slice($authResult['permissions'], 0, 3) as $permission) {
            echo "     - $permission\n";
        }
        if (count($authResult['permissions']) > 3) {
            echo "     - ... e mais " . (count($authResult['permissions']) - 3) . " permissões\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erro ao verificar contexto: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== PASSO 3: Criar Produto =====
echo "📦 PASSO 3: Criar Produto\n";
echo "--------------------------\n";

$productData = [
    'name' => 'Curso Completo de Marketing Digital',
    'description' => 'Aprenda marketing digital do zero ao avançado',
    'type' => 'digital',
    'price' => 29790, // R$ 297,90 em centavos
    'currency' => 'BRL'
];

try {
    echo "Criando produto: " . $productData['name'] . "\n";

    // Verificar se o produto já existe
    $existingProducts = $sdk->products()->list([
        'search' => $productData['name'],
        'limit' => 1
    ]);

    $product = null;
    if (!empty($existingProducts['data'])) {
        foreach ($existingProducts['data'] as $existing) {
            if ($existing['name'] === $productData['name']) {
                $product = $existing;
                echo "ℹ️  Produto já existe\n";
                break;
            }
        }
    }

    if (!$product) {
        $product = $sdk->products()->create($productData);
        echo "✅ Produto criado com sucesso!\n";
    }

    $productId = $product['id'] ?? $product['_id'];
    echo "   ID: " . $productId . "\n";
    echo "   Nome: " . $product['name'] . "\n";
    echo "   Preço: R$ " . number_format($productData['price'] / 100, 2, ',', '.') . "\n";
    echo "   Tipo: " . $product['type'] . "\n";

} catch (Exception $e) {
    echo "❌ Erro ao criar produto: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// ===== PASSO 4: Criar Oferta =====
echo "🎯 PASSO 4: Criar Oferta\n";
echo "-------------------------\n";

$offerData = [
    'name' => 'Oferta Especial - Marketing Digital',
    'description' => 'Aproveite nossa oferta especial com bônus exclusivos',
    'type' => 'single_product',
    'products' => [$productId],
    'status' => 'active',
    'settings' => [
        'max_purchases' => 100,
        'currency' => 'BRL',
        'show_guarantee' => true,
        'guarantee_days' => 30
    ],
    'theme' => [
        'primary_color' => '#007bff',
        'secondary_color' => '#6c757d',
        'font_family' => 'Roboto, sans-serif'
    ],
    'layout' => [
        'type' => 'single_column',
        'show_testimonials' => true,
        'show_guarantee' => true,
        'show_timer' => false
    ]
];

try {
    echo "Criando oferta: " . $offerData['name'] . "\n";

    // Verificar se a oferta já existe
    $existingOffers = $sdk->offer()->list([
        'search' => $offerData['name'],
        'limit' => 1
    ]);

    $offer = null;
    if (!empty($existingOffers['data'])) {
        foreach ($existingOffers['data'] as $existing) {
            if ($existing['name'] === $offerData['name']) {
                $offer = $existing;
                echo "ℹ️  Oferta já existe\n";
                break;
            }
        }
    }

    if (!$offer) {
        $offer = $sdk->offer()->create($offerData);
        echo "✅ Oferta criada com sucesso!\n";
    }

    $offerId = $offer['id'] ?? $offer['_id'];
    echo "   ID: " . $offerId . "\n";
    echo "   Nome: " . $offer['name'] . "\n";
    echo "   Tipo: " . $offer['type'] . "\n";
    echo "   Status: " . ($offer['status'] ?? 'active') . "\n";

    // Configurar tema da oferta
    if (isset($offerData['theme'])) {
        try {
            echo "   Configurando tema...\n";
            $sdk->offer()->configureTheme($offerId, $offerData['theme']);
            echo "   ✅ Tema configurado\n";
        } catch (Exception $themeError) {
            echo "   ⚠️  Tema: " . $themeError->getMessage() . "\n";
        }
    }

    // Configurar layout da oferta
    if (isset($offerData['layout'])) {
        try {
            echo "   Configurando layout...\n";
            $sdk->offer()->configureLayout($offerId, $offerData['layout']);
            echo "   ✅ Layout configurado\n";
        } catch (Exception $layoutError) {
            echo "   ⚠️  Layout: " . $layoutError->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erro ao criar oferta: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// ===== PASSO 5: Criar Flow de Checkout =====
echo "🔄 PASSO 5: Criar Flow de Checkout\n";
echo "-----------------------------------\n";

$flowData = [
    'name' => 'Flow Marketing Digital',
    'type' => 'standard',
    'offer_id' => $offerId,
    'steps' => [
        [
            'type' => 'product_selection',
            'config' => [
                'show_related' => true,
                'allow_multiple' => false
            ]
        ],
        [
            'type' => 'customer_info',
            'config' => [
                'required_fields' => ['name', 'email', 'phone'],
                'optional_fields' => ['cpf']
            ]
        ],
        [
            'type' => 'payment_info',
            'config' => [
                'methods' => ['credit_card', 'pix', 'boleto'],
                'installments' => [
                    'enabled' => true,
                    'max' => 12,
                    'min_value' => 5000 // R$ 50,00 mínimo por parcela
                ]
            ]
        ],
        [
            'type' => 'order_review',
            'config' => [
                'show_summary' => true,
                'allow_edit' => true
            ]
        ],
        [
            'type' => 'order_confirmation',
            'config' => [
                'redirect_url' => 'https://example.com/thank-you',
                'show_download' => true
            ]
        ]
    ],
    'config' => [
        'auto_advance' => true,
        'save_progress' => true,
        'session_timeout' => 1800 // 30 minutos
    ]
];

try {
    echo "Criando flow de checkout: " . $flowData['name'] . "\n";

    // Criar flow
    $flow = $sdk->checkout()->flow()->create($organizationId, $flowData);

    echo "✅ Flow criado com sucesso!\n";
    $flowId = $flow['id'] ?? $flow['_id'];
    echo "   ID: " . $flowId . "\n";
    echo "   Nome: " . $flow['name'] . "\n";
    echo "   Tipo: " . $flow['type'] . "\n";
    echo "   Oferta: " . $offerId . "\n";
    echo "   Steps: " . count($flowData['steps']) . "\n";

    // Listar steps do flow
    echo "   \n   Steps configurados:\n";
    foreach ($flowData['steps'] as $index => $step) {
        echo "   " . ($index + 1) . ". " . $step['type'] . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao criar flow: " . $e->getMessage() . "\n";
    echo "   Detalhes: " . json_encode($e->getTrace()[0] ?? [], JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// ===== PASSO 6: Alternância de Tenant (Opcional) =====
echo "🔄 PASSO 6: Alternância de Tenant (Organization Scope)\n";
echo "-------------------------------------------------------\n";

// Este passo demonstra como alternar entre tenants quando você tem organization scope
// Isso é útil quando precisa gerenciar recursos em múltiplos tenants

echo "ℹ️  Demonstração de alternância de tenant:\n\n";

echo "Cenário: Organization API Key com scope 'organization'\n";
echo "   - Acesso atual: Tenant $tenantId\n";
echo "   - Alternativa: Pode trocar para qualquer tenant da organização\n\n";

echo "Para alternar de tenant, você pode:\n\n";

echo "1️⃣ Método 1: Re-autenticar com novo tenant\n";
echo "   \$sdk->authenticateWithOrganizationApiKey(\$orgId, \$apiKey, \$newTenantId);\n\n";

echo "2️⃣ Método 2: Atualizar configuração manualmente\n";
echo "   \$sdk->getConfig()->set('tenant_id', \$newTenantId);\n";
echo "   // Próximas requisições usarão o novo tenant\n\n";

echo "3️⃣ Método 3: Usar SDK dedicado por tenant\n";
echo "   \$tenant1Sdk = new ClubifyCheckoutSDK([...]);\n";
echo "   \$tenant2Sdk = new ClubifyCheckoutSDK([...]);\n\n";

echo "⚠️  IMPORTANTE:\n";
echo "   - Cross-tenant keys: Só podem acessar tenants na lista de accessible_tenants\n";
echo "   - Tenant keys: Só podem acessar o tenant específico\n";
echo "   - Organization keys: Podem acessar todos os tenants\n\n";

// Exemplo prático (comentado para não executar)
echo "💡 Exemplo prático (comentado):\n";
echo "/*\n";
echo "// Alternar para outro tenant\n";
echo "\$anotherTenantId = '68da8f00378f93bd3931ad66';\n";
echo "\$sdk->authenticateWithOrganizationApiKey(\n";
echo "    \$organizationId,\n";
echo "    \$organizationApiKey,\n";
echo "    \$anotherTenantId\n";
echo ");\n\n";
echo "// Agora todas as operações serão no contexto do novo tenant\n";
echo "\$productsFromAnotherTenant = \$sdk->products()->list();\n";
echo "*/\n\n";

echo "\n";

// ===== PASSO 7: Resumo Final =====
echo "📊 PASSO 7: Resumo Final\n";
echo "========================\n";

try {
    // Listar produtos da organização
    $products = $sdk->products()->list(['limit' => 5]);
    echo "✅ Produtos cadastrados: " . count($products['data'] ?? []) . "\n";

    // Listar ofertas da organização
    $offers = $sdk->offer()->list(['limit' => 5]);
    echo "✅ Ofertas cadastradas: " . count($offers['data'] ?? []) . "\n";

    // Informações do contexto (usando dados da autenticação)
    echo "✅ Organization ID: " . $authResult['organization_id'] . "\n";
    echo "✅ Scope: " . $authResult['scope'] . "\n";
    echo "✅ Permissions: " . implode(', ', array_slice($authResult['permissions'], 0, 5)) .
         (count($authResult['permissions']) > 5 ? '...' : '') . "\n";

} catch (Exception $e) {
    echo "⚠️  Erro ao buscar resumo: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Casos de Uso Práticos =====
echo "💡 CASOS DE USO PRÁTICOS\n";
echo "=========================\n\n";

echo "✅ Cenários implementados neste exemplo:\n\n";

echo "1. 🔐 AUTENTICAÇÃO ORGANIZACIONAL:\n";
echo "   - Organization API Key garante acesso multi-tenant\n";
echo "   - Access token com escopo organizacional\n";
echo "   - Tenant context configurado na autenticação\n";
echo "   - Contexto mantido durante toda a sessão\n\n";

echo "2. 📦 GESTÃO DE PRODUTOS:\n";
echo "   - Criação de produtos digitais\n";
echo "   - Verificação de duplicatas\n";
echo "   - Configuração de preços e moeda\n";
echo "   - Produtos criados no contexto do tenant\n\n";

echo "3. 🎯 CONFIGURAÇÃO DE OFERTAS:\n";
echo "   - Vinculação de produtos a ofertas\n";
echo "   - Personalização de tema (cores, fontes)\n";
echo "   - Configuração de layout e elementos visuais\n";
echo "   - Garantias e limites de compra\n\n";

echo "4. 🔄 CRIAÇÃO DE FLOWS:\n";
echo "   - Flow personalizado de checkout\n";
echo "   - Múltiplos steps configuráveis\n";
echo "   - Métodos de pagamento e parcelamento\n";
echo "   - Validações e campos obrigatórios\n\n";

echo "5. 🔀 ALTERNÂNCIA DE TENANT:\n";
echo "   - Demonstração de como trocar entre tenants\n";
echo "   - Três métodos diferentes de alternância\n";
echo "   - Considerações de segurança por tipo de key\n\n";

echo "🎉 Exemplo concluído com sucesso!\n\n";

echo "📚 Próximos passos:\n";
echo "  1. Integrar webhook para receber notificações de pagamento\n";
echo "  2. Implementar upsells e order bumps na oferta\n";
echo "  3. Configurar automações de email marketing\n";
echo "  4. Adicionar analytics e tracking de conversão\n";
echo "  5. Implementar split de pagamento (se aplicável)\n\n";

?>