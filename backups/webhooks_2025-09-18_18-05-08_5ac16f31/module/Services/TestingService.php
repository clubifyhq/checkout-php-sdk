<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;

/**
 * Serviço de testes de webhooks
 *
 * Fornece funcionalidades completas de teste e debug
 * incluindo testes de conectividade, simulação de eventos,
 * validação de configuração e debugging avançado.
 */
class TestingService extends BaseService implements ServiceInterface
{
    private array $testEventTypes = [
        'webhook.test' => 'Evento de teste básico',
        'order.created' => 'Pedido criado',
        'order.completed' => 'Pedido concluído',
        'order.cancelled' => 'Pedido cancelado',
        'payment.completed' => 'Pagamento concluído',
        'payment.failed' => 'Pagamento falhou',
        'user.registered' => 'Usuário registrado',
        'user.updated' => 'Usuário atualizado',
    ];

    private array $testResults = [];

    public function __construct(
        private DeliveryService $deliveryService,
        private WebhookRepositoryInterface $repository
    ) {
        // Parent constructor will be called by Factory with proper dependencies
    }

    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'testing';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Testa webhook individual
     */
    public function testWebhook(string $webhookId, array $options = []): array
    {
        $startTime = microtime(true);
        $testId = uniqid('test_', true);

        $result = [
            'test_id' => $testId,
            'webhook_id' => $webhookId,
            'success' => false,
            'tests' => [],
            'summary' => [],
            'duration' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        try {
            // Busca webhook
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
            }

            // Executa testes
            $result['tests']['connectivity'] = $this->testConnectivity($webhook);
            $result['tests']['configuration'] = $this->testConfiguration($webhook);
            $result['tests']['delivery'] = $this->testDelivery($webhook, $options);
            $result['tests']['security'] = $this->testSecurity($webhook);

            // Calcula sumário
            $result['summary'] = $this->calculateTestSummary($result['tests']);
            $result['success'] = $result['summary']['all_passed'];

            $this->logger->info('Teste de webhook concluído', [
                'test_id' => $testId,
                'webhook_id' => $webhookId,
                'success' => $result['success'],
                'tests_passed' => $result['summary']['passed'],
                'tests_failed' => $result['summary']['failed'],
            ]);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            $this->logger->error('Erro no teste de webhook', [
                'test_id' => $testId,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);
        }

        $result['duration'] = microtime(true) - $startTime;
        $this->testResults[$testId] = $result;

        return $result;
    }

    /**
     * Testa múltiplos webhooks
     */
    public function testMultipleWebhooks(array $webhookIds, array $options = []): array
    {
        $results = [
            'batch_id' => uniqid('batch_', true),
            'webhook_count' => count($webhookIds),
            'tests' => [],
            'summary' => [
                'successful_webhooks' => 0,
                'failed_webhooks' => 0,
                'total_tests' => 0,
                'passed_tests' => 0,
                'failed_tests' => 0,
            ],
            'duration' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $startTime = microtime(true);

        foreach ($webhookIds as $webhookId) {
            $testResult = $this->testWebhook($webhookId, $options);
            $results['tests'][$webhookId] = $testResult;

            // Atualiza sumário
            if ($testResult['success']) {
                $results['summary']['successful_webhooks']++;
            } else {
                $results['summary']['failed_webhooks']++;
            }

            $results['summary']['total_tests'] += $testResult['summary']['total'] ?? 0;
            $results['summary']['passed_tests'] += $testResult['summary']['passed'] ?? 0;
            $results['summary']['failed_tests'] += $testResult['summary']['failed'] ?? 0;
        }

        $results['duration'] = microtime(true) - $startTime;

        $this->logger->info('Teste em lote de webhooks concluído', [
            'batch_id' => $results['batch_id'],
            'webhook_count' => $results['webhook_count'],
            'successful_webhooks' => $results['summary']['successful_webhooks'],
            'failed_webhooks' => $results['summary']['failed_webhooks'],
        ]);

        return $results;
    }

    /**
     * Simula evento específico
     */
    public function simulateEvent(string $webhookId, string $eventType, array $eventData = []): array
    {
        try {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
            }

            // Prepara dados do evento
            $simulatedData = $this->generateEventData($eventType, $eventData);

            // Faz entrega de teste
            $delivery = $this->deliveryService->deliver($webhook, $eventType, $simulatedData, [
                'test_mode' => true,
                'simulation' => true,
            ]);

            $this->logger->info('Evento simulado para webhook', [
                'webhook_id' => $webhookId,
                'event_type' => $eventType,
                'delivery_success' => $delivery['success'],
            ]);

            return $delivery;

        } catch (\Exception $e) {
            $this->logger->error('Erro na simulação de evento', [
                'webhook_id' => $webhookId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valida configuração de webhook
     */
    public function validateWebhookConfiguration(array $webhookConfig): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => [],
        ];

        // Valida URL
        if (empty($webhookConfig['url'])) {
            $validation['errors'][] = 'URL é obrigatória';
        } elseif (!filter_var($webhookConfig['url'], FILTER_VALIDATE_URL)) {
            $validation['errors'][] = 'URL inválida';
        } else {
            $urlValidation = $this->repository->validateUrl($webhookConfig['url']);
            if (!$urlValidation['reachable']) {
                $validation['warnings'][] = 'URL não está acessível';
            }
        }

        // Valida eventos
        if (empty($webhookConfig['events'])) {
            $validation['errors'][] = 'Pelo menos um evento deve ser configurado';
        } else {
            foreach ($webhookConfig['events'] as $event) {
                if (!$this->isValidEventType($event)) {
                    $validation['warnings'][] = "Tipo de evento não reconhecido: {$event}";
                }
            }
        }

        // Valida secret
        if (empty($webhookConfig['secret'])) {
            $validation['warnings'][] = 'Secret não configurado - webhooks não serão assinados';
        } elseif (strlen($webhookConfig['secret']) < 32) {
            $validation['warnings'][] = 'Secret muito curto - recomendado pelo menos 32 caracteres';
        }

        // Valida timeout
        $timeout = $webhookConfig['timeout'] ?? 30;
        if ($timeout < 5) {
            $validation['warnings'][] = 'Timeout muito baixo - pode causar falhas desnecessárias';
        } elseif ($timeout > 60) {
            $validation['warnings'][] = 'Timeout muito alto - pode impactar performance';
        }

        // Valida configuração de retry
        if (isset($webhookConfig['retry_enabled']) && $webhookConfig['retry_enabled']) {
            $maxRetries = $webhookConfig['max_retries'] ?? 5;
            if ($maxRetries > 10) {
                $validation['warnings'][] = 'Muitas tentativas de retry - pode sobrecarregar o sistema';
            }
        }

        // Sugestões
        if (!isset($webhookConfig['retry_enabled'])) {
            $validation['suggestions'][] = 'Considere habilitar retry automático para maior confiabilidade';
        }

        if (empty($webhookConfig['description'])) {
            $validation['suggestions'][] = 'Adicione uma descrição para facilitar a manutenção';
        }

        $validation['valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Gera relatório de debug
     */
    public function generateDebugReport(string $webhookId): array
    {
        try {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
            }

            $report = [
                'webhook_id' => $webhookId,
                'webhook_config' => $webhook,
                'connectivity_test' => $this->testConnectivity($webhook),
                'recent_deliveries' => $this->getRecentDeliveries($webhookId),
                'failure_analysis' => $this->analyzeFailures($webhookId),
                'performance_metrics' => $this->getPerformanceMetrics($webhookId),
                'recommendations' => $this->generateRecommendations($webhook),
                'generated_at' => date('Y-m-d H:i:s'),
            ];

            $this->logger->info('Relatório de debug gerado', [
                'webhook_id' => $webhookId,
            ]);

            return $report;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao gerar relatório de debug', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Lista tipos de eventos disponíveis
     */
    public function getAvailableEventTypes(): array
    {
        return $this->testEventTypes;
    }

    /**
     * Obtém resultado de teste específico
     */
    public function getTestResult(string $testId): ?array
    {
        return $this->testResults[$testId] ?? null;
    }

    /**
     * Testa conectividade
     */
    private function testConnectivity(array $webhook): array
    {
        $test = [
            'name' => 'Teste de Conectividade',
            'success' => false,
            'details' => [],
            'duration' => 0,
        ];

        $startTime = microtime(true);

        try {
            $urlValidation = $this->repository->validateUrl($webhook['url']);

            $test['success'] = $urlValidation['reachable'];
            $test['details'] = $urlValidation;

            if (!$test['success']) {
                $test['error'] = 'URL não é acessível';
            }

        } catch (\Exception $e) {
            $test['error'] = $e->getMessage();
        }

        $test['duration'] = microtime(true) - $startTime;

        return $test;
    }

    /**
     * Testa configuração
     */
    private function testConfiguration(array $webhook): array
    {
        $test = [
            'name' => 'Teste de Configuração',
            'success' => false,
            'details' => [],
            'duration' => 0,
        ];

        $startTime = microtime(true);

        try {
            $validation = $this->validateWebhookConfiguration($webhook);

            $test['success'] = $validation['valid'];
            $test['details'] = $validation;

            if (!$test['success']) {
                $test['error'] = implode(', ', $validation['errors']);
            }

        } catch (\Exception $e) {
            $test['error'] = $e->getMessage();
        }

        $test['duration'] = microtime(true) - $startTime;

        return $test;
    }

    /**
     * Testa entrega
     */
    private function testDelivery(array $webhook, array $options): array
    {
        $test = [
            'name' => 'Teste de Entrega',
            'success' => false,
            'details' => [],
            'duration' => 0,
        ];

        $startTime = microtime(true);

        try {
            $eventType = $options['test_event_type'] ?? 'webhook.test';
            $eventData = $options['test_event_data'] ?? ['test' => true];

            $delivery = $this->deliveryService->testDelivery($webhook, $eventData);

            $test['success'] = $delivery['success'];
            $test['details'] = $delivery;

            if (!$test['success']) {
                $test['error'] = $delivery['error'] ?? 'Entrega falhou';
            }

        } catch (\Exception $e) {
            $test['error'] = $e->getMessage();
        }

        $test['duration'] = microtime(true) - $startTime;

        return $test;
    }

    /**
     * Testa segurança
     */
    private function testSecurity(array $webhook): array
    {
        $test = [
            'name' => 'Teste de Segurança',
            'success' => false,
            'details' => [],
            'duration' => 0,
        ];

        $startTime = microtime(true);

        try {
            $details = [];

            // Verifica HTTPS
            $parsedUrl = parse_url($webhook['url']);
            $details['https'] = $parsedUrl['scheme'] === 'https';

            // Verifica secret
            $details['has_secret'] = !empty($webhook['secret']);
            $details['secret_strength'] = $this->evaluateSecretStrength($webhook['secret'] ?? '');

            // Verifica headers de segurança
            $details['security_headers'] = $this->checkSecurityHeaders($webhook);

            $test['success'] = $details['https'] && $details['has_secret'];
            $test['details'] = $details;

            if (!$test['success']) {
                $errors = [];
                if (!$details['https']) {
                    $errors[] = 'HTTPS não configurado';
                }
                if (!$details['has_secret']) {
                    $errors[] = 'Secret não configurado';
                }
                $test['error'] = implode(', ', $errors);
            }

        } catch (\Exception $e) {
            $test['error'] = $e->getMessage();
        }

        $test['duration'] = microtime(true) - $startTime;

        return $test;
    }

    /**
     * Calcula sumário dos testes
     */
    private function calculateTestSummary(array $tests): array
    {
        $total = count($tests);
        $passed = count(array_filter($tests, fn ($test) => $test['success']));
        $failed = $total - $passed;

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'all_passed' => $failed === 0,
            'success_rate' => $total > 0 ? $passed / $total : 0,
        ];
    }

    /**
     * Gera dados de evento para teste
     */
    private function generateEventData(string $eventType, array $customData = []): array
    {
        $baseData = [
            'timestamp' => date('c'),
            'test_mode' => true,
            'event_id' => uniqid('event_', true),
        ];

        $eventSpecificData = match ($eventType) {
            'order.created', 'order.completed', 'order.cancelled' => [
                'order_id' => 'test_order_' . rand(1000, 9999),
                'customer_id' => 'test_customer_' . rand(100, 999),
                'amount' => rand(1000, 50000) / 100,
                'currency' => 'BRL',
            ],
            'payment.completed', 'payment.failed' => [
                'payment_id' => 'test_payment_' . rand(1000, 9999),
                'order_id' => 'test_order_' . rand(1000, 9999),
                'amount' => rand(1000, 50000) / 100,
                'currency' => 'BRL',
                'method' => 'credit_card',
            ],
            'user.registered', 'user.updated' => [
                'user_id' => 'test_user_' . rand(1000, 9999),
                'email' => 'test' . rand(100, 999) . '@example.com',
                'name' => 'Test User ' . rand(1, 100),
            ],
            default => [
                'message' => 'Test event for webhook validation',
            ],
        };

        return array_merge($baseData, $eventSpecificData, $customData);
    }

    /**
     * Verifica se tipo de evento é válido
     */
    private function isValidEventType(string $eventType): bool
    {
        return $eventType === '*' || isset($this->testEventTypes[$eventType]);
    }

    /**
     * Avalia força do secret
     */
    private function evaluateSecretStrength(string $secret): array
    {
        return [
            'length' => strlen($secret),
            'strength' => match (true) {
                strlen($secret) < 16 => 'weak',
                strlen($secret) < 32 => 'medium',
                default => 'strong',
            },
            'recommendations' => strlen($secret) < 32 ? ['Use pelo menos 32 caracteres para maior segurança'] : [],
        ];
    }

    /**
     * Verifica headers de segurança
     */
    private function checkSecurityHeaders(array $webhook): array
    {
        // Em uma implementação real, faria requisição HEAD para verificar headers
        return [
            'strict_transport_security' => false,
            'content_security_policy' => false,
            'x_frame_options' => false,
            'recommendations' => ['Configure headers de segurança no servidor de destino'],
        ];
    }

    /**
     * Obtém entregas recentes
     */
    private function getRecentDeliveries(string $webhookId): array
    {
        try {
            return $this->repository->findDeliveryLogs($webhookId, ['limit' => 10]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Analisa falhas
     */
    private function analyzeFailures(string $webhookId): array
    {
        try {
            $failedDeliveries = $this->repository->findFailedDeliveries('7 days', ['webhook_id' => $webhookId]);

            $analysis = [
                'total_failures' => count($failedDeliveries),
                'common_errors' => [],
                'failure_patterns' => [],
            ];

            // Agrupa por tipo de erro
            $errorCounts = [];
            foreach ($failedDeliveries as $delivery) {
                $error = $delivery['error'] ?? 'Unknown error';
                $errorCounts[$error] = ($errorCounts[$error] ?? 0) + 1;
            }

            arsort($errorCounts);
            $analysis['common_errors'] = array_slice($errorCounts, 0, 5, true);

            return $analysis;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Obtém métricas de performance
     */
    private function getPerformanceMetrics(string $webhookId): array
    {
        try {
            return $this->repository->getWebhookStats($webhookId, '7 days');
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Gera recomendações
     */
    private function generateRecommendations(array $webhook): array
    {
        $recommendations = [];

        // Analisa configuração e sugere melhorias
        if (empty($webhook['secret'])) {
            $recommendations[] = 'Configure um secret para validação de assinatura HMAC';
        }

        if (($webhook['timeout'] ?? 30) > 45) {
            $recommendations[] = 'Considere reduzir o timeout para melhorar a responsividade';
        }

        if (!($webhook['retry_enabled'] ?? true)) {
            $recommendations[] = 'Habilite retry automático para maior confiabilidade';
        }

        return $recommendations;
    }
}
