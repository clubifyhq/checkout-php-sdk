<?php

/**
 * Diagnostic Script - Clubify Checkout SDK
 *
 * Script completo para testar todos os métodos do SDK e identificar:
 * - Conectividade real vs mock data
 * - Status de endpoints individuais
 * - Problemas de configuração
 * - Performance de cada módulo
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

class SDKDiagnostic
{
    private ClubifyCheckoutSDK $sdk;
    private array $results = [];
    private int $totalTests = 0;
    private int $successfulTests = 0;
    private int $failedTests = 0;
    private int $mockDataDetected = 0;
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->initializeSDK();
    }

    private function initializeSDK(): void
    {
        $config = [
            'credentials' => [
                'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? 'demo_tenant',
                'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'demo_key',
                'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
            ],
            'http' => [
                'timeout' => 5000,
                'connect_timeout' => 3,
                'retries' => 1
            ],
            'endpoints' => [
                'base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'
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

        try {
            $this->sdk = new ClubifyCheckoutSDK($config);
            $this->logResult('SDK_INITIALIZATION', 'SUCCESS', 'SDK inicializado com sucesso', [
                'tenant_id' => substr($config['credentials']['tenant_id'], 0, 10) . '...',
                'environment' => $config['credentials']['environment'],
                'base_url' => $config['endpoints']['base_url']
            ]);
        } catch (\Exception $e) {
            $this->logResult('SDK_INITIALIZATION', 'ERROR', 'Falha ao inicializar SDK', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    public function runFullDiagnostic(): void
    {
        $this->printHeader();

        // 1. Teste básico de conectividade
        $this->testConnectivity();

        // 2. Status geral do SDK
        $this->testSDKStatus();

        // 3. Teste todos os módulos
        $this->testAllModules();

        // 4. Teste métodos específicos de cada módulo
        $this->testModuleSpecificMethods();

        // 5. Teste APIs com dados reais
        $this->testRealAPIConnections();

        // 6. Análise de dados mock vs reais
        $this->analyzeMockVsRealData();

        // 7. Relatório final
        $this->printSummary();
    }

    private function testConnectivity(): void
    {
        $this->printSection("🔌 TESTE DE CONECTIVIDADE BÁSICA");

        try {
            // Teste HTTP básico
            $baseUrl = $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1';

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'header' => "User-Agent: ClubifySDK-Diagnostic/1.0\r\n"
                ]
            ]);

            $startTime = microtime(true);
            $response = @file_get_contents($baseUrl . '/health', false, $context);
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($response !== false) {
                $this->logResult('HTTP_CONNECTIVITY', 'SUCCESS', "Conectividade HTTP OK", [
                    'base_url' => $baseUrl,
                    'response_time' => $responseTime . 'ms',
                    'response_size' => strlen($response) . ' bytes'
                ]);
            } else {
                $this->logResult('HTTP_CONNECTIVITY', 'WARNING', "Endpoint /health não disponível", [
                    'base_url' => $baseUrl,
                    'attempted_time' => $responseTime . 'ms'
                ]);
            }
        } catch (\Exception $e) {
            $this->logResult('HTTP_CONNECTIVITY', 'ERROR', "Falha na conectividade HTTP", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testSDKStatus(): void
    {
        $this->printSection("📊 STATUS GERAL DO SDK");

        try {
            $stats = $this->sdk->getStats();
            $this->logResult('SDK_STATS', 'SUCCESS', "Estatísticas do SDK obtidas", $stats);

            $version = $this->sdk->getVersion();
            $this->logResult('SDK_VERSION', 'SUCCESS', "Versão: $version");

            $isInitialized = $this->sdk->isInitialized();
            $this->logResult('SDK_INITIALIZED', $isInitialized ? 'SUCCESS' : 'WARNING',
                "Status de inicialização: " . ($isInitialized ? 'Inicializado' : 'Não inicializado'));

        } catch (\Exception $e) {
            $this->logResult('SDK_STATUS', 'ERROR', "Erro ao obter status do SDK", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testAllModules(): void
    {
        $this->printSection("🧩 TESTE DE TODOS OS MÓDULOS");

        $modules = [
            'organization' => 'Organization',
            'products' => 'Products',
            'checkout' => 'Checkout',
            'payments' => 'Payments (Factory Pattern)',
            'customers' => 'Customers (Factory Pattern)',
            'webhooks' => 'Webhooks (Repository Pattern)',
            'tracking' => 'Tracking',
            'userManagement' => 'User Management',
            'subscriptions' => 'Subscriptions'
        ];

        foreach ($modules as $method => $displayName) {
            try {
                $module = $this->sdk->$method();

                if ($module) {
                    // Teste métodos básicos
                    $name = $module->getName();
                    $version = $module->getVersion();
                    $isInitialized = $module->isInitialized();
                    $isAvailable = $module->isAvailable();

                    $moduleInfo = [
                        'name' => $name,
                        'version' => $version,
                        'initialized' => $isInitialized,
                        'available' => $isAvailable
                    ];

                    // Se tem método getStatus, usar
                    if (method_exists($module, 'getStatus')) {
                        $status = $module->getStatus();
                        $moduleInfo['detailed_status'] = $status;
                    }

                    $this->logResult('MODULE_' . strtoupper($method), 'SUCCESS',
                        "$displayName carregado e funcional", $moduleInfo);
                } else {
                    $this->logResult('MODULE_' . strtoupper($method), 'ERROR',
                        "$displayName retornou null");
                }
            } catch (\Exception $e) {
                $this->logResult('MODULE_' . strtoupper($method), 'ERROR',
                    "Erro ao carregar $displayName", [
                        'error' => $e->getMessage(),
                        'type' => get_class($e)
                    ]);
            }
        }
    }

    private function testModuleSpecificMethods(): void
    {
        $this->printSection("🔧 MÉTODOS ESPECÍFICOS DE MÓDULOS");

        // Teste Customers (Factory Pattern)
        $this->testCustomersModule();

        // Teste Payments (Factory Pattern)
        $this->testPaymentsModule();

        // Teste Products
        $this->testProductsModule();

        // Teste Organization
        $this->testOrganizationModule();

        // Teste Webhooks (Repository Pattern)
        $this->testWebhooksModule();
    }

    private function testCustomersModule(): void
    {
        $this->printSubSection("👥 CUSTOMERS MODULE (Factory Pattern)");

        try {
            $customers = $this->sdk->customers();

            // Teste createCustomer
            $customerData = [
                'name' => 'Diagnostic Test User',
                'email' => 'diagnostic.' . time() . '@test.com',
                'phone' => '+5511999999999'
            ];

            $result = $customers->createCustomer($customerData);
            $this->analyzeAPIResponse('CUSTOMERS_CREATE', $result, $customerData);

            // Teste findByEmail
            $findResult = $customers->findByEmail('test@example.com');
            $this->analyzeAPIResponse('CUSTOMERS_FIND_BY_EMAIL', $findResult, ['email' => 'test@example.com']);

            // Teste updateProfile
            $updateResult = $customers->updateProfile('test_customer_id', ['name' => 'Updated Name']);
            $this->analyzeAPIResponse('CUSTOMERS_UPDATE_PROFILE', $updateResult, ['customer_id' => 'test_customer_id']);

        } catch (\Exception $e) {
            $this->logResult('CUSTOMERS_MODULE', 'ERROR', "Erro no módulo Customers", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testPaymentsModule(): void
    {
        $this->printSubSection("💳 PAYMENTS MODULE (Factory Pattern)");

        try {
            $payments = $this->sdk->payments();

            // Teste processPayment
            $paymentData = [
                'amount' => 10000, // R$ 100,00 em centavos
                'currency' => 'BRL',
                'customer_id' => 'test_customer_id',
                'payment_method' => 'credit_card'
            ];

            $result = $payments->processPayment($paymentData);
            $this->analyzeAPIResponse('PAYMENTS_PROCESS', $result, $paymentData);

            // Teste getStatus
            $statusResult = $payments->getStatus();
            $this->analyzeAPIResponse('PAYMENTS_STATUS', $statusResult);

        } catch (\Exception $e) {
            $this->logResult('PAYMENTS_MODULE', 'ERROR', "Erro no módulo Payments", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testProductsModule(): void
    {
        $this->printSubSection("🛍️ PRODUCTS MODULE");

        try {
            $products = $this->sdk->products();

            // Teste getStats
            $statsResult = $products->getStats();
            $this->analyzeAPIResponse('PRODUCTS_STATS', $statsResult);

            // Teste getStatus
            $statusResult = $products->getStatus();
            $this->analyzeAPIResponse('PRODUCTS_STATUS', $statusResult);

            // Teste listThemes
            $themesResult = $products->listThemes();
            $this->analyzeAPIResponse('PRODUCTS_THEMES', $themesResult);

        } catch (\Exception $e) {
            $this->logResult('PRODUCTS_MODULE', 'ERROR', "Erro no módulo Products", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testOrganizationModule(): void
    {
        $this->printSubSection("🏢 ORGANIZATION MODULE");

        try {
            $organization = $this->sdk->organization();

            // Teste getStatus
            $statusResult = $organization->getStatus();
            $this->analyzeAPIResponse('ORGANIZATION_STATUS', $statusResult);

            // Teste getName
            $nameResult = $organization->getName();
            $this->analyzeAPIResponse('ORGANIZATION_NAME', ['name' => $nameResult]);

        } catch (\Exception $e) {
            $this->logResult('ORGANIZATION_MODULE', 'ERROR', "Erro no módulo Organization", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testWebhooksModule(): void
    {
        $this->printSubSection("🔗 WEBHOOKS MODULE (Repository Pattern)");

        try {
            $webhooks = $this->sdk->webhooks();

            // Teste createWebhook
            $webhookData = [
                'url' => 'https://example.com/webhook',
                'events' => ['payment.completed', 'order.created']
            ];

            $result = $webhooks->createWebhook($webhookData);
            $this->analyzeAPIResponse('WEBHOOKS_CREATE', $result, $webhookData);

            // Teste listWebhooks
            $listResult = $webhooks->listWebhooks();
            $this->analyzeAPIResponse('WEBHOOKS_LIST', $listResult);

        } catch (\Exception $e) {
            $this->logResult('WEBHOOKS_MODULE', 'ERROR', "Erro no módulo Webhooks", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testRealAPIConnections(): void
    {
        $this->printSection("🌐 TESTE DE CONEXÕES REAIS DA API");

        // Teste endpoints específicos para verificar conectividade real
        $endpoints = [
            'health' => '/health',
            'organization' => '/organization',
            'products' => '/products',
            'payments' => '/payments/methods'
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpointConnectivity($name, $endpoint);
        }
    }

    private function testEndpointConnectivity(string $name, string $endpoint): void
    {
        try {
            $baseUrl = $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1';
            $fullUrl = $baseUrl . $endpoint;

            $headers = [
                'User-Agent: ClubifySDK-Diagnostic/1.0',
                'Accept: application/json',
                'Content-Type: application/json'
            ];

            if (!empty($_ENV['CLUBIFY_CHECKOUT_API_KEY'])) {
                $headers[] = 'Authorization: Bearer ' . $_ENV['CLUBIFY_CHECKOUT_API_KEY'];
            }

            if (!empty($_ENV['CLUBIFY_CHECKOUT_TENANT_ID'])) {
                $headers[] = 'X-Tenant-ID: ' . $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'];
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => implode("\r\n", $headers) . "\r\n"
                ]
            ]);

            $startTime = microtime(true);
            $response = @file_get_contents($fullUrl, false, $context);
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($response !== false) {
                $decodedResponse = json_decode($response, true);
                $isValidJson = json_last_error() === JSON_ERROR_NONE;

                $this->logResult('ENDPOINT_' . strtoupper($name), 'SUCCESS',
                    "Endpoint $endpoint respondeu", [
                        'url' => $fullUrl,
                        'response_time' => $responseTime . 'ms',
                        'response_size' => strlen($response) . ' bytes',
                        'valid_json' => $isValidJson,
                        'response_preview' => substr($response, 0, 200) . '...'
                    ]);

                if ($isValidJson && $decodedResponse) {
                    $this->analyzeResponseForMockData($name, $decodedResponse);
                }
            } else {
                $httpResponseHeader = $http_response_header ?? [];
                $this->logResult('ENDPOINT_' . strtoupper($name), 'ERROR',
                    "Endpoint $endpoint não respondeu", [
                        'url' => $fullUrl,
                        'attempted_time' => $responseTime . 'ms',
                        'headers' => $httpResponseHeader
                    ]);
            }
        } catch (\Exception $e) {
            $this->logResult('ENDPOINT_' . strtoupper($name), 'ERROR',
                "Erro ao testar endpoint $endpoint", [
                    'error' => $e->getMessage()
                ]);
        }
    }

    private function analyzeAPIResponse(string $operation, $response, array $requestData = []): void
    {
        if (is_array($response)) {
            $isMock = $this->detectMockData($response);
            $status = $isMock ? 'MOCK_DETECTED' : 'REAL_DATA';

            $analysisData = [
                'response_type' => 'array',
                'item_count' => count($response),
                'has_success_key' => isset($response['success']),
                'has_data_key' => isset($response['data']),
                'mock_indicators' => $this->findMockIndicators($response),
                'request_data' => $requestData
            ];

            if ($isMock) {
                $this->mockDataDetected++;
                $analysisData['mock_analysis'] = $this->analyzeMockPatterns($response);
            }

            $this->logResult($operation, $status,
                $isMock ? "Dados mock detectados" : "Dados reais da API",
                $analysisData);
        } else {
            $this->logResult($operation, 'WARNING', "Resposta não é um array", [
                'response_type' => gettype($response),
                'response_value' => is_scalar($response) ? $response : 'complex_type'
            ]);
        }
    }

    private function detectMockData($data): bool
    {
        // Padrões comuns de dados mock
        $mockPatterns = [
            // IDs previsíveis
            'customer_123', 'test_', 'mock_', 'demo_', 'sample_',
            // Timestamps suspeitos (muito redondos)
            '1234567890', '1577836800', // 01/01/2020
            // Valores monetários suspeitos
            '10000', '25000', '50000', // valores muito redondos
            // Emails de teste
            'test@', 'example@', 'demo@', 'mock@'
        ];

        $jsonString = json_encode($data);

        foreach ($mockPatterns as $pattern) {
            if (stripos($jsonString, $pattern) !== false) {
                return true;
            }
        }

        // Verifica se há dados muito estruturados demais (suspeito de mock)
        if (is_array($data)) {
            // Se todos os IDs seguem o mesmo padrão, é suspeito
            $ids = $this->extractIds($data);
            if (count($ids) > 1 && $this->allFollowSamePattern($ids)) {
                return true;
            }
        }

        return false;
    }

    private function findMockIndicators($data): array
    {
        $indicators = [];
        $jsonString = json_encode($data);

        if (stripos($jsonString, 'test') !== false) $indicators[] = 'contains_test';
        if (stripos($jsonString, 'mock') !== false) $indicators[] = 'contains_mock';
        if (stripos($jsonString, 'demo') !== false) $indicators[] = 'contains_demo';
        if (stripos($jsonString, 'example') !== false) $indicators[] = 'contains_example';
        if (stripos($jsonString, 'customer_123') !== false) $indicators[] = 'predictable_id';

        return $indicators;
    }

    private function extractIds($data): array
    {
        $ids = [];
        $this->extractIdsRecursive($data, $ids);
        return $ids;
    }

    private function extractIdsRecursive($data, &$ids): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && (str_contains($key, '_id') || $key === 'id')) {
                    $ids[] = $value;
                }
                if (is_array($value)) {
                    $this->extractIdsRecursive($value, $ids);
                }
            }
        }
    }

    private function allFollowSamePattern($ids): bool
    {
        if (count($ids) < 2) return false;

        $patterns = [];
        foreach ($ids as $id) {
            $pattern = preg_replace('/\d+/', 'X', $id);
            $patterns[] = $pattern;
        }

        return count(array_unique($patterns)) === 1;
    }

    private function analyzeMockPatterns($data): array
    {
        return [
            'suspicious_timestamps' => $this->findSuspiciousTimestamps($data),
            'round_numbers' => $this->findRoundNumbers($data),
            'test_emails' => $this->findTestEmails($data),
            'predictable_ids' => $this->findPredictableIds($data)
        ];
    }

    private function findSuspiciousTimestamps($data): array
    {
        $timestamps = [];
        $this->findTimestampsRecursive($data, $timestamps);

        $suspicious = [];
        foreach ($timestamps as $ts) {
            if ($ts == 1234567890 || $ts == 1577836800 || $ts % 86400 == 0) {
                $suspicious[] = $ts;
            }
        }

        return $suspicious;
    }

    private function findTimestampsRecursive($data, &$timestamps): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_numeric($value) && $value > 1000000000 && $value < 2000000000) {
                    $timestamps[] = (int)$value;
                }
                if (is_array($value)) {
                    $this->findTimestampsRecursive($value, $timestamps);
                }
            }
        }
    }

    private function findRoundNumbers($data): array
    {
        $numbers = [];
        $this->findNumbersRecursive($data, $numbers);

        return array_filter($numbers, function($num) {
            return $num > 0 && $num % 1000 == 0;
        });
    }

    private function findNumbersRecursive($data, &$numbers): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (is_numeric($value)) {
                    $numbers[] = (float)$value;
                }
                if (is_array($value)) {
                    $this->findNumbersRecursive($value, $numbers);
                }
            }
        }
    }

    private function findTestEmails($data): array
    {
        $emails = [];
        $this->findEmailsRecursive($data, $emails);

        return array_filter($emails, function($email) {
            return stripos($email, 'test') !== false ||
                   stripos($email, 'example') !== false ||
                   stripos($email, 'demo') !== false;
        });
    }

    private function findEmailsRecursive($data, &$emails): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $value;
                }
                if (is_array($value)) {
                    $this->findEmailsRecursive($value, $emails);
                }
            }
        }
    }

    private function findPredictableIds($data): array
    {
        $ids = $this->extractIds($data);
        return array_filter($ids, function($id) {
            return preg_match('/^(customer|user|order|payment)_\d{1,3}$/', $id);
        });
    }

    private function analyzeResponseForMockData(string $endpoint, array $response): void
    {
        $isMock = $this->detectMockData($response);
        if ($isMock) {
            $this->mockDataDetected++;
            $this->logResult('MOCK_ANALYSIS_' . strtoupper($endpoint), 'WARNING',
                "Dados mock detectados no endpoint", [
                    'endpoint' => $endpoint,
                    'mock_indicators' => $this->findMockIndicators($response),
                    'mock_patterns' => $this->analyzeMockPatterns($response)
                ]);
        }
    }

    private function analyzeMockVsRealData(): void
    {
        $this->printSection("🔍 ANÁLISE: DADOS MOCK vs REAIS");

        $mockPercentage = $this->totalTests > 0 ? round(($this->mockDataDetected / $this->totalTests) * 100, 2) : 0;

        $this->logResult('MOCK_VS_REAL_ANALYSIS', 'INFO', "Análise de dados mock vs reais", [
            'total_tests' => $this->totalTests,
            'mock_responses_detected' => $this->mockDataDetected,
            'real_responses' => $this->totalTests - $this->mockDataDetected,
            'mock_percentage' => $mockPercentage . '%',
            'real_percentage' => (100 - $mockPercentage) . '%'
        ]);

        if ($mockPercentage > 50) {
            $this->logResult('MOCK_WARNING', 'WARNING',
                "Alto percentual de dados mock detectados! Verificar configuração da API");
        } elseif ($mockPercentage > 0) {
            $this->logResult('MOCK_INFO', 'INFO',
                "Alguns dados mock detectados - normal para ambiente de desenvolvimento");
        } else {
            $this->logResult('REAL_DATA', 'SUCCESS',
                "Nenhum dado mock detectado - conectando com API real");
        }
    }

    private function logResult(string $test, string $status, string $message, array $data = []): void
    {
        $this->totalTests++;

        if ($status === 'SUCCESS') {
            $this->successfulTests++;
        } elseif ($status === 'ERROR') {
            $this->failedTests++;
        }

        $result = [
            'test' => $test,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->results[] = $result;

        // Output em tempo real
        $statusIcon = match($status) {
            'SUCCESS' => '✅',
            'ERROR' => '❌',
            'WARNING' => '⚠️',
            'MOCK_DETECTED' => '🎭',
            'INFO' => 'ℹ️',
            default => '📋'
        };

        echo sprintf("%s [%s] %s: %s\n", $statusIcon, $status, $test, $message);

        if (!empty($data) && in_array($status, ['ERROR', 'WARNING', 'MOCK_DETECTED'])) {
            echo "   Detalhes: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        echo "\n";
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n";
        echo "                       🔧 CLUBIFY SDK DIAGNOSTIC SCRIPT                        \n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n";
        echo "Objetivo: Testar conectividade, identificar dados mock e validar endpoints\n";
        echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n\n";
    }

    private function printSection(string $title): void
    {
        echo "\n";
        echo "┌─────────────────────────────────────────────────────────────────────────────┐\n";
        echo "│ " . str_pad($title, 75, ' ', STR_PAD_BOTH) . " │\n";
        echo "└─────────────────────────────────────────────────────────────────────────────┘\n\n";
    }

    private function printSubSection(string $title): void
    {
        echo "\n";
        echo "   ┌─" . str_repeat('─', strlen($title) + 2) . "┐\n";
        echo "   │ " . $title . " │\n";
        echo "   └─" . str_repeat('─', strlen($title) + 2) . "┘\n\n";
    }

    private function printSummary(): void
    {
        $endTime = microtime(true);
        $totalTime = round($endTime - $this->startTime, 2);

        $this->printSection("📊 RELATÓRIO FINAL");

        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                 RESUMO GERAL                                ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
        echo sprintf("║ ⏱️  Tempo total de execução: %s segundos                              ║\n", str_pad($totalTime . 's', 41));
        echo sprintf("║ 📊  Total de testes executados: %s                                     ║\n", str_pad($this->totalTests, 38));
        echo sprintf("║ ✅  Testes bem-sucedidos: %s                                           ║\n", str_pad($this->successfulTests, 44));
        echo sprintf("║ ❌  Testes com falha: %s                                               ║\n", str_pad($this->failedTests, 47));
        echo sprintf("║ 🎭  Dados mock detectados: %s                                          ║\n", str_pad($this->mockDataDetected, 46));
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        // Análise de qualidade da conectividade
        $successRate = $this->totalTests > 0 ? round(($this->successfulTests / $this->totalTests) * 100, 2) : 0;
        $mockRate = $this->totalTests > 0 ? round(($this->mockDataDetected / $this->totalTests) * 100, 2) : 0;

        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                              ANÁLISE DE QUALIDADE                           ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";

        if ($successRate >= 90) {
            echo "║ 🎉  EXCELENTE: Taxa de sucesso superior a 90%                           ║\n";
        } elseif ($successRate >= 70) {
            echo "║ 👍  BOM: Taxa de sucesso entre 70-90%                                   ║\n";
        } else {
            echo "║ ⚠️  ATENÇÃO: Taxa de sucesso abaixo de 70%                              ║\n";
        }

        if ($mockRate <= 10) {
            echo "║ 🌐  REAL: Baixo percentual de dados mock (conectando com API real)     ║\n";
        } elseif ($mockRate <= 50) {
            echo "║ 🔧  MISTO: Alguns dados mock (normal para desenvolvimento)              ║\n";
        } else {
            echo "║ 🎭  MOCK: Alto percentual de dados mock (verificar configuração)        ║\n";
        }

        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        // Recomendações
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                                RECOMENDAÇÕES                                ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";

        if ($this->failedTests > 0) {
            echo "║ 🔧  Verificar configurações de API (credentials, URLs, timeouts)        ║\n";
        }

        if ($mockRate > 30) {
            echo "║ 🌐  Verificar se as credenciais estão apontando para ambiente correto   ║\n";
            echo "║ 📋  Conferir TENANT_ID e API_KEY nas variáveis de ambiente              ║\n";
        }

        if ($successRate < 70) {
            echo "║ 🔍  Investigar conectividade de rede e firewall                         ║\n";
            echo "║ ⏱️  Considerar aumentar timeouts para conexões lentas                   ║\n";
        }

        echo "║ 📄  Log completo salvo em diagnostic-results.json                       ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        // Salvar log completo
        $this->saveResultsToFile();
    }

    private function saveResultsToFile(): void
    {
        $summary = [
            'diagnostic_info' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'total_time' => round(microtime(true) - $this->startTime, 2),
                'sdk_version' => $this->sdk->getVersion() ?? 'unknown',
                'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'unknown'
            ],
            'statistics' => [
                'total_tests' => $this->totalTests,
                'successful_tests' => $this->successfulTests,
                'failed_tests' => $this->failedTests,
                'mock_data_detected' => $this->mockDataDetected,
                'success_rate' => $this->totalTests > 0 ? round(($this->successfulTests / $this->totalTests) * 100, 2) : 0,
                'mock_rate' => $this->totalTests > 0 ? round(($this->mockDataDetected / $this->totalTests) * 100, 2) : 0
            ],
            'detailed_results' => $this->results
        ];

        file_put_contents(__DIR__ . '/diagnostic-results.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "📄 Relatório detalhado salvo em: diagnostic-results.json\n\n";
    }
}

// Carregar variáveis de ambiente se existir arquivo .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

// Executar diagnóstico
try {
    $diagnostic = new SDKDiagnostic();
    $diagnostic->runFullDiagnostic();
} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}