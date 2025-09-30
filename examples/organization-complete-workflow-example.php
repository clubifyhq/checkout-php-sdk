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
    'type' => 'single', // Tipos: single, combo, subscription, bundle
    'products' => [
        [
            'productId' => $productId,
            'quantity' => 1,
            'position' => 0,
            'isOptional' => false,
            'discountType' => 'percentage',
            'discountValue' => 10 // 10% de desconto
        ]
    ],
    'settings' => [
        'allowQuantityChange' => true,
        'maxQuantity' => 10,
        'minQuantity' => 1,
        'requiresShipping' => false,
        'taxable' => true,
        'collectCustomerInfo' => [
            'name' => true,
            'email' => true,
            'phone' => true,
            'address' => false,
            'cpf' => true
        ]
    ],
    'theme' => [
        'name' => 'modern', // Opções: light, dark, modern, premium, custom
        'colors' => [
            'primary' => '#007bff',
            'secondary' => '#6c757d',
            'background' => '#ffffff',
            'surface' => '#f9fafb',
            'text' => '#212529',
            'textSecondary' => '#6c757d',
            'border' => '#e5e7eb',
            'success' => '#28a745',
            'warning' => '#ffc107',
            'error' => '#dc3545'
        ],
        'typography' => [
            'fontFamily' => 'Roboto, sans-serif',
            'headingScale' => 1.25,
            'lineHeight' => 1.6
        ],
        'spacing' => [
            'unit' => 8 // Base spacing em pixels
        ],
        'borderRadius' => [
            'small' => 4,
            'medium' => 8,
            'large' => 12
        ],
        'shadows' => [
            'small' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            'medium' => '0 4px 6px -1px rgb(0 0 0 / 0.1)',
            'large' => '0 10px 15px -3px rgb(0 0 0 / 0.1)'
        ],
        'customCSS' => '.checkout-button { transition: all 0.3s ease; }'
    ],
    'layout' => [
        'structure' => 'single-column', // single-column, two-column, default
        'showHeader' => true,
        'showFooter' => true,
        'showProgress' => false,
        'enableAnimations' => true
    ],
    'seo' => [
        'title' => 'Curso Completo de Marketing Digital - Oferta Especial',
        'description' => 'Aprenda marketing digital do zero ao avançado com desconto especial',
        'keywords' => ['marketing digital', 'curso online', 'vendas online'],
        'ogImage' => 'https://exemplo.com/imagem-oferta.jpg'
    ],
    'tracking' => [
        'googleAnalyticsId' => 'UA-XXXXXXXXX-X',
        'facebookPixelId' => '1234567890',
        'customScripts' => []
    ],
    'metadata' => [
        'campaign' => 'black-friday-2024',
        'source' => 'sdk-example',
        'version' => '1.0'
    ],
    'isActive' => true
];

try {
    echo "Criando oferta: " . $offerData['name'] . "\n";

    // Verificar se a oferta já existe
    $existingOffers = $sdk->offer()->offers()->list([
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
        $offer = $sdk->offer()->offers()->create($offerData);
        echo "✅ Oferta criada com sucesso!\n";
    }

    $offerId = $offer['id'] ?? $offer['_id'];
    echo "   ID: " . $offerId . "\n";
    echo "   Nome: " . $offer['name'] . "\n";
    echo "   Tipo: " . $offer['type'] . "\n";
    echo "   Slug: " . ($offer['slug'] ?? 'não definido') . "\n";
    echo "   Status: " . ($offer['status'] ?? 'draft') . "\n";
    echo "   Ativa: " . (($offer['isActive'] ?? false) ? 'Sim' : 'Não') . "\n";

    // Mostrar configurações aplicadas
    if (isset($offer['settings'])) {
        echo "   Settings: Configurado ✅\n";
    }
    if (isset($offer['theme'])) {
        echo "   Theme: Configurado ✅\n";
    }
    if (isset($offer['layout'])) {
        echo "   Layout: Configurado ✅\n";
    }
    if (isset($offer['seo'])) {
        echo "   SEO: Configurado ✅\n";
    }
    if (isset($offer['tracking'])) {
        echo "   Tracking: Configurado ✅\n";
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
    'description' => 'Flow completo para venda de cursos digitais com múltiplas etapas e personalização',
    'version' => '1.0.0',
    'status' => 'draft', // draft, testing, active, paused, archived
    'isDefault' => false,
    'isActive' => true,
    'deviceTarget' => 'both', // mobile, desktop, both
    'tags' => ['marketing', 'digital', 'curso'],

    // Configuração completa do fluxo
    'flowConfig' => [
        'startStep' => 'step-information',

        'steps' => [
            'step-information' => [
                'id' => 'step-information',
                'name' => 'Informações do Cliente',
                'type' => 'information',
                'component' => 'InformationStep',
                'ui' => [
                    'title' => 'Seus Dados',
                    'subtitle' => 'Preencha suas informações para continuar',
                    'showProgress' => true,
                    'progressType' => 'steps',
                    'showBackButton' => false,
                    'nextButtonText' => 'Continuar para Pagamento',
                    'layout' => [
                        'columns' => 1,
                        'spacing' => 'normal',
                        'alignment' => 'left',
                        'width' => 'normal'
                    ],
                    'styling' => [
                        'backgroundColor' => '#ffffff',
                        'textColor' => '#212529',
                        'primaryColor' => '#007bff',
                        'secondaryColor' => '#6c757d',
                        'borderRadius' => 8,
                        'shadow' => 'medium'
                    ]
                ],
                'fields' => [
                    [
                        'id' => 'name',
                        'type' => 'text',
                        'name' => 'name',
                        'label' => 'Nome Completo',
                        'placeholder' => 'Digite seu nome completo',
                        'required' => true,
                        'validation' => [
                            'minLength' => 3,
                            'maxLength' => 100
                        ],
                        'styling' => [
                            'width' => '100%'
                        ]
                    ],
                    [
                        'id' => 'email',
                        'type' => 'email',
                        'name' => 'email',
                        'label' => 'E-mail',
                        'placeholder' => 'seu@email.com',
                        'required' => true,
                        'validation' => [
                            'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
                        ],
                        'styling' => [
                            'width' => '100%'
                        ]
                    ],
                    [
                        'id' => 'phone',
                        'type' => 'phone',
                        'name' => 'phone',
                        'label' => 'Telefone/WhatsApp',
                        'placeholder' => '(00) 00000-0000',
                        'required' => true,
                        'validation' => [
                            'minLength' => 10,
                            'maxLength' => 15
                        ],
                        'styling' => [
                            'width' => '100%'
                        ]
                    ],
                    [
                        'id' => 'cpf',
                        'type' => 'text',
                        'name' => 'cpf',
                        'label' => 'CPF',
                        'placeholder' => '000.000.000-00',
                        'required' => false,
                        'validation' => [
                            'pattern' => '^\d{3}\.\d{3}\.\d{3}-\d{2}$'
                        ],
                        'styling' => [
                            'width' => '100%'
                        ]
                    ]
                ],
                'transitions' => [
                    [
                        'condition' => [
                            'type' => 'always'
                        ],
                        'target' => 'step-payment',
                        'priority' => 1
                    ]
                ],
                'validations' => [
                    [
                        'type' => 'required_fields',
                        'fields' => ['name', 'email', 'phone'],
                        'message' => 'Por favor, preencha todos os campos obrigatórios'
                    ]
                ]
            ],

            'step-payment' => [
                'id' => 'step-payment',
                'name' => 'Pagamento',
                'type' => 'payment',
                'component' => 'PaymentStep',
                'ui' => [
                    'title' => 'Forma de Pagamento',
                    'subtitle' => 'Escolha como deseja pagar',
                    'showProgress' => true,
                    'progressType' => 'steps',
                    'showBackButton' => true,
                    'backButtonText' => 'Voltar',
                    'nextButtonText' => 'Finalizar Compra',
                    'layout' => [
                        'columns' => 1,
                        'spacing' => 'normal',
                        'alignment' => 'left',
                        'width' => 'normal'
                    ],
                    'styling' => [
                        'backgroundColor' => '#ffffff',
                        'textColor' => '#212529',
                        'primaryColor' => '#007bff',
                        'secondaryColor' => '#6c757d',
                        'borderRadius' => 8,
                        'shadow' => 'medium'
                    ]
                ],
                'stepConfig' => [
                    'methods' => ['credit_card', 'pix', 'boleto'],
                    'installments' => [
                        'enabled' => true,
                        'max' => 12,
                        'min_value' => 5000
                    ]
                ],
                'transitions' => [
                    [
                        'condition' => [
                            'type' => 'always'
                        ],
                        'target' => 'step-review',
                        'priority' => 1
                    ]
                ],
                'validations' => [
                    [
                        'type' => 'payment_method',
                        'message' => 'Por favor, selecione uma forma de pagamento'
                    ]
                ]
            ],

            'step-review' => [
                'id' => 'step-review',
                'name' => 'Revisão do Pedido',
                'type' => 'review',
                'component' => 'ReviewStep',
                'ui' => [
                    'title' => 'Revise seu Pedido',
                    'subtitle' => 'Confira os detalhes antes de finalizar',
                    'showProgress' => true,
                    'progressType' => 'steps',
                    'showBackButton' => true,
                    'backButtonText' => 'Voltar',
                    'nextButtonText' => 'Confirmar Compra',
                    'layout' => [
                        'columns' => 1,
                        'spacing' => 'normal',
                        'alignment' => 'left',
                        'width' => 'normal'
                    ],
                    'styling' => [
                        'backgroundColor' => '#ffffff',
                        'textColor' => '#212529',
                        'primaryColor' => '#007bff',
                        'secondaryColor' => '#6c757d',
                        'borderRadius' => 8,
                        'shadow' => 'medium'
                    ]
                ],
                'stepConfig' => [
                    'show_summary' => true,
                    'allow_edit' => true
                ],
                'transitions' => [
                    [
                        'condition' => [
                            'type' => 'always'
                        ],
                        'target' => 'step-confirmation',
                        'priority' => 1
                    ]
                ]
            ],

            'step-confirmation' => [
                'id' => 'step-confirmation',
                'name' => 'Confirmação',
                'type' => 'final',
                'component' => 'ConfirmationStep',
                'ui' => [
                    'title' => 'Pedido Confirmado!',
                    'subtitle' => 'Obrigado pela sua compra',
                    'showProgress' => false,
                    'progressType' => 'none',
                    'showBackButton' => false,
                    'nextButtonText' => 'Acessar Curso',
                    'layout' => [
                        'columns' => 1,
                        'spacing' => 'normal',
                        'alignment' => 'center',
                        'width' => 'normal'
                    ],
                    'styling' => [
                        'backgroundColor' => '#ffffff',
                        'textColor' => '#212529',
                        'primaryColor' => '#28a745',
                        'secondaryColor' => '#6c757d',
                        'borderRadius' => 8,
                        'shadow' => 'medium'
                    ]
                ],
                'stepConfig' => [
                    'redirect_url' => 'https://example.com/thank-you',
                    'show_download' => true
                ],
                'transitions' => []
            ]
        ],

        'globalSettings' => [
            'allowSkipSteps' => false,
            'saveProgress' => true,
            'progressStorageType' => 'localStorage',
            'showExitConfirmation' => true,
            'exitConfirmationMessage' => 'Tem certeza que deseja sair? Seu progresso será salvo.',
            'sessionTimeout' => 1800,
            'routing' => [
                'baseUrl' => '/checkout',
                'useHashRouting' => false,
                'stepUrlFormat' => 'step/{stepId}',
                'preserveQueryParams' => true
            ],
            'abandonment' => [
                'trackAbandonment' => true,
                'abandonmentTimeout' => 600,
                'recoveryEmail' => [
                    'enabled' => true,
                    'delayMinutes' => 30
                ],
                'retargeting' => [
                    'enabled' => true,
                    'pixels' => []
                ]
            ]
        ]
    ],

    // Funções customizadas (opcional)
    'customFunctions' => [
        'validateCPF' => [
            'code' => 'function(cpf) { /* validação de CPF */ return true; }',
            'description' => 'Valida um CPF brasileiro',
            'parameters' => [
                [
                    'name' => 'cpf',
                    'type' => 'string',
                    'required' => true
                ]
            ]
        ]
    ],

    // Localizações (opcional)
    'localizations' => [
        'pt-BR' => [
            'steps' => [
                'step-information' => [
                    'ui' => [
                        'title' => 'Seus Dados',
                        'subtitle' => 'Preencha suas informações para continuar'
                    ]
                ]
            ]
        ]
    ],

    // Metadata adicional
    'metadata' => [
        'campaign' => 'black-friday-2024',
        'source' => 'sdk-example',
        'version' => '1.0',
        'notes' => 'Flow de exemplo completo criado via SDK'
    ]
];

try {
    echo "Criando flow de checkout: " . $flowData['name'] . "\n";

    // Criar flow para a oferta
    // Nota: Flow é criado para uma oferta específica no cart-service
    $flow = $sdk->checkout()->flow()->create($offerId, $flowData);

    echo "✅ Flow criado com sucesso!\n";
    $flowId = $flow['id'] ?? $flow['_id'];
    echo "   ID: " . $flowId . "\n";
    echo "   Nome: " . $flow['name'] . "\n";
    echo "   Versão: " . $flow['version'] . "\n";
    echo "   Status: " . $flow['status'] . "\n";
    echo "   Device Target: " . $flow['deviceTarget'] . "\n";
    echo "   Oferta: " . $offerId . "\n";

    // Listar steps do flow
    if (isset($flow['flowConfig']['steps'])) {
        $steps = $flow['flowConfig']['steps'];
        echo "   Steps: " . count($steps) . "\n\n";
        echo "   Steps configurados:\n";
        $stepNumber = 1;
        foreach ($steps as $stepId => $step) {
            echo "   " . $stepNumber . ". " . $step['name'] . " (" . $step['type'] . ")\n";
            echo "      ID: " . $stepId . "\n";
            echo "      Component: " . $step['component'] . "\n";
            $stepNumber++;
        }
    }

    // Mostrar configurações globais
    if (isset($flow['flowConfig']['globalSettings'])) {
        echo "\n   Configurações Globais:\n";
        $settings = $flow['flowConfig']['globalSettings'];
        echo "      - Save Progress: " . ($settings['saveProgress'] ? 'Sim' : 'Não') . "\n";
        echo "      - Session Timeout: " . $settings['sessionTimeout'] . "s\n";
        echo "      - Track Abandonment: " . ($settings['abandonment']['trackAbandonment'] ? 'Sim' : 'Não') . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao criar flow: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== PASSO 6: Ativar Oferta =====
echo "✅ PASSO 6: Ativar Oferta\n";
echo "-------------------------\n";

try {
    echo "Ativando oferta: " . $offerId . "\n";

    // Ativar a oferta para que ela possa ser usada
    $activated = $sdk->offer()->offers()->activate($offerId);

    if ($activated) {
        echo "✅ Oferta ativada com sucesso!\n";
        echo "   ID: " . $offerId . "\n";
        echo "   Status: active\n";
    } else {
        echo "⚠️  Não foi possível ativar a oferta\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao ativar oferta: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== PASSO 7: Publicar/Ativar Flow =====
echo "🚀 PASSO 7: Publicar Flow\n";
echo "-------------------------\n";

try {
    if (isset($flowId)) {
        echo "Publicando flow: " . $flowId . "\n";

        // Publicar o flow (muda status de 'draft' para 'active')
        $publishResult = $sdk->checkout()->flow()->publish($flowId);

        echo "✅ Flow publicado com sucesso!\n";
        echo "   Flow ID: " . ($publishResult['flowId'] ?? $flowId) . "\n";
        echo "   Status: active\n";
        echo "   Publicado em: " . ($publishResult['publishedAt'] ?? date('Y-m-d H:i:s')) . "\n";

        echo "\n   ⚡ O flow agora está ativo e pronto para receber checkout!\n";
    } else {
        echo "⚠️  Flow ID não disponível para publicação\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao publicar flow: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== PASSO 8: Alternância de Tenant (Opcional) =====
echo "🔄 PASSO 8: Alternância de Tenant (Organization Scope)\n";
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

// ===== PASSO 9: Resumo Final =====
echo "📊 PASSO 9: Resumo Final\n";
echo "========================\n";

try {
    // Listar produtos da organização
    $products = $sdk->products()->list(['limit' => 5]);
    echo "✅ Produtos cadastrados: " . count($products['data'] ?? []) . "\n";

    // Listar ofertas da organização
    $offers = $sdk->offer()->offers()->list(['limit' => 5]);
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
echo "   - Flow personalizado de checkout com 4 steps\n";
echo "   - Configuração completa de UI e validações\n";
echo "   - Múltiplos steps configuráveis (information, payment, review, confirmation)\n";
echo "   - Métodos de pagamento e parcelamento\n";
echo "   - Campos customizados com validação\n";
echo "   - Transições e condições entre steps\n";
echo "   - Configurações globais (progress, routing, abandonment)\n\n";

echo "5. ✅ ATIVAÇÃO DE OFERTA:\n";
echo "   - Mudança de status da oferta para 'active'\n";
echo "   - Oferta disponível para uso em checkouts\n";
echo "   - Validação de regras de negócio\n\n";

echo "6. 🚀 PUBLICAÇÃO DE FLOW:\n";
echo "   - Mudança de status do flow de 'draft' para 'active'\n";
echo "   - Flow validado e pronto para produção\n";
echo "   - Data de publicação registrada\n";
echo "   - Flow disponível para receber checkouts\n\n";

echo "7. 🔀 ALTERNÂNCIA DE TENANT:\n";
echo "   - Demonstração de como trocar entre tenants\n";
echo "   - Três métodos diferentes de alternância\n";
echo "   - Considerações de segurança por tipo de key\n\n";

echo "8. 🔗 GERAÇÃO DE URLs DE CHECKOUT:\n";
echo "   - URL completa de checkout com tracking UTM\n";
echo "   - Short URL para compartilhamento\n";
echo "   - QR Code para acesso mobile\n";
echo "   - Opções de expiração (data ou número de usos)\n";
echo "   - Proteção por senha (opcional)\n";
echo "   - Listagem de todas as URLs geradas\n\n";

echo "🎉 Exemplo concluído com sucesso!\n\n";

echo "📚 Próximos passos:\n";
echo "  1. Testar o flow de checkout acessando a URL da oferta\n";
echo "  2. Integrar webhook para receber notificações de pagamento\n";
echo "  3. Implementar upsells e order bumps na oferta\n";
echo "  4. Configurar automações de email marketing\n";
echo "  5. Adicionar analytics e tracking de conversão\n";
echo "  6. Implementar split de pagamento (se aplicável)\n";
echo "  7. Configurar testes A/B para otimização de conversão\n\n";

echo "🔗 Geração de URLs de Checkout:\n";
echo "=================================\n\n";

if (isset($offerId)) {
    try {
        // Gerar URL de checkout para a oferta com UTM parameters e opções avançadas
        echo "Gerando URL de checkout com tracking...\n";

        // Opções disponíveis para geração de URL:
        // - customDomain: Domínio customizado para a URL
        // - slug: Slug customizado (auto-gerado se não fornecido)
        // - utmParams: Parâmetros UTM para tracking (source, medium, campaign, term, content)
        // - expirationType: 'never' | 'date' | 'usage'
        // - expirationDate: Data de expiração (se expirationType = 'date')
        // - maxUsage: Número máximo de usos (se expirationType = 'usage')
        // - passwordProtected: true/false
        // - password: Senha para URLs protegidas

        $urlData = $sdk->offer()->offers()->generateCheckoutUrl($offerId, [
            'utmParams' => [
                'source' => 'sdk-example',
                'medium' => 'api',
                'campaign' => 'black-friday-2024',
                'content' => 'demo-workflow'
            ],
            // Exemplo: URL com expiração por uso
            // 'expirationType' => 'usage',
            // 'maxUsage' => 100,

            // Exemplo: URL com expiração por data
            // 'expirationType' => 'date',
            // 'expirationDate' => '2024-12-31T23:59:59Z',

            // Exemplo: URL protegida por senha
            // 'passwordProtected' => true,
            // 'password' => 'black-friday-2024',
        ]);

        echo "  ✅ Checkout URL: " . $urlData['checkoutUrl'] . "\n";
        echo "  ✅ Short URL: " . $urlData['shortUrl'] . "\n";
        echo "  ✅ QR Code: " . (isset($urlData['qrCode']) ? 'Gerado ✓' : 'N/A') . "\n";

        if (isset($urlData['metadata'])) {
            echo "  Metadata:\n";
            echo "    - Slug: " . ($urlData['metadata']['slug'] ?? 'N/A') . "\n";
            echo "    - Criado em: " . ($urlData['metadata']['createdAt'] ?? 'N/A') . "\n";
        }

        // Listar todas as URLs geradas para a oferta
        $allUrls = $sdk->offer()->offers()->getOfferUrls($offerId);
        if (!empty($allUrls)) {
            echo "\n  📋 Total de URLs geradas: " . count($allUrls) . "\n";
        }

    } catch (Exception $e) {
        echo "  ⚠️  Erro ao gerar URL: " . $e->getMessage() . "\n";
        
    }
}
echo "\n";

?>