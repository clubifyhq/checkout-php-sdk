<?php
require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "Testing Clubify Checkout SDK - ALL METHODS COMPREHENSIVE TEST\n";
echo "=============================================================\n";

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
    echo "✅ SDK initialized successfully!\n\n";

    // Contadores para estatísticas
    $totalMethods = 0;
    $workingMethods = 0;
    $errorMethods = 0;
    $results = [];

    // ====================================
    // ORGANIZATION MODULE (17 métodos)
    // ====================================
    echo "🏢 ORGANIZATION MODULE TESTS:\n";
    echo "============================\n";
    $org = $sdk->organization();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($org, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($org, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($org, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($org, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($org, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($org, 'getStatus', [], 'array');

    // Métodos específicos do OrganizationModule
    $moduleResults['getRepository'] = testMethod($org, 'getRepository', [], 'object');
    $moduleResults['tenant'] = testMethod($org, 'tenant', [], 'object');
    $moduleResults['admin'] = testMethod($org, 'admin', [], 'object');
    $moduleResults['apiKey'] = testMethod($org, 'apiKey', [], 'object');
    $moduleResults['domain'] = testMethod($org, 'domain', [], 'object');

    // Métodos de negócio
    $moduleResults['setupOrganization'] = testMethod($org, 'setupOrganization', [[
        'name' => 'Test Org',
        'admin_name' => 'Admin Test',
        'admin_email' => 'admin@test.com'
    ]], 'array');
    $moduleResults['setupComplete'] = testMethod($org, 'setupComplete', [[
        'name' => 'Complete Org',
        'admin_name' => 'Admin Complete',
        'admin_email' => 'admin@complete.com'
    ]], 'array');

    $results['OrganizationModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // PRODUCTS MODULE (13 métodos)
    // ====================================
    echo "📦 PRODUCTS MODULE TESTS:\n";
    echo "=========================\n";
    $products = $sdk->products();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($products, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($products, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($products, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($products, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($products, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($products, 'getStatus', [], 'array');
    $moduleResults['cleanup'] = testMethod($products, 'cleanup', [], 'void');

    // Métodos específicos do ProductsModule
    $moduleResults['isHealthy'] = testMethod($products, 'isHealthy', [], 'boolean');
    $moduleResults['getStats'] = testMethod($products, 'getStats', [], 'array');

    // Métodos de negócio
    $moduleResults['setupComplete'] = testMethod($products, 'setupComplete', [[
        'name' => 'Test Product',
        'price' => 99.99
    ]], 'array');
    $moduleResults['createComplete'] = testMethod($products, 'createComplete', [[
        'name' => 'Complete Product',
        'price' => 199.99
    ]], 'array');

    $results['ProductsModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // CHECKOUT MODULE (16 métodos)
    // ====================================
    echo "🛒 CHECKOUT MODULE TESTS:\n";
    echo "=========================\n";
    $checkout = $sdk->checkout();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($checkout, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($checkout, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($checkout, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($checkout, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($checkout, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($checkout, 'getStatus', [], 'array');
    $moduleResults['cleanup'] = testMethod($checkout, 'cleanup', [], 'void');

    // Métodos específicos do CheckoutModule
    $moduleResults['isHealthy'] = testMethod($checkout, 'isHealthy', [], 'boolean');
    $moduleResults['getStats'] = testMethod($checkout, 'getStats', [], 'array');

    // Métodos de negócio
    $moduleResults['createSession'] = testMethod($checkout, 'createSession', [[
        'product_id' => 'test_prod_123',
        'customer_email' => 'customer@test.com'
    ]], 'array');
    $moduleResults['setupComplete'] = testMethod($checkout, 'setupComplete', [[
        'product_id' => 'setup_prod_123',
        'customer_email' => 'setup@test.com'
    ]], 'array');
    $moduleResults['createComplete'] = testMethod($checkout, 'createComplete', [[
        'product_id' => 'complete_prod_123',
        'customer_email' => 'complete@test.com'
    ]], 'array');
    $moduleResults['oneClick'] = testMethod($checkout, 'oneClick', [[
        'payment_method' => 'credit_card',
        'amount' => 99.99
    ]], 'array');

    $results['CheckoutModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // PAYMENTS MODULE (16 métodos)
    // ====================================
    echo "💳 PAYMENTS MODULE TESTS:\n";
    echo "=========================\n";
    $payments = $sdk->payments();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($payments, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($payments, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($payments, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($payments, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($payments, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($payments, 'getStatus', [], 'array');
    $moduleResults['cleanup'] = testMethod($payments, 'cleanup', [], 'void');

    // Métodos específicos do PaymentsModule
    $moduleResults['isHealthy'] = testMethod($payments, 'isHealthy', [], 'boolean');
    $moduleResults['getStats'] = testMethod($payments, 'getStats', [], 'array');

    // Métodos de negócio
    $moduleResults['processPayment'] = testMethod($payments, 'processPayment', [[
        'method' => 'pix',
        'amount' => 50.00,
        'currency' => 'BRL'
    ]], 'array');
    $moduleResults['setupComplete'] = testMethod($payments, 'setupComplete', [[
        'gateway' => 'stripe',
        'amount' => 100.00
    ]], 'array');
    $moduleResults['createComplete'] = testMethod($payments, 'createComplete', [[
        'gateway' => 'pagarme',
        'amount' => 150.00
    ]], 'array');
    $moduleResults['tokenizeCard'] = testMethod($payments, 'tokenizeCard', [[
        'number' => '4111111111111111',
        'brand' => 'visa',
        'exp_month' => '12',
        'exp_year' => '2025'
    ]], 'array');

    $results['PaymentsModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // CUSTOMERS MODULE (17 métodos)
    // ====================================
    echo "👥 CUSTOMERS MODULE TESTS:\n";
    echo "==========================\n";
    $customers = $sdk->customers();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($customers, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($customers, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($customers, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($customers, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($customers, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($customers, 'getStatus', [], 'array');
    $moduleResults['cleanup'] = testMethod($customers, 'cleanup', [], 'void');

    // Métodos específicos do CustomersModule
    $moduleResults['isHealthy'] = testMethod($customers, 'isHealthy', [], 'boolean');
    $moduleResults['getStats'] = testMethod($customers, 'getStats', [], 'array');

    // Métodos de negócio
    $moduleResults['createCustomer'] = testMethod($customers, 'createCustomer', [[
        'name' => 'Test Customer',
        'email' => 'test@customer.com'
    ]], 'array');
    $moduleResults['setupComplete'] = testMethod($customers, 'setupComplete', [[
        'name' => 'Setup Customer',
        'email' => 'setup@customer.com'
    ]], 'array');
    $moduleResults['createComplete'] = testMethod($customers, 'createComplete', [[
        'name' => 'Complete Customer',
        'email' => 'complete@customer.com'
    ]], 'array');
    $moduleResults['findByEmail'] = testMethod($customers, 'findByEmail', ['test@email.com'], 'array');
    $moduleResults['updateProfile'] = testMethod($customers, 'updateProfile', [
        'customer_123',
        ['name' => 'Updated Name']
    ], 'array');

    $results['CustomersModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // WEBHOOKS MODULE (15 métodos)
    // ====================================
    echo "🔗 WEBHOOKS MODULE TESTS:\n";
    echo "=========================\n";
    $webhooks = $sdk->webhooks();
    $moduleResults = [];

    // Métodos da Interface ModuleInterface
    $moduleResults['getName'] = testMethod($webhooks, 'getName', [], 'string');
    $moduleResults['getVersion'] = testMethod($webhooks, 'getVersion', [], 'string');
    $moduleResults['getDependencies'] = testMethod($webhooks, 'getDependencies', [], 'array');
    $moduleResults['isInitialized'] = testMethod($webhooks, 'isInitialized', [], 'boolean');
    $moduleResults['isAvailable'] = testMethod($webhooks, 'isAvailable', [], 'boolean');
    $moduleResults['getStatus'] = testMethod($webhooks, 'getStatus', [], 'array');
    $moduleResults['cleanup'] = testMethod($webhooks, 'cleanup', [], 'void');

    // Métodos específicos do WebhooksModule
    $moduleResults['isHealthy'] = testMethod($webhooks, 'isHealthy', [], 'boolean');
    $moduleResults['getStats'] = testMethod($webhooks, 'getStats', [], 'array');

    // Métodos de negócio
    $moduleResults['configureWebhook'] = testMethod($webhooks, 'configureWebhook', [[
        'url' => 'https://example.com/webhook',
        'events' => ['order.created', 'payment.completed']
    ]], 'array');
    $moduleResults['sendEvent'] = testMethod($webhooks, 'sendEvent', [
        'payment.completed',
        ['payment_id' => 'pay_123', 'amount' => 100.00]
    ], 'array');
    $moduleResults['listWebhooks'] = testMethod($webhooks, 'listWebhooks', [], 'array');

    $results['WebhooksModule'] = $moduleResults;
    list($working, $errors) = countResults($moduleResults);
    $workingMethods += $working;
    $errorMethods += $errors;
    $totalMethods += count($moduleResults);
    echo "\n";

    // ====================================
    // RESUMO FINAL
    // ====================================
    echo "📊 COMPREHENSIVE TEST SUMMARY:\n";
    echo "===============================\n";
    echo "Total Methods Tested: {$totalMethods}\n";
    echo "✅ Working Methods: {$workingMethods}\n";
    echo "❌ Methods with Errors: {$errorMethods}\n";
    echo "Success Rate: " . round(($workingMethods / $totalMethods) * 100, 2) . "%\n\n";

    if ($errorMethods > 0) {
        echo "🚨 METHODS WITH ERRORS:\n";
        echo "========================\n";
        foreach ($results as $moduleName => $moduleResults) {
            foreach ($moduleResults as $methodName => $result) {
                if (!$result['success']) {
                    echo "❌ {$moduleName}::{$methodName} - {$result['error']}\n";
                }
            }
        }
        echo "\n";
    }

    echo "🎉 COMPREHENSIVE TEST COMPLETED!\n";

} catch (Exception $e) {
    echo "❌ Critical SDK Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// ====================================
// FUNÇÕES AUXILIARES
// ====================================

function testMethod($object, $methodName, $params, $expectedType) {
    try {
        $result = call_user_func_array([$object, $methodName], $params);

        // Verificar tipo de retorno
        $actualType = gettype($result);
        if ($expectedType === 'void') {
            $typeMatch = true;
        } elseif ($expectedType === 'object') {
            $typeMatch = is_object($result);
        } else {
            $typeMatch = ($actualType === $expectedType);
        }

        if ($typeMatch) {
            echo "   ✅ {$methodName}(): " . formatReturnValue($result, $expectedType) . "\n";
            return ['success' => true, 'result' => $result];
        } else {
            echo "   ⚠️  {$methodName}(): Expected {$expectedType}, got {$actualType}\n";
            return ['success' => false, 'error' => "Type mismatch: expected {$expectedType}, got {$actualType}"];
        }
    } catch (Exception $e) {
        echo "   ❌ {$methodName}(): Error - " . $e->getMessage() . "\n";
        return ['success' => false, 'error' => $e->getMessage()];
    } catch (TypeError $e) {
        echo "   ❌ {$methodName}(): Type Error - " . $e->getMessage() . "\n";
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function formatReturnValue($value, $expectedType) {
    if ($expectedType === 'void') {
        return 'OK';
    } elseif ($expectedType === 'boolean') {
        return $value ? 'true' : 'false';
    } elseif ($expectedType === 'string') {
        return substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '');
    } elseif ($expectedType === 'array' && isset($value['id'])) {
        return 'ID: ' . $value['id'];
    } elseif ($expectedType === 'array' && isset($value['success'])) {
        return 'Success: ' . ($value['success'] ? 'true' : 'false');
    } elseif ($expectedType === 'array') {
        return 'Array with ' . count($value) . ' items';
    } elseif ($expectedType === 'object') {
        return get_class($value);
    } else {
        return (string)$value;
    }
}

function countResults($moduleResults) {
    $working = 0;
    $errors = 0;
    foreach ($moduleResults as $result) {
        if ($result['success']) {
            $working++;
        } else {
            $errors++;
        }
    }
    return [$working, $errors];
}
?>