<?php

namespace App\Http\Controllers;

// use App\Helpers\ClubifySDKHelper;
// use App\Helpers\ResponseHelper;
// use App\Helpers\ModuleTestHelper;
use Exception;

class ClubifyDemoController extends Controller
{
    // Controller simplificado usando helpers

    /**
     * Página principal de demonstração
     */
    public function index()
    {
        // Evitar chamadas ao SDK no index para debug
        return view('clubify.demo', [
            'sdkStatus' => 'Debug mode - SDK calls disabled',
            'config' => ['status' => 'debug_mode']
        ]);
    }

    /**
     * Debug de inicialização do SDK
     */
    public function debug()
    {
        return response()->json([
            'status' => 'working',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Debug endpoint is working correctly'
        ]);
    }

    /**
     * Teste de produtos
     */
    public function testProducts()
    {
        try {
            if (!ClubifySDKHelper::isAvailable()) {
                return ResponseHelper::error('SDK não está disponível', 500);
            }

            $sdk = ClubifySDKHelper::getInstance();
            $products = $sdk->products();

            return ResponseHelper::success([
                'module' => 'products',
                'loaded' => true
            ], 'Módulo de produtos carregado com sucesso');

        } catch (Exception $e) {
            return ResponseHelper::exception($e, 'Erro ao testar módulo de produtos');
        }
    }

    /**
     * Teste de checkout
     */
    public function testCheckout()
    {
        try {
            if (!ClubifySDKHelper::isAvailable()) {
                return ResponseHelper::error('SDK não está disponível', 500);
            }

            $sdk = ClubifySDKHelper::getInstance();
            $checkout = $sdk->checkout();

            return ResponseHelper::success([
                'module' => 'checkout',
                'loaded' => true
            ], 'Módulo de checkout carregado com sucesso');

        } catch (Exception $e) {
            return ResponseHelper::exception($e, 'Erro ao testar módulo de checkout');
        }
    }

    /**
     * Teste de organização
     */
    public function testOrganization()
    {
        try {
            if (!ClubifySDKHelper::isAvailable()) {
                return ResponseHelper::error('SDK não está disponível', 500);
            }

            $sdk = ClubifySDKHelper::getInstance();
            $organization = $sdk->organization();

            return ResponseHelper::success([
                'module' => 'organization',
                'loaded' => true
            ], 'Módulo de organização carregado com sucesso');

        } catch (Exception $e) {
            return ResponseHelper::exception($e, 'Erro ao testar módulo de organização');
        }
    }

    /**
     * Status geral do SDK
     */
    public function status()
    {
        try {
            $statusInfo = [
                'sdk_available' => ClubifySDKHelper::isAvailable(),
                'sdk_status' => ClubifySDKHelper::isAvailable() ? 'Disponível' : 'Não disponível',
                'config' => ClubifySDKHelper::getCredentialsInfo(),
                'available_modules' => [
                    'organization',
                    'products',
                    'checkout',
                    'payments',
                    'customers',
                    'webhooks'
                ]
            ];

            if (ClubifySDKHelper::isAvailable()) {
                $sdk = ClubifySDKHelper::getInstance();
                $statusInfo['initialized'] = $sdk->isInitialized();
            }

            return ResponseHelper::status($statusInfo);

        } catch (Exception $e) {
            return ResponseHelper::exception($e, 'Erro ao verificar status do SDK');
        }
    }

    /**
     * Página de testes completos do SDK
     */
    public function testAllMethodsPage()
    {
        return view('clubify.test-all-methods');
    }

    /**
     * Executa todos os testes do SDK
     */
    public function runAllTests()
    {
        try {
            if (!ClubifySDKHelper::isAvailable()) {
                return ResponseHelper::error('SDK não está disponível', 500);
            }

            $results = [];
            $totalMethods = 0;
            $workingMethods = 0;
            $errorMethods = 0;

            // Testar todos os módulos
            $modules = ['organization', 'products', 'checkout', 'payments', 'customers', 'webhooks'];

            foreach ($modules as $moduleName) {
                $moduleResults = $this->testModuleInternal($moduleName);
                $results[$moduleName] = $moduleResults;

                foreach ($moduleResults as $result) {
                    $totalMethods++;
                    if ($result['success']) {
                        $workingMethods++;
                    } else {
                        $errorMethods++;
                    }
                }
            }

            $stats = ModuleTestHelper::calculateStats($results);

            return ResponseHelper::success([
                'results' => $results,
                'stats' => $stats
            ], 'Todos os testes executados com sucesso');

        } catch (Exception $e) {
            return ResponseHelper::exception($e, 'Erro ao executar testes');
        }
    }

    /**
     * Testa um módulo específico
     */
    public function testModule($moduleName)
    {
        try {
            if (!ClubifySDKHelper::isAvailable()) {
                return ResponseHelper::error('SDK não está disponível', 500);
            }

            $results = $this->testModuleInternal($moduleName);

            return ResponseHelper::moduleTest($moduleName, true, $results);

        } catch (Exception $e) {
            return ResponseHelper::moduleTest($moduleName, false, [], $e);
        }
    }

    /**
     * Executa testes internos de um módulo específico
     */
    private function testModuleInternal($moduleName)
    {
        $results = [];
        $sdk = ClubifySDKHelper::getInstance();

        switch ($moduleName) {
            case 'organization':
                $module = $sdk->organization();
                $results = $this->testOrganizationModule($module);
                break;

            case 'products':
                $module = $sdk->products();
                $results = $this->testProductsModule($module);
                break;

            case 'checkout':
                $module = $sdk->checkout();
                $results = $this->testCheckoutModule($module);
                break;

            case 'payments':
                $module = $sdk->payments();
                $results = $this->testPaymentsModule($module);
                break;

            case 'customers':
                $module = $sdk->customers();
                $results = $this->testCustomersModule($module);
                break;

            case 'webhooks':
                $module = $sdk->webhooks();
                $results = $this->testWebhooksModule($module);
                break;

            default:
                throw new Exception("Módulo '{$moduleName}' não reconhecido");
        }

        return $results;
    }

    /**
     * Testa o módulo Organization
     */
    private function testOrganizationModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');

        // Métodos específicos do OrganizationModule
        $results[] = ModuleTestHelper::testMethod($module, 'getRepository', [], 'object');
        $results[] = ModuleTestHelper::testMethod($module, 'tenant', [], 'object');
        $results[] = ModuleTestHelper::testMethod($module, 'admin', [], 'object');
        $results[] = ModuleTestHelper::testMethod($module, 'apiKey', [], 'object');
        $results[] = ModuleTestHelper::testMethod($module, 'domain', [], 'object');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'setupOrganization', [[
            'name' => 'Test Org',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin@test.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'setupComplete', [[
            'name' => 'Complete Org',
            'admin_name' => 'Admin Complete',
            'admin_email' => 'admin@complete.com'
        ]], 'array');

        return $results;
    }

    /**
     * Testa o módulo Products
     */
    private function testProductsModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do ProductsModule
        $results[] = ModuleTestHelper::testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'setupComplete', [[
            'name' => 'Test Product',
            'price' => 99.99
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'createComplete', [[
            'name' => 'Complete Product',
            'price' => 199.99
        ]], 'array');

        return $results;
    }

    /**
     * Testa o módulo Checkout
     */
    private function testCheckoutModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do CheckoutModule
        $results[] = ModuleTestHelper::testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'createSession', [[
            'product_id' => 'test_prod_123',
            'customer_email' => 'customer@test.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'setupComplete', [[
            'product_id' => 'setup_prod_123',
            'customer_email' => 'setup@test.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'createComplete', [[
            'product_id' => 'complete_prod_123',
            'customer_email' => 'complete@test.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'oneClick', [[
            'payment_method' => 'credit_card',
            'amount' => 99.99
        ]], 'array');

        return $results;
    }

    /**
     * Testa o módulo Payments
     */
    private function testPaymentsModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do PaymentsModule
        $results[] = ModuleTestHelper::testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'processPayment', [[
            'method' => 'pix',
            'amount' => 50.00,
            'currency' => 'BRL'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'setupComplete', [[
            'gateway' => 'stripe',
            'amount' => 100.00
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'createComplete', [[
            'gateway' => 'pagarme',
            'amount' => 150.00
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'tokenizeCard', [[
            'number' => '4111111111111111',
            'brand' => 'visa',
            'exp_month' => '12',
            'exp_year' => '2025'
        ]], 'array');

        return $results;
    }

    /**
     * Testa o módulo Customers
     */
    private function testCustomersModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do CustomersModule
        $results[] = ModuleTestHelper::testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'createCustomer', [[
            'name' => 'Test Customer',
            'email' => 'test@customer.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'setupComplete', [[
            'name' => 'Setup Customer',
            'email' => 'setup@customer.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'createComplete', [[
            'name' => 'Complete Customer',
            'email' => 'complete@customer.com'
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'findByEmail', ['test@email.com'], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'updateProfile', [
            'customer_123',
            ['name' => 'Updated Name']
        ], 'array');

        return $results;
    }

    /**
     * Testa o módulo Webhooks
     */
    private function testWebhooksModule($module)
    {
        $results = [];

        // Métodos da Interface ModuleInterface
        $results[] = ModuleTestHelper::testMethod($module, 'getName', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getVersion', [], 'string');
        $results[] = ModuleTestHelper::testMethod($module, 'getDependencies', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStatus', [], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do WebhooksModule
        $results[] = ModuleTestHelper::testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = ModuleTestHelper::testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = ModuleTestHelper::testMethod($module, 'configureWebhook', [[
            'url' => 'https://example.com/webhook',
            'events' => ['order.created', 'payment.completed']
        ]], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'sendEvent', [
            'payment.completed',
            ['payment_id' => 'pay_123', 'amount' => 100.00]
        ], 'array');
        $results[] = ModuleTestHelper::testMethod($module, 'listWebhooks', [], 'array');

        return $results;
    }
}