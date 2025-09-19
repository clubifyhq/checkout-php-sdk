<?php

/**
 * IMPROVED Diagnostic Script - Clubify Checkout SDK
 *
 * Script melhorado que usa Reflection para descobrir e testar
 * TODOS os métodos disponíveis em cada módulo dinamicamente.
 *
 * Características:
 * - Descoberta automática de métodos via Reflection
 * - Análise profunda de dados mock vs reais
 * - Detecção inteligente de padrões de retorno
 * - Cobertura completa de todas as implementações
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

class ImprovedSDKDiagnostic
{
    private ClubifyCheckoutSDK $sdk;
    private array $results = [];
    private int $totalTests = 0;
    private int $successfulTests = 0;
    private int $failedTests = 0;
    private int $mockDataDetected = 0;
    private float $startTime;
    private array $moduleStats = [];

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
                'timeout' => 8000,
                'connect_timeout' => 5,
                'retries' => 2
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
                'level' => 'debug'
            ]
        ];

        try {
            $this->sdk = new ClubifyCheckoutSDK($config);

            // IMPORTANTE: Inicializar o SDK antes dos testes
            $initResult = $this->sdk->initialize();

            $this->logResult('SDK_INITIALIZATION', 'SUCCESS', 'SDK inicializado com sucesso', [
                'tenant_id' => substr($config['credentials']['tenant_id'], 0, 10) . '...',
                'environment' => $config['credentials']['environment'],
                'base_url' => $config['endpoints']['base_url'],
                'initialization_result' => $initResult
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

    public function runComprehensiveDiagnostic(): void
    {
        $this->printHeader();

        // 1. Teste básico de conectividade
        $this->testConnectivity();

        // 2. Status geral do SDK
        $this->testSDKStatus();

        // 3. Descoberta e teste de todos os módulos
        $this->discoverAndTestAllModules();

        // 4. Teste TODOS os métodos de cada módulo via Reflection
        $this->testAllModuleMethodsViaReflection();

        // 5. Análise avançada de padrões mock
        $this->advancedMockAnalysis();

        // 6. Validação de endpoints diretos
        $this->validateDirectAPIEndpoints();

        // 7. Relatório final abrangente
        $this->printComprehensiveSummary();
    }

    private function discoverAndTestAllModules(): void
    {
        $this->printSection("🔍 DESCOBERTA AUTOMÁTICA DE MÓDULOS");

        // Lista conhecida de módulos para descoberta
        $potentialModules = [
            'organization', 'products', 'checkout', 'payments', 'customers',
            'webhooks', 'tracking', 'userManagement', 'subscriptions',
            'orders', 'analytics', 'notifications', 'security', 'reporting'
        ];

        $discoveredModules = [];

        foreach ($potentialModules as $moduleName) {
            try {
                if (method_exists($this->sdk, $moduleName)) {
                    // Garantir que o SDK está inicializado antes de acessar módulos
                    if (!$this->sdk->isInitialized()) {
                        $this->sdk->initialize();
                    }

                    $module = $this->sdk->$moduleName();

                    if ($module !== null) {
                        $discoveredModules[$moduleName] = $module;

                        // Informações básicas do módulo
                        $moduleInfo = [
                            'name' => method_exists($module, 'getName') ? $module->getName() : $moduleName,
                            'version' => method_exists($module, 'getVersion') ? $module->getVersion() : 'unknown',
                            'class' => get_class($module),
                            'initialized' => method_exists($module, 'isInitialized') ? $module->isInitialized() : 'unknown',
                            'available' => method_exists($module, 'isAvailable') ? $module->isAvailable() : 'unknown'
                        ];

                        // Descobrir métodos disponíveis via Reflection
                        $reflection = new \ReflectionClass($module);
                        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

                        $moduleMethods = [];
                        foreach ($publicMethods as $method) {
                            if (!$method->isConstructor() && !$method->isDestructor() &&
                                $method->getDeclaringClass()->getName() !== 'stdClass') {
                                $moduleMethods[] = [
                                    'name' => $method->getName(),
                                    'parameters' => $method->getNumberOfParameters(),
                                    'required_params' => $method->getNumberOfRequiredParameters(),
                                    'static' => $method->isStatic()
                                ];
                            }
                        }

                        $moduleInfo['discovered_methods'] = $moduleMethods;
                        $moduleInfo['total_methods'] = count($moduleMethods);

                        $this->moduleStats[$moduleName] = $moduleInfo;

                        $this->logResult('MODULE_DISCOVERED_' . strtoupper($moduleName), 'SUCCESS',
                            "Módulo {$moduleName} descoberto com " . count($moduleMethods) . " métodos públicos", $moduleInfo);
                    }
                }
            } catch (\Exception $e) {
                $this->logResult('MODULE_DISCOVERY_' . strtoupper($moduleName), 'ERROR',
                    "Erro ao descobrir módulo {$moduleName}", [
                        'error' => $e->getMessage(),
                        'type' => get_class($e)
                    ]);
            }
        }

        $this->logResult('MODULE_DISCOVERY_SUMMARY', 'INFO',
            "Descobertos " . count($discoveredModules) . " módulos funcionais", [
                'discovered_modules' => array_keys($discoveredModules),
                'total_methods_found' => array_sum(array_column($this->moduleStats, 'total_methods'))
            ]);
    }

    private function testAllModuleMethodsViaReflection(): void
    {
        $this->printSection("🧪 TESTE ABRANGENTE DE TODOS OS MÉTODOS VIA REFLECTION");

        foreach ($this->moduleStats as $moduleName => $moduleInfo) {
            $this->printSubSection("📋 TESTANDO MÓDULO: " . strtoupper($moduleName));

            try {
                // Garantir que o SDK e módulo estão inicializados
                if (!$this->sdk->isInitialized()) {
                    $this->sdk->initialize();
                }

                $module = $this->sdk->$moduleName();

                // Verificar se o módulo está inicializado
                if (method_exists($module, 'isInitialized') && !$module->isInitialized()) {
                    $this->logResult('MODULE_NOT_INITIALIZED_' . strtoupper($moduleName), 'WARNING',
                        "Módulo {$moduleName} não estava inicializado - tentando corrigir");
                }

                foreach ($moduleInfo['discovered_methods'] as $methodInfo) {
                    $methodName = $methodInfo['name'];

                    // Pular métodos que não devem ser testados automaticamente
                    if ($this->shouldSkipMethod($methodName)) {
                        continue;
                    }

                    $this->testModuleMethod($module, $moduleName, $methodName, $methodInfo);
                }

            } catch (\Exception $e) {
                $this->logResult('MODULE_REFLECTION_' . strtoupper($moduleName), 'ERROR',
                    "Erro ao testar métodos do módulo {$moduleName}", [
                        'error' => $e->getMessage()
                    ]);
            }
        }
    }

    private function shouldSkipMethod(string $methodName): bool
    {
        $skipPatterns = [
            // Métodos privados/internos
            '__', 'get', 'set', 'is', 'has',
            // Métodos destrutivos
            'delete', 'remove', 'destroy', 'clear',
            // Métodos que requerem parâmetros complexos
            'update', 'create', 'save', 'execute',
            // Métodos de configuração
            'configure', 'initialize', 'setup'
        ];

        foreach ($skipPatterns as $pattern) {
            if (stripos($methodName, $pattern) === 0) {
                return true;
            }
        }

        return false;
    }

    private function testModuleMethod($module, string $moduleName, string $methodName, array $methodInfo): void
    {
        try {
            $testKey = strtoupper($moduleName) . '_METHOD_' . strtoupper($methodName);

            // CRÍTICO: Garantir que tudo está inicializado antes de testar
            if (!$this->sdk->isInitialized()) {
                $this->sdk->initialize();
            }

            // Verificar se o módulo está realmente inicializado
            if (method_exists($module, 'isInitialized') && !$module->isInitialized()) {
                $this->logResult($testKey, 'WARNING',
                    "Módulo {$moduleName} não inicializado - pulando método {$methodName}");
                return;
            }

            // Diferentes estratégias baseadas no número de parâmetros
            if ($methodInfo['required_params'] == 0) {
                // Métodos sem parâmetros obrigatórios
                $result = $module->$methodName();
                $this->analyzeMethodResponse($testKey, $result, $moduleName, $methodName, []);

            } elseif ($methodInfo['required_params'] <= 2) {
                // Métodos com poucos parâmetros - tentar com dados de teste
                $testParams = $this->generateTestParameters($methodName, $methodInfo['required_params']);

                try {
                    $result = $module->$methodName(...$testParams);
                    $this->analyzeMethodResponse($testKey, $result, $moduleName, $methodName, $testParams);
                } catch (\TypeError $e) {
                    // Se falhar por tipo de parâmetro, documentar isso
                    $this->logResult($testKey, 'WARNING',
                        "Método {$methodName} tem tipo de parâmetro incompatível", [
                            'module' => $moduleName,
                            'method' => $methodName,
                            'required_params' => $methodInfo['required_params'],
                            'attempted_params' => $testParams,
                            'error' => $e->getMessage()
                        ]);
                } catch (\Exception $e) {
                    // Se falhar com parâmetros de teste, documentar isso
                    $this->logResult($testKey, 'WARNING',
                        "Método {$methodName} requer parâmetros específicos", [
                            'module' => $moduleName,
                            'method' => $methodName,
                            'required_params' => $methodInfo['required_params'],
                            'attempted_params' => $testParams,
                            'error' => $e->getMessage()
                        ]);
                }
            } else {
                // Métodos com muitos parâmetros - apenas documentar
                $this->logResult($testKey, 'INFO',
                    "Método {$methodName} requer {$methodInfo['required_params']} parâmetros - pulado", [
                        'module' => $moduleName,
                        'method' => $methodName,
                        'required_params' => $methodInfo['required_params']
                    ]);
            }

        } catch (\Exception $e) {
            $this->logResult(strtoupper($moduleName) . '_METHOD_' . strtoupper($methodName), 'ERROR',
                "Erro ao testar método {$methodName}", [
                    'module' => $moduleName,
                    'method' => $methodName,
                    'error' => $e->getMessage(),
                    'type' => get_class($e)
                ]);
        }
    }

    private function generateTestParameters(string $methodName, int $paramCount): array
    {
        $params = [];

        for ($i = 0; $i < $paramCount; $i++) {
            // Gerar parâmetros baseados no nome do método e posição
            if (stripos($methodName, 'email') !== false) {
                $params[] = 'test@diagnostic.com';
            } elseif (stripos($methodName, 'id') !== false) {
                $params[] = 'diagnostic_test_id_' . time();
            } elseif (stripos($methodName, 'phone') !== false) {
                $params[] = '+5511999999999';
            } elseif (stripos($methodName, 'amount') !== false) {
                $params[] = 10000; // R$ 100,00 em centavos
            } elseif (stripos($methodName, 'currency') !== false) {
                $params[] = 'BRL';
            } elseif (stripos($methodName, 'url') !== false) {
                $params[] = 'https://diagnostic.test.com/webhook';
            } elseif (stripos($methodName, 'customize') !== false && $i === 1) {
                // Para métodos como customizeTheme, o segundo parâmetro é array
                $params[] = ['diagnostic' => 'test'];
            } elseif (stripos($methodName, 'theme') !== false && $i === 1) {
                // Para métodos de tema, segundo parâmetro geralmente é array
                $params[] = ['theme_data' => 'diagnostic'];
            } elseif (stripos($methodName, 'config') !== false || stripos($methodName, 'settings') !== false) {
                // Para métodos de configuração, usar array
                $params[] = ['diagnostic' => 'test_' . ($i + 1)];
            } elseif (stripos($methodName, 'reorder') !== false && $i === 1) {
                // Para métodos de reordenação, segundo parâmetro é array
                $params[] = ['order1', 'order2', 'order3'];
            } elseif (stripos($methodName, 'section') !== false && $i > 0) {
                // Para métodos de seção, parâmetros adicionais são arrays
                $params[] = ['section_data' => 'diagnostic'];
            } elseif (stripos($methodName, 'layout') !== false && $i === 1) {
                // Para métodos de layout, segundo parâmetro é array
                $params[] = ['layout_data' => 'diagnostic'];
            } else {
                // Parâmetro genérico baseado na posição
                $params[] = 'diagnostic_test_param_' . ($i + 1);
            }
        }

        return $params;
    }

    private function analyzeMethodResponse(string $testKey, $response, string $moduleName, string $methodName, array $params): void
    {
        $responseAnalysis = [
            'module' => $moduleName,
            'method' => $methodName,
            'parameters_used' => $params,
            'response_type' => gettype($response),
            'response_size' => is_string($response) ? strlen($response) : (is_array($response) ? count($response) : 'n/a')
        ];

        if (is_array($response)) {
            // Análise profunda para arrays
            $isMock = $this->detectMockDataAdvanced($response);
            $mockIndicators = $this->findMockIndicatorsAdvanced($response);

            $responseAnalysis['is_mock'] = $isMock;
            $responseAnalysis['mock_indicators'] = $mockIndicators;
            $responseAnalysis['has_success_key'] = isset($response['success']);
            $responseAnalysis['has_data_key'] = isset($response['data']);
            $responseAnalysis['has_error_key'] = isset($response['error']);
            $responseAnalysis['array_keys'] = array_keys($response);

            if ($isMock) {
                $this->mockDataDetected++;
                $responseAnalysis['mock_patterns'] = $this->analyzeMockPatternsAdvanced($response);
                $status = 'MOCK_DETECTED';
                $message = "Método {$methodName} retornou dados mock";
            } else {
                $status = 'REAL_DATA';
                $message = "Método {$methodName} retornou dados reais da API";
            }

        } elseif (is_string($response)) {
            // Análise para strings
            $isMock = $this->detectMockInString($response);
            $responseAnalysis['is_mock'] = $isMock;
            $responseAnalysis['string_preview'] = substr($response, 0, 100);

            if ($isMock) {
                $this->mockDataDetected++;
                $status = 'MOCK_DETECTED';
                $message = "Método {$methodName} retornou string mock";
            } else {
                $status = 'REAL_DATA';
                $message = "Método {$methodName} retornou string real";
            }

        } elseif (is_bool($response)) {
            $status = 'BOOLEAN_RESULT';
            $message = "Método {$methodName} retornou boolean: " . ($response ? 'true' : 'false');
            $responseAnalysis['boolean_value'] = $response;

        } elseif (is_null($response)) {
            $status = 'NULL_RESULT';
            $message = "Método {$methodName} retornou null";

        } else {
            $status = 'UNKNOWN_TYPE';
            $message = "Método {$methodName} retornou tipo desconhecido: " . gettype($response);
        }

        $this->logResult($testKey, $status, $message, $responseAnalysis);
    }

    private function detectMockDataAdvanced($data): bool
    {
        // Padrões mais sofisticados de detecção de mock
        $advancedMockPatterns = [
            // IDs previsíveis com padrões específicos
            '/^(test|mock|demo|sample)_\w+$/i',
            '/^(customer|user|order|payment)_\d{1,4}$/i',
            // Timestamps suspeitos (muito redondos ou específicos)
            '/^1[4-6]\d{8}00$/', // Timestamps que terminam em 00
            // Valores monetários suspeitos (múltiplos de 1000)
            '/^[1-9]\d*000$/',
            // Emails óbvios de teste
            '/@(test|example|demo|mock|localhost)\./i',
            // URLs de teste
            '/https?:\/\/(test|demo|mock|localhost|example)\./i'
        ];

        $jsonString = json_encode($data);

        foreach ($advancedMockPatterns as $pattern) {
            if (preg_match($pattern, $jsonString)) {
                return true;
            }
        }

        // Análise estrutural para dados muito "perfeitos"
        if (is_array($data)) {
            // Verificar se há muitos valores redondos
            $roundNumbers = $this->countRoundNumbers($data);
            $totalNumbers = $this->countNumbers($data);

            if ($totalNumbers > 0 && ($roundNumbers / $totalNumbers) > 0.7) {
                return true; // Mais de 70% dos números são redondos = suspeito
            }

            // Verificar se há muitos IDs sequenciais
            $ids = $this->extractIdsAdvanced($data);
            if (count($ids) > 2 && $this->areIdsSequential($ids)) {
                return true;
            }
        }

        return false;
    }

    private function findMockIndicatorsAdvanced($data): array
    {
        $indicators = [];
        $jsonString = json_encode($data);

        // Padrões básicos
        if (stripos($jsonString, 'test') !== false) $indicators[] = 'contains_test';
        if (stripos($jsonString, 'mock') !== false) $indicators[] = 'contains_mock';
        if (stripos($jsonString, 'demo') !== false) $indicators[] = 'contains_demo';
        if (stripos($jsonString, 'example') !== false) $indicators[] = 'contains_example';
        if (stripos($jsonString, 'sample') !== false) $indicators[] = 'contains_sample';

        // Padrões avançados
        if (preg_match('/\d{4}-01-01/', $jsonString)) $indicators[] = 'new_year_dates';
        if (preg_match('/12:00:00/', $jsonString)) $indicators[] = 'noon_times';
        if (preg_match('/".*":\s*"lorem\s+ipsum/i', $jsonString)) $indicators[] = 'lorem_ipsum';
        if (preg_match('/placeholder|dummy|fake/i', $jsonString)) $indicators[] = 'placeholder_text';

        return $indicators;
    }

    private function detectMockInString(string $str): bool
    {
        $mockStringPatterns = [
            'test', 'mock', 'demo', 'sample', 'example', 'placeholder',
            'lorem ipsum', 'dummy', 'fake', 'not implemented'
        ];

        foreach ($mockStringPatterns as $pattern) {
            if (stripos($str, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function analyzeMockPatternsAdvanced($data): array
    {
        return [
            'suspicious_timestamps' => $this->findSuspiciousTimestampsAdvanced($data),
            'round_numbers' => $this->findRoundNumbers($data),
            'test_emails' => $this->findTestEmailsAdvanced($data),
            'predictable_ids' => $this->findPredictableIdsAdvanced($data),
            'sequential_ids' => $this->findSequentialIds($data),
            'perfect_data' => $this->findPerfectDataIndicators($data)
        ];
    }

    private function countRoundNumbers($data): int
    {
        $count = 0;
        $this->countRoundNumbersRecursive($data, $count);
        return $count;
    }

    private function countRoundNumbersRecursive($data, &$count): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (is_numeric($value) && $value > 0 && $value % 1000 == 0) {
                    $count++;
                }
                if (is_array($value)) {
                    $this->countRoundNumbersRecursive($value, $count);
                }
            }
        }
    }

    private function countNumbers($data): int
    {
        $count = 0;
        $this->countNumbersRecursive($data, $count);
        return $count;
    }

    private function countNumbersRecursive($data, &$count): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (is_numeric($value)) {
                    $count++;
                }
                if (is_array($value)) {
                    $this->countNumbersRecursive($value, $count);
                }
            }
        }
    }

    private function extractIdsAdvanced($data): array
    {
        $ids = [];
        $this->extractIdsAdvancedRecursive($data, $ids);
        return $ids;
    }

    private function extractIdsAdvancedRecursive($data, &$ids): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && (str_contains($key, '_id') || $key === 'id')) {
                    if (is_string($value) || is_numeric($value)) {
                        $ids[] = (string)$value;
                    }
                }
                if (is_array($value)) {
                    $this->extractIdsAdvancedRecursive($value, $ids);
                }
            }
        }
    }

    private function areIdsSequential(array $ids): bool
    {
        if (count($ids) < 3) return false;

        // Extrair números dos IDs
        $numbers = [];
        foreach ($ids as $id) {
            if (preg_match('/(\d+)/', $id, $matches)) {
                $numbers[] = (int)$matches[1];
            }
        }

        if (count($numbers) < 3) return false;

        sort($numbers);

        // Verificar se são sequenciais
        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] - $numbers[$i-1] !== 1) {
                return false;
            }
        }

        return true;
    }

    private function findSuspiciousTimestampsAdvanced($data): array
    {
        $timestamps = [];
        $this->findTimestampsRecursive($data, $timestamps);

        $suspicious = [];
        foreach ($timestamps as $ts) {
            // Timestamps muito redondos ou óbvios
            if ($ts == 1234567890 || $ts == 1577836800 ||
                $ts % 86400 == 0 || // Meia-noite exata
                $ts % 3600 == 0 ||  // Hora exata
                date('i:s', $ts) == '00:00' // Minuto:segundo = 00:00
            ) {
                $suspicious[] = $ts;
            }
        }

        return $suspicious;
    }

    private function findTestEmailsAdvanced($data): array
    {
        $emails = [];
        $this->findEmailsRecursive($data, $emails);

        return array_filter($emails, function($email) {
            $testPatterns = [
                'test@', 'example@', 'demo@', 'mock@', 'sample@',
                '@test.', '@example.', '@demo.', '@mock.', '@localhost'
            ];

            foreach ($testPatterns as $pattern) {
                if (stripos($email, $pattern) !== false) {
                    return true;
                }
            }
            return false;
        });
    }

    private function findPredictableIdsAdvanced($data): array
    {
        $ids = $this->extractIdsAdvanced($data);
        return array_filter($ids, function($id) {
            return preg_match('/^(customer|user|order|payment|product)_\d{1,4}$/', $id) ||
                   preg_match('/^(test|mock|demo|sample)_/', $id);
        });
    }

    private function findSequentialIds($data): array
    {
        $ids = $this->extractIdsAdvanced($data);

        if ($this->areIdsSequential($ids)) {
            return $ids;
        }

        return [];
    }

    private function findPerfectDataIndicators($data): array
    {
        $indicators = [];

        // Verificar se todos os preços terminam em .00
        $prices = $this->extractPrices($data);
        if (count($prices) > 1) {
            $perfectPrices = array_filter($prices, function($price) {
                return fmod($price, 1.0) == 0; // Preço sem centavos
            });

            if (count($perfectPrices) / count($prices) > 0.8) {
                $indicators[] = 'all_prices_round';
            }
        }

        // Verificar se todas as datas são da mesma semana/mês
        $dates = $this->extractDates($data);
        if (count($dates) > 2 && $this->allDatesInSamePeriod($dates)) {
            $indicators[] = 'dates_same_period';
        }

        return $indicators;
    }

    private function extractPrices($data): array
    {
        $prices = [];
        $this->extractPricesRecursive($data, $prices);
        return $prices;
    }

    private function extractPricesRecursive($data, &$prices): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && (stripos($key, 'price') !== false ||
                    stripos($key, 'amount') !== false || stripos($key, 'cost') !== false)) {
                    if (is_numeric($value)) {
                        $prices[] = (float)$value;
                    }
                }
                if (is_array($value)) {
                    $this->extractPricesRecursive($value, $prices);
                }
            }
        }
    }

    private function extractDates($data): array
    {
        $dates = [];
        $this->extractDatesRecursive($data, $dates);
        return $dates;
    }

    private function extractDatesRecursive($data, &$dates): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && preg_match('/\d{4}-\d{2}-\d{2}/', $value)) {
                    $dates[] = $value;
                }
                if (is_array($value)) {
                    $this->extractDatesRecursive($value, $dates);
                }
            }
        }
    }

    private function allDatesInSamePeriod(array $dates): bool
    {
        if (count($dates) < 2) return false;

        $months = array_map(function($date) {
            return substr($date, 0, 7); // YYYY-MM
        }, $dates);

        return count(array_unique($months)) <= 1;
    }

    private function advancedMockAnalysis(): void
    {
        $this->printSection("🕵️ ANÁLISE AVANÇADA DE PADRÕES MOCK");

        $totalMockMethods = 0;
        $totalRealMethods = 0;
        $moduleAnalysis = [];

        foreach ($this->moduleStats as $moduleName => $moduleInfo) {
            $moduleMockCount = 0;
            $moduleRealCount = 0;

            // Contar métodos mock vs reais para cada módulo
            foreach ($this->results as $result) {
                if (strpos($result['test'], strtoupper($moduleName) . '_METHOD_') === 0) {
                    if ($result['status'] === 'MOCK_DETECTED') {
                        $moduleMockCount++;
                    } elseif ($result['status'] === 'REAL_DATA') {
                        $moduleRealCount++;
                    }
                }
            }

            $totalModuleMethods = $moduleMockCount + $moduleRealCount;
            $moduleRealPercentage = $totalModuleMethods > 0 ?
                round(($moduleRealCount / $totalModuleMethods) * 100, 2) : 0;

            $moduleAnalysis[$moduleName] = [
                'total_methods_tested' => $totalModuleMethods,
                'mock_methods' => $moduleMockCount,
                'real_methods' => $moduleRealCount,
                'real_percentage' => $moduleRealPercentage
            ];

            $totalMockMethods += $moduleMockCount;
            $totalRealMethods += $moduleRealCount;

            $this->logResult('MODULE_ANALYSIS_' . strtoupper($moduleName), 'INFO',
                "Módulo {$moduleName}: {$moduleRealPercentage}% dados reais", $moduleAnalysis[$moduleName]);
        }

        $overallRealPercentage = ($totalMockMethods + $totalRealMethods) > 0 ?
            round(($totalRealMethods / ($totalMockMethods + $totalRealMethods)) * 100, 2) : 0;

        $this->logResult('ADVANCED_MOCK_ANALYSIS', 'INFO',
            "Análise completa: {$overallRealPercentage}% de dados reais detectados", [
                'total_methods_with_data' => $totalMockMethods + $totalRealMethods,
                'mock_methods' => $totalMockMethods,
                'real_methods' => $totalRealMethods,
                'overall_real_percentage' => $overallRealPercentage,
                'module_breakdown' => $moduleAnalysis
            ]);
    }

    // Métodos auxiliares (copiados do script original com melhorias)
    private function testConnectivity(): void
    {
        $this->printSection("🔌 TESTE DE CONECTIVIDADE AVANÇADA");

        try {
            $baseUrl = $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1';

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => "User-Agent: ClubifySDK-ImprovedDiagnostic/2.0\r\n"
                ]
            ]);

            $startTime = microtime(true);
            $response = @file_get_contents($baseUrl . '/health', false, $context);
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($response !== false) {
                $this->logResult('HTTP_CONNECTIVITY_ADVANCED', 'SUCCESS', "Conectividade HTTP OK", [
                    'base_url' => $baseUrl,
                    'response_time' => $responseTime . 'ms',
                    'response_size' => strlen($response) . ' bytes'
                ]);
            } else {
                $this->logResult('HTTP_CONNECTIVITY_ADVANCED', 'WARNING', "Endpoint /health não disponível", [
                    'base_url' => $baseUrl,
                    'attempted_time' => $responseTime . 'ms'
                ]);
            }
        } catch (\Exception $e) {
            $this->logResult('HTTP_CONNECTIVITY_ADVANCED', 'ERROR', "Falha na conectividade HTTP", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function testSDKStatus(): void
    {
        $this->printSection("📊 STATUS AVANÇADO DO SDK");

        try {
            $stats = $this->sdk->getStats();
            $this->logResult('SDK_STATS_ADVANCED', 'SUCCESS', "Estatísticas do SDK obtidas", $stats);

            $version = $this->sdk->getVersion();
            $this->logResult('SDK_VERSION_ADVANCED', 'SUCCESS', "Versão: $version");

            $isInitialized = $this->sdk->isInitialized();
            $this->logResult('SDK_INITIALIZED_ADVANCED', $isInitialized ? 'SUCCESS' : 'WARNING',
                "Status de inicialização: " . ($isInitialized ? 'Inicializado' : 'Não inicializado'));

        } catch (\Exception $e) {
            $this->logResult('SDK_STATUS_ADVANCED', 'ERROR', "Erro ao obter status do SDK", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateDirectAPIEndpoints(): void
    {
        $this->printSection("🌐 VALIDAÇÃO DIRETA DE ENDPOINTS DA API");

        $endpoints = [
            'health' => '/health',
            'organizations' => '/organizations',
            'products' => '/products',
            'payments' => '/payments',
            'customers' => '/customers',
            'orders' => '/orders'
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
                'User-Agent: ClubifySDK-ImprovedDiagnostic/2.0',
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
                    'timeout' => 15,
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

                $this->logResult('ENDPOINT_DIRECT_' . strtoupper($name), 'SUCCESS',
                    "Endpoint $endpoint respondeu diretamente", [
                        'url' => $fullUrl,
                        'response_time' => $responseTime . 'ms',
                        'response_size' => strlen($response) . ' bytes',
                        'valid_json' => $isValidJson,
                        'response_preview' => substr($response, 0, 200) . '...'
                    ]);

                if ($isValidJson && $decodedResponse) {
                    $isMock = $this->detectMockDataAdvanced($decodedResponse);
                    if ($isMock) {
                        $this->mockDataDetected++;
                    }
                }
            } else {
                $httpResponseHeader = $http_response_header ?? [];
                $this->logResult('ENDPOINT_DIRECT_' . strtoupper($name), 'ERROR',
                    "Endpoint $endpoint não respondeu diretamente", [
                        'url' => $fullUrl,
                        'attempted_time' => $responseTime . 'ms',
                        'headers' => $httpResponseHeader
                    ]);
            }
        } catch (\Exception $e) {
            $this->logResult('ENDPOINT_DIRECT_' . strtoupper($name), 'ERROR',
                "Erro ao testar endpoint direto $endpoint", [
                    'error' => $e->getMessage()
                ]);
        }
    }

    // Métodos auxiliares necessários (implementações simplificadas)
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

    private function logResult(string $test, string $status, string $message, array $data = []): void
    {
        $this->totalTests++;

        if ($status === 'SUCCESS' || $status === 'REAL_DATA') {
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
            'SUCCESS', 'REAL_DATA' => '✅',
            'ERROR' => '❌',
            'WARNING' => '⚠️',
            'MOCK_DETECTED' => '🎭',
            'INFO' => 'ℹ️',
            'BOOLEAN_RESULT' => '🔘',
            'NULL_RESULT' => '⭕',
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
        echo "                   🚀 CLUBIFY SDK IMPROVED DIAGNOSTIC SCRIPT                   \n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n";
        echo "✨ VERSÃO MELHORADA - Descoberta automática via Reflection PHP\n";
        echo "🔍 Análise abrangente de TODOS os métodos disponíveis\n";
        echo "🎯 Detecção inteligente de padrões mock vs dados reais\n";
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

    private function printComprehensiveSummary(): void
    {
        $endTime = microtime(true);
        $totalTime = round($endTime - $this->startTime, 2);

        $this->printSection("📊 RELATÓRIO FINAL ABRANGENTE");

        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                               RESUMO GERAL                                  ║\n";
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
        echo "║                         ANÁLISE DE QUALIDADE AVANÇADA                       ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";

        if ($successRate >= 90) {
            echo "║ 🎉  EXCELENTE: Taxa de sucesso superior a 90%                           ║\n";
        } elseif ($successRate >= 70) {
            echo "║ 👍  BOM: Taxa de sucesso entre 70-90%                                   ║\n";
        } else {
            echo "║ ⚠️  ATENÇÃO: Taxa de sucesso abaixo de 70%                              ║\n";
        }

        if ($mockRate <= 5) {
            echo "║ 🌐  EXCELENTE: Quase 100% dados reais (conectando com API real)        ║\n";
        } elseif ($mockRate <= 15) {
            echo "║ 👍  BOM: Baixo percentual de dados mock                                 ║\n";
        } elseif ($mockRate <= 50) {
            echo "║ 🔧  MISTO: Alguns dados mock (normal para desenvolvimento)              ║\n";
        } else {
            echo "║ 🎭  ATENÇÃO: Alto percentual de dados mock (verificar configuração)     ║\n";
        }

        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        // Estatísticas dos módulos descobertos
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                          MÓDULOS DESCOBERTOS                                ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";

        foreach ($this->moduleStats as $moduleName => $stats) {
            $methodCount = $stats['total_methods'] ?? 0;
            echo sprintf("║ 📦 %-20s %s métodos descobertos                           ║\n",
                ucfirst($moduleName) . ':', str_pad($methodCount, 20));
        }

        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

        // Salvar log completo
        $this->saveComprehensiveResults();
    }

    private function saveComprehensiveResults(): void
    {
        $summary = [
            'diagnostic_info' => [
                'script_version' => '2.0_improved',
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
            'discovered_modules' => $this->moduleStats,
            'detailed_results' => $this->results
        ];

        file_put_contents(__DIR__ . '/improved-diagnostic-results.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "📄 Relatório abrangente salvo em: improved-diagnostic-results.json\n\n";
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

// Executar diagnóstico melhorado
try {
    $diagnostic = new ImprovedSDKDiagnostic();
    $diagnostic->runComprehensiveDiagnostic();
} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}