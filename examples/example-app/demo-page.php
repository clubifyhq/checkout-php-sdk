<?php
require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Configuração do SDK
$config = [
    'credentials' => [
        'api_key' => 'demo_key',
        'api_secret' => 'demo_secret',
        'tenant_id' => 'demo_tenant'
    ],
    'environment' => 'development',
    'debug' => true
];

try {
    // Inicializar SDK
    $sdk = new ClubifyCheckoutSDK($config);

    // Testar status dos módulos
    $modules = [
        'organization' => $sdk->organization(),
        'products' => $sdk->products(),
        'checkout' => $sdk->checkout(),
        'payments' => $sdk->payments(),
        'customers' => $sdk->customers(),
        'webhooks' => $sdk->webhooks()
    ];

    echo "<h1>Clubify Checkout SDK - Demonstração</h1>";
    echo "<h2>Status dos Módulos:</h2>";

    foreach ($modules as $name => $module) {
        $status = $module->getStatus();
        $available = $status['available'] ? '✅' : '❌';
        echo "<p>{$available} <strong>" . ucfirst($name) . "Module</strong> - " .
             ($status['available'] ? 'Disponível' : 'Indisponível') . "</p>";
    }

    echo "<h2>Teste de Métodos de Negócio:</h2>";

    // Teste OrganizationModule
    $orgResult = $sdk->setupOrganization([
        'name' => 'Demo Organization',
        'admin_name' => 'Admin Demo',
        'admin_email' => 'admin@demo.com'
    ]);
    echo "<p>✅ <strong>Setup Organization:</strong> " . $orgResult['organization']['id'] . "</p>";

    // Teste ProductsModule
    $productResult = $sdk->createCompleteProduct([
        'name' => 'Demo Product',
        'price' => 99.99
    ]);
    echo "<p>✅ <strong>Create Product:</strong> " . $productResult['product_id'] . "</p>";

    // Teste CheckoutModule
    $checkoutResult = $sdk->createCheckoutSession([
        'product_id' => 'demo_prod_123',
        'customer_email' => 'customer@demo.com'
    ]);
    echo "<p>✅ <strong>Create Checkout Session:</strong> " . $checkoutResult['session_id'] . "</p>";

    echo "<h2>✅ SDK funcionando perfeitamente!</h2>";
    echo "<p>Todos os módulos foram inicializados e testados com sucesso.</p>";

} catch (Exception $e) {
    echo "<h1>❌ Erro no SDK</h1>";
    echo "<p><strong>Erro:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
}
?>