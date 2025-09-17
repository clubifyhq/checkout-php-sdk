<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Exception;
use TypeError;

class ClubifyDemoController extends Controller
{
    private ClubifyCheckoutSDK $sdk;

    public function __construct()
    {
        // Configuração completa do SDK baseada na documentação
        $config = [
            'credentials' => [
                'tenant_id' => env('CLUBIFY_TENANT_ID', 'demo_tenant'),
                'api_key' => env('CLUBIFY_API_KEY', 'demo_api_key_123'),
                'api_secret' => env('CLUBIFY_API_SECRET', 'demo_secret_456')
            ],
            'environment' => env('CLUBIFY_ENVIRONMENT', 'development'),
            'api' => [
                'base_url' => env('CLUBIFY_API_URL', 'https://checkout.svelve.com'),
                'timeout' => env('CLUBIFY_TIMEOUT', 45),
                'retries' => env('CLUBIFY_RETRIES', 3),
                'verify_ssl' => env('CLUBIFY_VERIFY_SSL', false)
            ],
            'cache' => [
                'enabled' => env('CLUBIFY_CACHE_ENABLED', true),
                'ttl' => env('CLUBIFY_CACHE_TTL', 3600)
            ],
            'logging' => [
                'enabled' => env('CLUBIFY_LOG_REQUESTS', true),
                'level' => env('CLUBIFY_LOG_LEVEL', 'info')
            ]
        ];

        try {
            $this->sdk = new ClubifyCheckoutSDK($config);
            logger()->info('Clubify SDK inicializado com sucesso', ['config' => array_keys($config)]);
        } catch (Exception $e) {
            logger()->error('Erro ao inicializar Clubify SDK', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Página principal de demonstração
     */
    public function index()
    {
        $sdkStatus = isset($this->sdk) ? 'Conectado' : 'Erro na conexão';

        return view('clubify.demo', [
            'sdkStatus' => $sdkStatus,
            'config' => [
                'tenant_id' => env('CLUBIFY_TENANT_ID', 'demo_tenant'),
                'environment' => env('CLUBIFY_ENVIRONMENT', 'development'),
                'base_url' => env('CLUBIFY_BASE_URL', 'https://checkout.svelve.com')
            ]
        ]);
    }

    /**
     * Teste de produtos
     */
    public function testProducts()
    {
        try {
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
            }

            // Testar módulo de produtos
            $products = $this->sdk->products();

            return response()->json([
                'success' => true,
                'message' => 'Módulo de produtos carregado com sucesso',
                'module' => 'products'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Teste de checkout
     */
    public function testCheckout()
    {
        try {
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
            }

            // Testar módulo de checkout
            $checkout = $this->sdk->checkout();

            return response()->json([
                'success' => true,
                'message' => 'Módulo de checkout carregado com sucesso',
                'module' => 'checkout'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Teste de organização
     */
    public function testOrganization()
    {
        try {
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
            }

            // Testar módulo de organização
            $organization = $this->sdk->organization();

            return response()->json([
                'success' => true,
                'message' => 'Módulo de organização carregado com sucesso',
                'module' => 'organization'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Status geral do SDK
     */
    public function status()
    {
        try {
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
            }

            return response()->json([
                'success' => true,
                'sdk_status' => 'Inicializado',
                'config' => [
                    'tenant_id' => env('CLUBIFY_TENANT_ID', 'demo_tenant'),
                    'environment' => env('CLUBIFY_ENVIRONMENT', 'development'),
                    'base_url' => env('CLUBIFY_BASE_URL', 'http://localhost:8000')
                ],
                'available_modules' => [
                    'organization',
                    'products',
                    'checkout',
                    'payments',
                    'customers',
                    'webhooks'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'sdk_status' => 'Erro'
            ], 500);
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
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
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

            $successRate = $totalMethods > 0 ? round(($workingMethods / $totalMethods) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'results' => $results,
                'stats' => [
                    'totalMethods' => $totalMethods,
                    'workingMethods' => $workingMethods,
                    'errorMethods' => $errorMethods,
                    'successRate' => $successRate
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Testa um módulo específico
     */
    public function testModule($moduleName)
    {
        try {
            if (!isset($this->sdk)) {
                throw new Exception('SDK não inicializado');
            }

            $results = $this->testModuleInternal($moduleName);

            return response()->json([
                'success' => true,
                'module' => $moduleName,
                'results' => $results
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Executa testes internos de um módulo específico
     */
    private function testModuleInternal($moduleName)
    {
        $results = [];

        switch ($moduleName) {
            case 'organization':
                $module = $this->sdk->organization();
                $results = $this->testOrganizationModule($module);
                break;

            case 'products':
                $module = $this->sdk->products();
                $results = $this->testProductsModule($module);
                break;

            case 'checkout':
                $module = $this->sdk->checkout();
                $results = $this->testCheckoutModule($module);
                break;

            case 'payments':
                $module = $this->sdk->payments();
                $results = $this->testPaymentsModule($module);
                break;

            case 'customers':
                $module = $this->sdk->customers();
                $results = $this->testCustomersModule($module);
                break;

            case 'webhooks':
                $module = $this->sdk->webhooks();
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');

        // Métodos específicos do OrganizationModule
        $results[] = $this->testMethod($module, 'getRepository', [], 'object');
        $results[] = $this->testMethod($module, 'tenant', [], 'object');
        $results[] = $this->testMethod($module, 'admin', [], 'object');
        $results[] = $this->testMethod($module, 'apiKey', [], 'object');
        $results[] = $this->testMethod($module, 'domain', [], 'object');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'setupOrganization', [[
            'name' => 'Test Org',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin@test.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'setupComplete', [[
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');
        $results[] = $this->testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do ProductsModule
        $results[] = $this->testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'setupComplete', [[
            'name' => 'Test Product',
            'price' => 99.99
        ]], 'array');
        $results[] = $this->testMethod($module, 'createComplete', [[
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');
        $results[] = $this->testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do CheckoutModule
        $results[] = $this->testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'createSession', [[
            'product_id' => 'test_prod_123',
            'customer_email' => 'customer@test.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'setupComplete', [[
            'product_id' => 'setup_prod_123',
            'customer_email' => 'setup@test.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'createComplete', [[
            'product_id' => 'complete_prod_123',
            'customer_email' => 'complete@test.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'oneClick', [[
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');
        $results[] = $this->testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do PaymentsModule
        $results[] = $this->testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'processPayment', [[
            'method' => 'pix',
            'amount' => 50.00,
            'currency' => 'BRL'
        ]], 'array');
        $results[] = $this->testMethod($module, 'setupComplete', [[
            'gateway' => 'stripe',
            'amount' => 100.00
        ]], 'array');
        $results[] = $this->testMethod($module, 'createComplete', [[
            'gateway' => 'pagarme',
            'amount' => 150.00
        ]], 'array');
        $results[] = $this->testMethod($module, 'tokenizeCard', [[
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');
        $results[] = $this->testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do CustomersModule
        $results[] = $this->testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'createCustomer', [[
            'name' => 'Test Customer',
            'email' => 'test@customer.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'setupComplete', [[
            'name' => 'Setup Customer',
            'email' => 'setup@customer.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'createComplete', [[
            'name' => 'Complete Customer',
            'email' => 'complete@customer.com'
        ]], 'array');
        $results[] = $this->testMethod($module, 'findByEmail', ['test@email.com'], 'array');
        $results[] = $this->testMethod($module, 'updateProfile', [
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
        $results[] = $this->testMethod($module, 'getName', [], 'string');
        $results[] = $this->testMethod($module, 'getVersion', [], 'string');
        $results[] = $this->testMethod($module, 'getDependencies', [], 'array');
        $results[] = $this->testMethod($module, 'isInitialized', [], 'boolean');
        $results[] = $this->testMethod($module, 'isAvailable', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStatus', [], 'array');
        $results[] = $this->testMethod($module, 'cleanup', [], 'void');

        // Métodos específicos do WebhooksModule
        $results[] = $this->testMethod($module, 'isHealthy', [], 'boolean');
        $results[] = $this->testMethod($module, 'getStats', [], 'array');

        // Métodos de negócio
        $results[] = $this->testMethod($module, 'configureWebhook', [[
            'url' => 'https://example.com/webhook',
            'events' => ['order.created', 'payment.completed']
        ]], 'array');
        $results[] = $this->testMethod($module, 'sendEvent', [
            'payment.completed',
            ['payment_id' => 'pay_123', 'amount' => 100.00]
        ], 'array');
        $results[] = $this->testMethod($module, 'listWebhooks', [], 'array');

        return $results;
    }

    /**
     * Testa um método específico
     */
    private function testMethod($object, $methodName, $params, $expectedType)
    {
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
                $formattedResult = $this->formatReturnValue($result, $expectedType);
                $detailedInfo = $this->extractDetailedInfo($result, $expectedType);

                return [
                    'method' => $methodName,
                    'success' => true,
                    'result' => $formattedResult,
                    'raw_result' => $result,
                    'detailed_info' => $detailedInfo,
                    'response_type' => $actualType,
                    'error' => null
                ];
            } else {
                return [
                    'method' => $methodName,
                    'success' => false,
                    'result' => null,
                    'raw_result' => null,
                    'detailed_info' => null,
                    'response_type' => $actualType,
                    'error' => "Type mismatch: expected {$expectedType}, got {$actualType}"
                ];
            }
        } catch (Exception $e) {
            return [
                'method' => $methodName,
                'success' => false,
                'result' => null,
                'raw_result' => null,
                'detailed_info' => null,
                'response_type' => null,
                'error' => $e->getMessage()
            ];
        } catch (TypeError $e) {
            return [
                'method' => $methodName,
                'success' => false,
                'result' => null,
                'raw_result' => null,
                'detailed_info' => null,
                'response_type' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrai informações detalhadas do retorno da API
     */
    private function extractDetailedInfo($value, $expectedType)
    {
        $info = [];

        if ($expectedType === 'void') {
            $info['status'] = 'Method executed successfully';
            $info['return_type'] = 'void';
        } elseif ($expectedType === 'boolean') {
            $info['status'] = $value ? 'true' : 'false';
            $info['return_type'] = 'boolean';
        } elseif ($expectedType === 'string') {
            $info['status'] = 'String returned';
            $info['length'] = strlen($value);
            $info['preview'] = substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '');
            $info['return_type'] = 'string';
        } elseif ($expectedType === 'array') {
            $info['return_type'] = 'array';
            $info['item_count'] = count($value);

            // Buscar informações específicas comuns em respostas da API
            if (isset($value['id'])) {
                $info['id'] = $value['id'];
                $info['operation'] = 'Resource with ID returned';
            }

            if (isset($value['success'])) {
                $info['api_success'] = $value['success'] ? 'true' : 'false';
                $info['operation'] = 'API response with success status';
            }

            if (isset($value['data'])) {
                $info['has_data'] = true;
                if (is_array($value['data']) && isset($value['data']['id'])) {
                    $info['data_id'] = $value['data']['id'];
                }
            }

            if (isset($value['message'])) {
                $info['message'] = $value['message'];
            }

            if (isset($value['status'])) {
                $info['status'] = $value['status'];
            }

            if (isset($value['created_at'])) {
                $info['created_at'] = $value['created_at'];
                $info['operation'] = 'Resource created';
            }

            if (isset($value['updated_at'])) {
                $info['updated_at'] = $value['updated_at'];
                $info['operation'] = 'Resource updated';
            }

            // Capturar chaves principais do array
            $info['keys'] = array_keys($value);

        } elseif ($expectedType === 'object') {
            $info['return_type'] = 'object';
            $info['class'] = get_class($value);
            $info['status'] = 'Object instance returned';

            // Tentar extrair propriedades públicas se houver
            $publicProps = get_object_vars($value);
            if (!empty($publicProps)) {
                $info['public_properties'] = array_keys($publicProps);
            }
        }

        return $info;
    }

    /**
     * Formata o valor de retorno para exibição
     */
    private function formatReturnValue($value, $expectedType)
    {
        if ($expectedType === 'void') {
            return '✅ OK - Method executed';
        } elseif ($expectedType === 'boolean') {
            return ($value ? '✅ true' : '❌ false');
        } elseif ($expectedType === 'string') {
            return strlen($value) . ' chars: "' . substr($value, 0, 50) . (strlen($value) > 50 ? '..."' : '"');
        } elseif ($expectedType === 'array') {
            $parts = [];

            if (isset($value['id'])) {
                $parts[] = '🆔 ID: ' . $value['id'];
            }

            if (isset($value['success'])) {
                $parts[] = ($value['success'] ? '✅ Success' : '❌ Failed');
            }

            if (isset($value['data']['id'])) {
                $parts[] = '📦 Data ID: ' . $value['data']['id'];
            }

            if (isset($value['message'])) {
                $parts[] = '💬 ' . substr($value['message'], 0, 30) . (strlen($value['message']) > 30 ? '...' : '');
            }

            if (isset($value['status'])) {
                $parts[] = '📊 Status: ' . $value['status'];
            }

            if (empty($parts)) {
                $parts[] = '📋 Array (' . count($value) . ' items)';
            }

            return implode(' | ', $parts);

        } elseif ($expectedType === 'object') {
            return '🏗️ ' . get_class($value) . ' instance';
        } else {
            return (string)$value;
        }
    }
}