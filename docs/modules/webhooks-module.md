# 🔗 Webhooks Module - Documentação Completa

## Visão Geral

O **Webhooks Module** é responsável pelo gerenciamento completo de webhooks, incluindo configuração, entrega confiável, sistema de retry automático, validação de assinatura HMAC e ferramentas de teste e debug abrangentes.

### 🎯 Funcionalidades Principais

- **Entrega Confiável**: Sistema robusto de entrega com retry automático e circuit breaker
- **Validação de Assinatura**: Verificação HMAC para segurança e integridade
- **Sistema de Retry**: Retry inteligente com backoff exponencial e dead letter queue
- **Rate Limiting**: Controle de taxa para proteção contra spam
- **Testes e Debug**: Ferramentas completas para teste e debug de webhooks
- **Logs de Auditoria**: Rastreamento completo de todas as entregas e tentativas
- **Filtros de Eventos**: Sistema flexível de filtros para personalização

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** com foco em confiabilidade e observabilidade:

```
WebhooksModule
├── Services/
│   ├── WebhookService       # CRUD de webhooks
│   ├── ConfigService        # Configuração e validação
│   ├── DeliveryService      # Entrega de eventos
│   ├── RetryService         # Sistema de retry
│   └── TestingService       # Testes e debug
├── Repositories/
│   └── WebhookRepository    # Persistência
├── DTOs/
│   ├── WebhookData          # DTO de webhook
│   ├── EventData            # DTO de evento
│   └── WebhookStatsData     # DTO de estatísticas
└── Exceptions/
    ├── WebhookException
    ├── WebhookDeliveryException
    └── WebhookConfigException
```

## 📚 API Reference

### WebhooksModule

#### Métodos de Configuração

##### `setupWebhook(array $webhookData): array`

Configura um webhook completo com teste automático de conectividade.

**Parâmetros:**
```php
$webhookData = [
    'url' => 'https://api.exemplo.com/webhook',       // Required
    'events' => ['order.created', 'payment.approved'], // Required
    'secret' => 'webhook_secret_key',                 // Required para assinatura
    'active' => true,                                 // Optional (default: true)
    'name' => 'Webhook Principal',                    // Optional
    'description' => 'Webhook para eventos de pedidos', // Optional
    'retry_enabled' => true,                          // Optional (default: true)
    'max_retries' => 5,                              // Optional (default: 5)
    'timeout_seconds' => 30,                         // Optional (default: 30)
    'headers' => [                                   // Optional
        'Authorization' => 'Bearer token_123',
        'Content-Type' => 'application/json'
    ],
    'filters' => [                                   // Optional
        'order.created' => [
            'amount' => ['min' => 1000],             // Apenas pedidos > R$ 10
            'status' => ['in' => ['paid', 'completed']]
        ]
    ],
    'format' => 'json',                              // Optional (json/xml/form)
    'test_on_create' => true                         // Optional (default: true)
];
```

**Retorno:**
```php
[
    'id' => 'webhook_123456',
    'url' => 'https://api.exemplo.com/webhook',
    'events' => ['order.created', 'payment.approved'],
    'secret' => 'webhook_secret_key',
    'active' => true,
    'name' => 'Webhook Principal',
    'retry_enabled' => true,
    'max_retries' => 5,
    'timeout_seconds' => 30,
    'headers' => [...],
    'filters' => [...],
    'stats' => [
        'total_deliveries' => 0,
        'successful_deliveries' => 0,
        'failed_deliveries' => 0,
        'average_response_time' => 0
    ],
    'test_result' => [
        'success' => true,
        'response_time' => 125,
        'status_code' => 200,
        'response_body' => 'OK'
    ],
    'created_at' => '2025-01-16T10:00:00Z',
    'updated_at' => '2025-01-16T10:00:00Z'
]
```

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

// Configurar webhook principal
$webhook = $sdk->webhooks()->setupWebhook([
    'url' => 'https://meusite.com/api/webhook',
    'events' => [
        'order.created',
        'order.completed',
        'payment.approved',
        'payment.failed',
        'subscription.created',
        'subscription.cancelled'
    ],
    'secret' => 'meu_webhook_secret_super_seguro',
    'name' => 'Webhook Sistema Principal',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'X-Source' => 'clubify-checkout'
    ],
    'filters' => [
        'order.created' => [
            'amount' => ['min' => 1000], // Apenas pedidos acima de R$ 10
            'status' => ['in' => ['paid', 'processing']]
        ]
    ],
    'retry_enabled' => true,
    'max_retries' => 3
]);

echo "Webhook configurado: {$webhook['id']}\n";
echo "Teste de conectividade: " . ($webhook['test_result']['success'] ? 'OK' : 'FALHA') . "\n";
```

#### Métodos de Entrega

##### `deliverEvent(string $eventType, array $eventData, array $options = []): array`

Entrega um evento para todos os webhooks configurados.

**Parâmetros:**
```php
$eventType = 'order.created';
$eventData = [
    'id' => 'order_123',
    'customer_id' => 'cust_456',
    'amount' => 9900,
    'currency' => 'BRL',
    'status' => 'paid',
    'items' => [
        [
            'product_id' => 'prod_789',
            'quantity' => 1,
            'price' => 9900
        ]
    ],
    'created_at' => '2025-01-16T10:00:00Z'
];

$options = [
    'priority' => 'high',           // high/normal/low
    'delay_seconds' => 0,           // Atraso na entrega
    'max_retries_override' => 3     // Override de retry para este evento
];
```

**Retorno:**
```php
[
    'event_type' => 'order.created',
    'webhooks_found' => 2,
    'deliveries' => [
        [
            'webhook_id' => 'webhook_123',
            'delivery_id' => 'delivery_456',
            'success' => true,
            'status_code' => 200,
            'response_time' => 150,
            'response_body' => 'OK',
            'delivered_at' => '2025-01-16T10:00:05Z'
        ],
        [
            'webhook_id' => 'webhook_789',
            'delivery_id' => 'delivery_101',
            'success' => false,
            'status_code' => 500,
            'error' => 'Internal Server Error',
            'response_time' => 30000,
            'retry_scheduled' => true,
            'next_retry_at' => '2025-01-16T10:05:00Z'
        ]
    ]
]
```

**Exemplo de Uso:**
```php
// Entregar evento de novo pedido
$result = $sdk->webhooks()->deliverEvent('order.created', [
    'id' => 'order_' . uniqid(),
    'customer' => [
        'id' => 'cust_123',
        'name' => 'João Silva',
        'email' => 'joao@exemplo.com'
    ],
    'order' => [
        'amount' => 29900,
        'currency' => 'BRL',
        'items' => [
            [
                'product_id' => 'prod_curso_php',
                'name' => 'Curso de PHP Avançado',
                'quantity' => 1,
                'price' => 29900
            ]
        ]
    ],
    'payment' => [
        'method' => 'credit_card',
        'status' => 'approved',
        'gateway' => 'stripe'
    ],
    'metadata' => [
        'source' => 'website',
        'campaign' => 'black_friday'
    ]
], [
    'priority' => 'high'
]);

echo "Webhooks encontrados: {$result['webhooks_found']}\n";
echo "Entregas realizadas: " . count($result['deliveries']) . "\n";

foreach ($result['deliveries'] as $delivery) {
    if ($delivery['success']) {
        echo "✅ Webhook {$delivery['webhook_id']}: Entregue com sucesso\n";
    } else {
        echo "❌ Webhook {$delivery['webhook_id']}: Falha - {$delivery['error']}\n";
        if ($delivery['retry_scheduled']) {
            echo "🔄 Retry agendado para: {$delivery['next_retry_at']}\n";
        }
    }
}
```

#### Métodos de Retry

##### `processRetries(int $limit = 100): array`

Processa retries pendentes na fila.

**Exemplo de Uso:**
```php
// Processar retries pendentes (executar em cron job)
$retryResults = $sdk->webhooks()->processRetries(50);

echo "Retries processados: {$retryResults['processed']}\n";
echo "Sucessos: {$retryResults['successful']}\n";
echo "Falhas: {$retryResults['failed']}\n";
echo "Permanentes (dead letter): {$retryResults['permanent_failures']}\n";
```

#### Métodos de Validação

##### `validateSignature(string $payload, string $signature, string $secret): bool`

Valida assinatura HMAC de webhook recebido.

**Exemplo de Uso no Endpoint:**
```php
// No seu endpoint que recebe webhooks
class WebhookController
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');
        $secret = config('webhooks.secret');

        // Validar assinatura
        $isValid = $sdk->webhooks()->validateSignature($payload, $signature, $secret);

        if (!$isValid) {
            return response('Invalid signature', 401);
        }

        // Processar webhook
        $event = json_decode($payload, true);

        switch ($event['type']) {
            case 'order.created':
                $this->handleOrderCreated($event['data']);
                break;
            case 'payment.approved':
                $this->handlePaymentApproved($event['data']);
                break;
        }

        return response('OK', 200);
    }
}
```

#### Services Disponíveis

##### `webhooks(): WebhookService`

Retorna o serviço de gestão de webhooks.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar webhook
- `find(string $id): ?array` - Buscar webhook
- `update(string $id, array $data): array` - Atualizar webhook
- `delete(string $id): bool` - Excluir webhook
- `findByEvent(string $eventType): array` - Webhooks por evento
- `list(array $filters): array` - Listar webhooks
- `count(array $filters): int` - Contar webhooks

##### `delivery(): DeliveryService`

Retorna o serviço de entrega.

**Métodos Disponíveis:**
- `deliver(array $webhook, string $eventType, array $data, array $options): array` - Entregar
- `getDeliveryHistory(string $webhookId, array $filters): array` - Histórico
- `getFailedCount(string $period): int` - Contagem de falhas
- `getFailureRate(string $period): float` - Taxa de falhas
- `cleanupOldDeliveries(int $days): int` - Limpeza

##### `retry(): RetryService`

Retorna o serviço de retry.

**Métodos Disponíveis:**
- `scheduleRetry(string $webhookId, string $deliveryId): array` - Agendar retry
- `processPendingRetries(int $limit): array` - Processar pendentes
- `getQueueSize(): int` - Tamanho da fila
- `getRetryHistory(string $deliveryId): array` - Histórico de retry
- `cleanupOldRetries(int $days): int` - Limpeza

##### `testing(): TestingService`

Retorna o serviço de testes.

**Métodos Disponíveis:**
- `testWebhook(string $webhookId): array` - Testar webhook
- `sendTestEvent(string $webhookId, string $eventType, array $data): array` - Evento teste
- `validateEndpoint(string $url): array` - Validar endpoint
- `debugDelivery(string $deliveryId): array` - Debug entrega

##### `config(): ConfigService`

Retorna o serviço de configuração.

**Métodos Disponíveis:**
- `updateGlobalConfig(array $config): void` - Atualizar config global
- `getGlobalConfig(): array` - Obter config global
- `eventPassesFilters(string $eventType, array $data, array $webhook): bool` - Validar filtros
- `validateWebhookConfig(array $config): array` - Validar configuração

## 💡 Exemplos Práticos

### Sistema Completo de Webhooks

```php
// Implementação completa de sistema de webhooks
class WebhookManager
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function setupWebhooksForOrganization($organizationId, $endpoints)
    {
        $webhooks = [];

        foreach ($endpoints as $endpoint) {
            $webhook = $this->sdk->webhooks()->setupWebhook([
                'url' => $endpoint['url'],
                'events' => $endpoint['events'],
                'secret' => $this->generateSecret($organizationId),
                'name' => $endpoint['name'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $endpoint['token'],
                    'X-Organization-ID' => $organizationId
                ],
                'filters' => $endpoint['filters'] ?? [],
                'retry_enabled' => true,
                'max_retries' => 5
            ]);

            $webhooks[] = $webhook;

            // Log configuração
            echo "Webhook configurado: {$webhook['name']} - {$webhook['url']}\n";
        }

        return $webhooks;
    }

    public function handleOrderLifecycle($order)
    {
        $events = [
            'order.created' => $this->formatOrderCreated($order),
            'order.paid' => $this->formatOrderPaid($order),
            'order.completed' => $this->formatOrderCompleted($order)
        ];

        foreach ($events as $eventType => $eventData) {
            if ($this->shouldFireEvent($eventType, $order)) {
                $result = $this->sdk->webhooks()->deliverEvent($eventType, $eventData, [
                    'priority' => $this->getEventPriority($eventType),
                    'idempotency_key' => $this->generateIdempotencyKey($order['id'], $eventType)
                ]);

                $this->logEventDelivery($eventType, $order['id'], $result);
            }
        }
    }

    public function setupRetryProcessing()
    {
        // Processar retries a cada 5 minutos
        while (true) {
            $results = $this->sdk->webhooks()->processRetries(100);

            if ($results['processed'] > 0) {
                echo "Retries processados: {$results['processed']}\n";
                echo "Sucessos: {$results['successful']}\n";
                echo "Falhas: {$results['failed']}\n";
            }

            sleep(300); // 5 minutos
        }
    }

    private function generateSecret($organizationId)
    {
        return hash('sha256', $organizationId . '_' . time() . '_' . random_bytes(16));
    }
}
```

### Sistema de Monitoramento de Webhooks

```php
// Sistema de monitoramento e alertas
class WebhookMonitoring
{
    private $sdk;
    private $alertThresholds;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
        $this->alertThresholds = [
            'failure_rate' => 0.1,      // 10%
            'response_time' => 5000,     // 5 segundos
            'queue_size' => 1000         // 1000 retries pendentes
        ];
    }

    public function performHealthCheck()
    {
        $isHealthy = $this->sdk->webhooks()->isHealthy();
        $stats = $this->sdk->webhooks()->getStats();

        $alerts = [];

        // Verificar taxa de falhas
        if ($stats['webhooks_failed'] > 0) {
            $failureRate = $stats['webhooks_failed'] / ($stats['webhooks_delivered'] + $stats['webhooks_failed']);
            if ($failureRate > $this->alertThresholds['failure_rate']) {
                $alerts[] = [
                    'type' => 'high_failure_rate',
                    'message' => "Taxa de falhas alta: " . ($failureRate * 100) . "%",
                    'severity' => 'warning'
                ];
            }
        }

        // Verificar tempo de resposta
        if ($stats['avg_response_time'] > $this->alertThresholds['response_time']) {
            $alerts[] = [
                'type' => 'slow_response',
                'message' => "Tempo médio de resposta alto: {$stats['avg_response_time']}ms",
                'severity' => 'warning'
            ];
        }

        // Verificar fila de retry
        if ($stats['retry_queue_size'] > $this->alertThresholds['queue_size']) {
            $alerts[] = [
                'type' => 'large_retry_queue',
                'message' => "Fila de retry grande: {$stats['retry_queue_size']} itens",
                'severity' => 'critical'
            ];
        }

        return [
            'healthy' => $isHealthy && empty($alerts),
            'stats' => $stats,
            'alerts' => $alerts,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function generateDailyReport()
    {
        $stats = $this->sdk->webhooks()->getStats();
        $webhooks = $this->sdk->webhooks()->webhooks()->list(['active' => true]);

        $report = [
            'date' => date('Y-m-d'),
            'summary' => [
                'total_webhooks' => $stats['total_webhooks'],
                'active_webhooks' => $stats['active_webhooks'],
                'total_deliveries' => $stats['webhooks_delivered'] + $stats['webhooks_failed'],
                'success_rate' => $this->calculateSuccessRate($stats),
                'avg_response_time' => $stats['avg_response_time']
            ],
            'webhook_details' => []
        ];

        foreach ($webhooks as $webhook) {
            $webhookStats = $this->getWebhookStats($webhook['id']);
            $report['webhook_details'][] = [
                'id' => $webhook['id'],
                'name' => $webhook['name'],
                'url' => $webhook['url'],
                'events' => $webhook['events'],
                'deliveries_24h' => $webhookStats['deliveries_24h'],
                'success_rate_24h' => $webhookStats['success_rate_24h'],
                'avg_response_time_24h' => $webhookStats['avg_response_time_24h']
            ];
        }

        return $report;
    }

    private function calculateSuccessRate($stats)
    {
        $total = $stats['webhooks_delivered'] + $stats['webhooks_failed'];
        return $total > 0 ? ($stats['webhooks_delivered'] / $total) * 100 : 100;
    }
}
```

### Sistema de Debugging e Testes

```php
// Ferramentas de debugging e testes
class WebhookDebugger
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function testAllWebhooks()
    {
        $webhooks = $this->sdk->webhooks()->webhooks()->list(['active' => true]);
        $results = [];

        foreach ($webhooks as $webhook) {
            $testResult = $this->sdk->webhooks()->testing()->testWebhook($webhook['id']);

            $results[] = [
                'webhook_id' => $webhook['id'],
                'name' => $webhook['name'],
                'url' => $webhook['url'],
                'test_result' => $testResult
            ];

            echo "Testando {$webhook['name']}: ";
            echo $testResult['success'] ? "✅ OK" : "❌ FALHA";
            echo " ({$testResult['response_time']}ms)\n";

            if (!$testResult['success']) {
                echo "  Erro: {$testResult['error']}\n";
            }
        }

        return $results;
    }

    public function sendTestEvents($webhookId)
    {
        $testEvents = [
            'order.created' => [
                'id' => 'test_order_' . uniqid(),
                'amount' => 9900,
                'status' => 'created',
                'test_mode' => true
            ],
            'payment.approved' => [
                'id' => 'test_payment_' . uniqid(),
                'amount' => 9900,
                'status' => 'approved',
                'test_mode' => true
            ],
            'customer.created' => [
                'id' => 'test_customer_' . uniqid(),
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'test_mode' => true
            ]
        ];

        $results = [];

        foreach ($testEvents as $eventType => $eventData) {
            $result = $this->sdk->webhooks()->testing()->sendTestEvent(
                $webhookId,
                $eventType,
                $eventData
            );

            $results[$eventType] = $result;

            echo "Evento {$eventType}: ";
            echo $result['success'] ? "✅ Entregue" : "❌ Falhou";
            echo " ({$result['response_time']}ms)\n";
        }

        return $results;
    }

    public function debugFailedDelivery($deliveryId)
    {
        $debug = $this->sdk->webhooks()->testing()->debugDelivery($deliveryId);

        echo "=== Debug da Entrega {$deliveryId} ===\n";
        echo "URL: {$debug['webhook_url']}\n";
        echo "Evento: {$debug['event_type']}\n";
        echo "Status: {$debug['status_code']}\n";
        echo "Tempo de resposta: {$debug['response_time']}ms\n";
        echo "Tentativas: {$debug['attempt_number']}/{$debug['max_retries']}\n";
        echo "\nHeaders enviados:\n";
        foreach ($debug['request_headers'] as $header => $value) {
            echo "  {$header}: {$value}\n";
        }
        echo "\nPayload enviado:\n";
        echo json_encode($debug['request_payload'], JSON_PRETTY_PRINT);
        echo "\nResposta recebida:\n";
        echo $debug['response_body'];
        echo "\nErro (se houver): {$debug['error']}\n";

        return $debug;
    }
}
```

## 🔧 DTOs e Validação

### WebhookData DTO

```php
use ClubifyCheckout\Modules\Webhooks\DTOs\WebhookData;

$webhookData = new WebhookData([
    'url' => 'https://api.exemplo.com/webhook',
    'events' => ['order.created', 'payment.approved'],
    'secret' => 'webhook_secret_123',
    'name' => 'Webhook Principal',
    'active' => true,
    'headers' => [
        'Authorization' => 'Bearer token_123',
        'Content-Type' => 'application/json'
    ],
    'timeout_seconds' => 30,
    'retry_enabled' => true,
    'max_retries' => 5,
    'filters' => [
        'order.created' => [
            'amount' => ['min' => 1000]
        ]
    ]
]);

// Validação automática inclui:
// - Formato de URL válida
// - Eventos válidos
// - Headers válidos
// - Timeouts razoáveis
// - Filtros bem formados
if ($webhookData->isValid()) {
    $webhook = $sdk->webhooks()->setupWebhook($webhookData->toArray());
}
```

### EventData DTO

```php
use ClubifyCheckout\Modules\Webhooks\DTOs\EventData;

$eventData = new EventData([
    'type' => 'order.created',
    'id' => 'evt_' . uniqid(),
    'data' => [
        'order' => [
            'id' => 'order_123',
            'amount' => 9900,
            'status' => 'paid'
        ]
    ],
    'metadata' => [
        'source' => 'api',
        'version' => '1.0'
    ],
    'timestamp' => date('c')
]);
```

## 📊 Relatórios e Analytics

### Estatísticas Detalhadas

```php
// Relatório completo de webhooks
$stats = $sdk->webhooks()->getStats();

echo "=== Estatísticas de Webhooks ===\n";
echo "Total de webhooks: {$stats['total_webhooks']}\n";
echo "Webhooks ativos: {$stats['active_webhooks']}\n";
echo "Entregas realizadas: {$stats['webhooks_delivered']}\n";
echo "Entregas falharam: {$stats['webhooks_failed']}\n";
echo "Retries agendados: {$stats['webhooks_retried']}\n";
echo "Fila de retry: {$stats['retry_queue_size']}\n";
echo "Tempo médio de resposta: " . round($stats['avg_response_time'], 2) . "ms\n";

$successRate = ($stats['webhooks_delivered'] / ($stats['webhooks_delivered'] + $stats['webhooks_failed'])) * 100;
echo "Taxa de sucesso: " . round($successRate, 2) . "%\n";
```

### Análise de Performance

```php
// Análise de performance por webhook
class WebhookPerformanceAnalyzer
{
    private $sdk;

    public function generatePerformanceReport($days = 7)
    {
        $webhooks = $this->sdk->webhooks()->webhooks()->list(['active' => true]);
        $report = [
            'period' => "{$days} days",
            'generated_at' => date('c'),
            'webhooks' => []
        ];

        foreach ($webhooks as $webhook) {
            $stats = $this->getWebhookPerformanceStats($webhook['id'], $days);

            $report['webhooks'][] = [
                'id' => $webhook['id'],
                'name' => $webhook['name'],
                'url' => $webhook['url'],
                'total_deliveries' => $stats['total_deliveries'],
                'successful_deliveries' => $stats['successful_deliveries'],
                'failed_deliveries' => $stats['failed_deliveries'],
                'success_rate' => $stats['success_rate'],
                'avg_response_time' => $stats['avg_response_time'],
                'p95_response_time' => $stats['p95_response_time'],
                'retry_rate' => $stats['retry_rate'],
                'most_common_errors' => $stats['most_common_errors']
            ];
        }

        return $report;
    }
}
```

## 🔍 Monitoramento e Logs

### Health Check

```php
// Verificar saúde do sistema de webhooks
$isHealthy = $sdk->webhooks()->isHealthy();

if ($isHealthy) {
    echo "✅ Sistema de webhooks saudável\n";
} else {
    echo "❌ Sistema de webhooks com problemas\n";

    // Investigar problemas
    $stats = $sdk->webhooks()->getStats();

    if ($stats['retry_queue_size'] > 1000) {
        echo "⚠️ Fila de retry muito grande: {$stats['retry_queue_size']}\n";
    }

    if ($stats['failed_deliveries_24h'] > 100) {
        echo "⚠️ Muitas falhas nas últimas 24h: {$stats['failed_deliveries_24h']}\n";
    }
}
```

### Logs Estruturados

```php
// Os logs são gerados automaticamente para todas as operações:

/*
[2025-01-16 10:30:00] INFO: Webhook configurado com sucesso
{
    "webhook_id": "webhook_123456",
    "url": "https://api.exemplo.com/webhook",
    "events": ["order.created", "payment.approved"],
    "test_result": {"success": true, "response_time": 125}
}

[2025-01-16 10:35:00] INFO: Evento entregue para webhooks
{
    "event_type": "order.created",
    "webhooks_count": 2,
    "deliveries_count": 2,
    "success_count": 1,
    "failed_count": 1
}

[2025-01-16 10:40:00] WARNING: Webhook delivery failed, retry scheduled
{
    "webhook_id": "webhook_789",
    "delivery_id": "delivery_101",
    "error": "Connection timeout",
    "retry_attempt": 1,
    "next_retry_at": "2025-01-16T10:45:00Z"
}
*/
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Webhooks\Exceptions\WebhookException;
use ClubifyCheckout\Modules\Webhooks\Exceptions\WebhookDeliveryException;
use ClubifyCheckout\Modules\Webhooks\Exceptions\WebhookConfigException;

try {
    $webhook = $sdk->webhooks()->setupWebhook($webhookData);
    $result = $sdk->webhooks()->deliverEvent('order.created', $eventData);
} catch (WebhookConfigException $e) {
    echo "Erro na configuração: " . $e->getMessage();
    // Verificar configuração do webhook
} catch (WebhookDeliveryException $e) {
    echo "Erro na entrega: " . $e->getMessage();
    // Verificar conectividade do endpoint
} catch (WebhookException $e) {
    echo "Erro no webhook: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Webhooks
CLUBIFY_WEBHOOKS_DEFAULT_TIMEOUT=30
CLUBIFY_WEBHOOKS_MAX_RETRIES=5
CLUBIFY_WEBHOOKS_RETRY_DELAY=300
CLUBIFY_WEBHOOKS_RATE_LIMIT_ENABLED=true
CLUBIFY_WEBHOOKS_MAX_REQUESTS_PER_MINUTE=60
CLUBIFY_WEBHOOKS_VALIDATE_SSL=true
```

### Configuração Avançada

```php
$config = [
    'webhooks' => [
        'delivery' => [
            'timeout' => 30,
            'retry_enabled' => true,
            'max_retries' => 5,
            'retry_delay' => 300,
            'signature_header' => 'X-Webhook-Signature',
            'user_agent' => 'ClubifyCheckout-Webhook/1.0'
        ],
        'security' => [
            'validate_ssl' => true,
            'allowed_domains' => [],
            'blocked_domains' => ['localhost', '127.0.0.1'],
            'require_https' => true
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
            'burst_limit' => 10
        ],
        'monitoring' => [
            'log_all_deliveries' => true,
            'alert_on_failure_rate' => 0.1,
            'health_check_interval' => 300
        ],
        'retry' => [
            'backoff_strategy' => 'exponential', // linear/exponential
            'base_delay' => 300,
            'max_delay' => 3600,
            'jitter' => true
        ],
        'cleanup' => [
            'auto_cleanup' => true,
            'retention_days' => 30,
            'cleanup_interval' => 86400
        ]
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

### Configuração de Eventos

```php
// Configurar eventos disponíveis
$sdk->webhooks()->config()->updateGlobalConfig([
    'available_events' => [
        'order.created',
        'order.updated',
        'order.completed',
        'order.cancelled',
        'payment.approved',
        'payment.failed',
        'payment.refunded',
        'customer.created',
        'customer.updated',
        'subscription.created',
        'subscription.updated',
        'subscription.cancelled',
        'webhook.test'
    ],
    'event_formats' => [
        'json' => 'application/json',
        'xml' => 'application/xml',
        'form' => 'application/x-www-form-urlencoded'
    ]
]);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de confiabilidade e observabilidade enterprise.**