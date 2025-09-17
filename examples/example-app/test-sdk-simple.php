<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "Testing Clubify Checkout SDK - Simple Test...\n";
echo "===========================================\n";

try {
    $config = [
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'api_secret' => 'demo_secret_456'
        ],
        'environment' => 'development',
        'api' => [
            'base_url' => 'https://checkout.svelve.com',
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
    echo "   Base URL: {$config['api']['base_url']}\n";
    echo "\n";

    // Inicializar SDK
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK initialized successfully!\n";

    // Informações básicas
    echo "   Version: " . $sdk->getVersion() . "\n";
    echo "   Authenticated: " . ($sdk->isAuthenticated() ? 'Yes' : 'No') . "\n";
    echo "   Initialized: " . ($sdk->isInitialized() ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Obter configuração
    $configuration = $sdk->getConfig();
    echo "🔧 Configuration Details:\n";
    echo "   Tenant ID: " . $configuration->getTenantId() . "\n";
    echo "   Environment: " . $configuration->getEnvironment() . "\n";
    echo "   Debug Mode: " . ($configuration->isDebugEnabled() ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Métodos de conveniência disponíveis
    echo "🚀 Available Convenience Methods:\n";
    echo "   - setupOrganization(array \$organizationData)\n";
    echo "   - createCompleteProduct(array \$productData)\n";
    echo "   - createCheckoutSession(array \$sessionData)\n";
    echo "   - processOneClick(array \$paymentData)\n";
    echo "\n";

    // Core components status
    echo "⚙️ Core Components Status:\n";
    $stats = $sdk->getStats();
    foreach ($stats as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'enabled' : 'disabled';
        }
        echo "   {$key}: {$value}\n";
    }

    echo "\n✅ SDK Core functionality working correctly!\n";
    echo "   Note: Module interfaces are still in development.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}