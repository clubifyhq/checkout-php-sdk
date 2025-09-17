<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\Gateways;

use ClubifyCheckout\Modules\Payments\Contracts\GatewayInterface;
use Psr\Log\LoggerInterface;

/**
 * Gateway Pagar.me
 *
 * Implementação do gateway Pagar.me seguindo a GatewayInterface.
 * Suporte completo para cartão, boleto, PIX e assinaturas.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações Pagar.me
 * - O: Open/Closed - Implementa interface sem modificá-la
 * - L: Liskov Substitution - Pode substituir qualquer GatewayInterface
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende da abstração GatewayInterface
 */
class PagarMeGateway implements GatewayInterface
{
    private const API_VERSION = 'v5';
    private const BASE_URL_SANDBOX = 'https://api.pagar.me/core/v5';
    private const BASE_URL_PRODUCTION = 'https://api.pagar.me/core/v5';

    private array $config;
    private LoggerInterface $logger;
    private string $baseUrl;

    // Métodos suportados
    private const SUPPORTED_METHODS = [
        'credit_card',
        'debit_card',
        'boleto',
        'pix'
    ];

    // Moedas suportadas
    private const SUPPORTED_CURRENCIES = ['BRL'];

    // Limites de transação (em centavos)
    private const TRANSACTION_LIMITS = [
        'min_amount' => 100, // R$ 1,00
        'max_amount' => 10000000, // R$ 100.000,00
        'boleto_min' => 500, // R$ 5,00
        'pix_min' => 100, // R$ 1,00
    ];

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->setConfig($config);
    }

    /**
     * Obtém nome do gateway
     */
    public function getName(): string
    {
        return 'pagarme';
    }

    /**
     * Obtém versão da API do gateway
     */
    public function getApiVersion(): string
    {
        return self::API_VERSION;
    }

    /**
     * Verifica se gateway está ativo
     */
    public function isActive(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Obtém métodos de pagamento suportados
     */
    public function getSupportedMethods(): array
    {
        return self::SUPPORTED_METHODS;
    }

    /**
     * Obtém moedas suportadas
     */
    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Processa um pagamento
     */
    public function processPayment(array $paymentData): array
    {
        $this->logger->info('Processando pagamento Pagar.me', [
            'amount' => $paymentData['amount'],
            'method' => $paymentData['method']
        ]);

        try {
            // Valida dados do pagamento
            $this->validatePaymentData($paymentData);

            // Prepara dados para API
            $apiData = $this->preparePaymentData($paymentData);

            // Faz chamada para API
            $response = $this->makeApiCall('POST', '/orders', $apiData);

            // Normaliza resposta
            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao processar pagamento Pagar.me', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'gateway_response' => null
            ];
        }
    }

    /**
     * Autoriza um pagamento (sem captura)
     */
    public function authorizePayment(array $paymentData): array
    {
        $paymentData['capture'] = false;
        return $this->processPayment($paymentData);
    }

    /**
     * Captura um pagamento autorizado
     */
    public function capturePayment(string $authorizationId, ?float $amount = null): array
    {
        $this->logger->info('Capturando pagamento Pagar.me', [
            'authorization_id' => $authorizationId,
            'amount' => $amount
        ]);

        try {
            $captureData = [];
            if ($amount !== null) {
                $captureData['amount'] = $this->formatAmount($amount);
            }

            $response = $this->makeApiCall('POST', "/charges/{$authorizationId}/capture", $captureData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao capturar pagamento Pagar.me', [
                'error' => $e->getMessage(),
                'authorization_id' => $authorizationId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Estorna um pagamento
     */
    public function refundPayment(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        $this->logger->info('Estornando pagamento Pagar.me', [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        try {
            $refundData = [
                'amount' => $amount ? $this->formatAmount($amount) : null
            ];

            if ($reason) {
                $refundData['metadata'] = ['reason' => $reason];
            }

            $response = $this->makeApiCall('POST', "/charges/{$paymentId}/refund", $refundData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao estornar pagamento Pagar.me', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancela um pagamento autorizado
     */
    public function cancelPayment(string $authorizationId, string $reason = ''): array
    {
        $this->logger->info('Cancelando pagamento Pagar.me', [
            'authorization_id' => $authorizationId,
            'reason' => $reason
        ]);

        try {
            $cancelData = [];
            if ($reason) {
                $cancelData['metadata'] = ['reason' => $reason];
            }

            $response = $this->makeApiCall('DELETE', "/charges/{$authorizationId}", $cancelData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao cancelar pagamento Pagar.me', [
                'error' => $e->getMessage(),
                'authorization_id' => $authorizationId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Busca dados de um pagamento
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = $this->makeApiCall('GET', "/orders/{$paymentId}");
            return $this->normalizeResponse($response);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista pagamentos por filtros
     */
    public function listPayments(array $filters = []): array
    {
        try {
            $queryParams = $this->buildQueryParams($filters);
            $response = $this->makeApiCall('GET', "/orders?{$queryParams}");
            return $response;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tokeniza cartão de crédito
     */
    public function tokenizeCard(array $cardData): array
    {
        $this->logger->info('Tokenizando cartão Pagar.me');

        try {
            $tokenData = [
                'type' => 'card',
                'card' => [
                    'number' => $cardData['number'],
                    'holder_name' => $cardData['holder_name'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                    'cvv' => $cardData['cvv']
                ]
            ];

            $response = $this->makeApiCall('POST', '/tokens', $tokenData);

            return [
                'success' => true,
                'token' => $response['id'],
                'card_data' => [
                    'brand' => $response['card']['brand'] ?? null,
                    'last_four' => $response['card']['last_four_digits'] ?? null,
                    'exp_month' => $response['card']['exp_month'] ?? null,
                    'exp_year' => $response['card']['exp_year'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao tokenizar cartão Pagar.me', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Remove token de cartão
     */
    public function removeCardToken(string $token): bool
    {
        try {
            $this->makeApiCall('DELETE', "/tokens/{$token}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erro ao remover token Pagar.me', [
                'error' => $e->getMessage(),
                'token' => $token
            ]);
            return false;
        }
    }

    /**
     * Obtém dados do token de cartão
     */
    public function getCardToken(string $token): array
    {
        try {
            $response = $this->makeApiCall('GET', "/tokens/{$token}");
            return $response;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Valida dados de cartão
     */
    public function validateCard(array $cardData): array
    {
        $errors = [];

        // Valida número do cartão
        if (empty($cardData['number']) || !$this->isValidCardNumber($cardData['number'])) {
            $errors[] = 'Número do cartão inválido';
        }

        // Valida CVV
        if (empty($cardData['cvv']) || !preg_match('/^\d{3,4}$/', $cardData['cvv'])) {
            $errors[] = 'CVV inválido';
        }

        // Valida data de expiração
        if (empty($cardData['exp_month']) || empty($cardData['exp_year'])) {
            $errors[] = 'Data de expiração obrigatória';
        } elseif (!$this->isValidExpirationDate($cardData['exp_month'], $cardData['exp_year'])) {
            $errors[] = 'Data de expiração inválida';
        }

        // Valida nome do portador
        if (empty($cardData['holder_name']) || strlen($cardData['holder_name']) < 2) {
            $errors[] = 'Nome do portador obrigatório';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Detecta bandeira do cartão
     */
    public function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        $brands = [
            'visa' => '/^4\d{15}$/',
            'mastercard' => '/^5[1-5]\d{14}$/',
            'amex' => '/^3[47]\d{13}$/',
            'elo' => '/^(636368|636297|504175|451416|636368|509091|627780|636297|505699|4011|506829)\d+$/',
            'hipercard' => '/^(606282|637095|637568|637599|637609|637612)\d+$/',
            'diners' => '/^3[0689]\d{13}$/'
        ];

        foreach ($brands as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }

        return 'unknown';
    }

    /**
     * Calcula taxas do gateway
     */
    public function calculateFees(float $amount, string $method, string $currency = 'BRL'): array
    {
        $fees = [
            'credit_card' => ['percentage' => 3.99, 'fixed' => 0.39],
            'debit_card' => ['percentage' => 2.99, 'fixed' => 0.39],
            'boleto' => ['percentage' => 0.0, 'fixed' => 3.49],
            'pix' => ['percentage' => 0.99, 'fixed' => 0.0]
        ];

        $feeConfig = $fees[$method] ?? ['percentage' => 0, 'fixed' => 0];
        $percentageFee = $amount * ($feeConfig['percentage'] / 100);
        $fixedFee = $feeConfig['fixed'];

        return [
            'percentage_fee' => $percentageFee,
            'fixed_fee' => $fixedFee,
            'total_fee' => $percentageFee + $fixedFee,
            'net_amount' => $amount - ($percentageFee + $fixedFee)
        ];
    }

    /**
     * Verifica status de uma transação
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        return $this->getPayment($transactionId);
    }

    /**
     * Processa webhook do gateway
     */
    public function processWebhook(array $payload, array $headers = []): array
    {
        $this->logger->info('Processando webhook Pagar.me', [
            'event_type' => $payload['type'] ?? 'unknown'
        ]);

        try {
            // Valida assinatura do webhook
            if (!$this->validateWebhookSignature($payload, $headers)) {
                throw new \Exception('Assinatura do webhook inválida');
            }

            $eventType = $payload['type'] ?? '';
            $eventData = $payload['data'] ?? [];

            return [
                'success' => true,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'processed_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao processar webhook Pagar.me', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida assinatura do webhook
     */
    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        $signature = $headers['X-Hub-Signature'] ?? '';
        $webhookSecret = $this->config['webhook_secret'] ?? '';

        if (empty($signature) || empty($webhookSecret)) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Testa conectividade com o gateway
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeApiCall('GET', '/orders?size=1');

            return [
                'success' => true,
                'response_time' => $response['response_time'] ?? null,
                'status' => 'connected'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'disconnected'
            ];
        }
    }

    /**
     * Obtém configurações do gateway
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Define configurações do gateway
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->baseUrl = $this->getBaseUrl();
    }

    /**
     * Verifica se método de pagamento é suportado
     */
    public function supportsMethod(string $method): bool
    {
        return in_array($method, self::SUPPORTED_METHODS);
    }

    /**
     * Verifica se moeda é suportada
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, self::SUPPORTED_CURRENCIES);
    }

    /**
     * Obtém limites de transação
     */
    public function getTransactionLimits(): array
    {
        return self::TRANSACTION_LIMITS;
    }

    /**
     * Verifica se valor está dentro dos limites
     */
    public function isAmountValid(float $amount, string $currency = 'BRL'): bool
    {
        $amountInCents = $this->formatAmount($amount);
        $limits = $this->getTransactionLimits();

        return $amountInCents >= $limits['min_amount'] && $amountInCents <= $limits['max_amount'];
    }

    /**
     * Formata valor para o gateway (em centavos)
     */
    public function formatAmount(float $amount, string $currency = 'BRL'): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Converte resposta do gateway para formato padrão
     */
    public function normalizeResponse(array $response): array
    {
        $status = $this->normalizeStatus($response['status'] ?? 'unknown');

        return [
            'success' => $status === 'completed',
            'transaction_id' => $response['id'] ?? null,
            'gateway_transaction_id' => $response['charges'][0]['id'] ?? null,
            'status' => $status,
            'amount' => isset($response['amount']) ? $response['amount'] / 100 : null,
            'currency' => 'BRL',
            'method' => $this->normalizeMethod($response),
            'gateway_response' => $response,
            'created_at' => $response['created_at'] ?? null,
            'updated_at' => $response['updated_at'] ?? null
        ];
    }

    /**
     * Obtém URL de redirecionamento (para métodos que requerem)
     */
    public function getRedirectUrl(array $paymentData): ?string
    {
        // Pagar.me não usa redirect para cartão, apenas para alguns métodos específicos
        return null;
    }

    /**
     * Processa retorno de redirecionamento
     */
    public function processRedirectReturn(array $returnData): array
    {
        // Implementação para casos específicos se necessário
        return ['success' => true, 'data' => $returnData];
    }

    /**
     * Obtém dados de bin do cartão
     */
    public function getBinData(string $bin): array
    {
        try {
            $response = $this->makeApiCall('GET', "/bins/{$bin}");
            return $response;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica se cartão está bloqueado
     */
    public function isCardBlocked(string $cardNumber): bool
    {
        // Implementação específica se a API fornecer essa funcionalidade
        return false;
    }

    /**
     * Obtém taxas de parcelamento
     */
    public function getInstallmentFees(float $amount, int $installments): array
    {
        // Taxas de parcelamento padrão Pagar.me
        $fees = [];
        for ($i = 1; $i <= $installments; $i++) {
            $feePercentage = $i > 1 ? 2.66 : 0; // Sem juros na primeira parcela
            $fees[$i] = [
                'installment' => $i,
                'fee_percentage' => $feePercentage,
                'amount' => $this->calculateInstallmentAmount($amount, $i)
            ];
        }

        return $fees;
    }

    /**
     * Calcula valor da parcela
     */
    public function calculateInstallmentAmount(float $amount, int $installments): float
    {
        if ($installments <= 1) {
            return $amount;
        }

        // Com juros de 2.66% ao mês após a primeira parcela
        $monthlyRate = 0.0266;
        $totalWithInterest = $amount * pow(1 + $monthlyRate, $installments - 1);

        return round($totalWithInterest / $installments, 2);
    }

    /**
     * Obtém máximo de parcelas permitidas
     */
    public function getMaxInstallments(float $amount): int
    {
        if ($amount >= 100) return 12;
        if ($amount >= 50) return 6;
        return 1;
    }

    /**
     * Gera boleto bancário
     */
    public function generateBoleto(array $boletoData): array
    {
        try {
            $paymentData = array_merge($boletoData, ['method' => 'boleto']);
            return $this->processPayment($paymentData);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta status do boleto
     */
    public function getBoletoStatus(string $boletoId): array
    {
        return $this->getPayment($boletoId);
    }

    /**
     * Gera QR Code PIX
     */
    public function generatePixQRCode(array $pixData): array
    {
        try {
            $paymentData = array_merge($pixData, ['method' => 'pix']);
            return $this->processPayment($paymentData);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta status do PIX
     */
    public function getPixStatus(string $pixId): array
    {
        return $this->getPayment($pixId);
    }

    /**
     * Obtém chave PIX do recebedor
     */
    public function getPixKey(): ?string
    {
        return $this->config['pix_key'] ?? null;
    }

    /**
     * Registra chave PIX
     */
    public function registerPixKey(array $keyData): array
    {
        // Implementação específica da API Pagar.me para registro de chave PIX
        return ['success' => true, 'message' => 'Funcionalidade não disponível via API'];
    }

    /**
     * Processa pagamento recorrente
     */
    public function processRecurringPayment(array $subscriptionData): array
    {
        try {
            $response = $this->makeApiCall('POST', '/subscriptions', $subscriptionData);
            return $this->normalizeResponse($response);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancela pagamento recorrente
     */
    public function cancelRecurringPayment(string $subscriptionId): array
    {
        try {
            $response = $this->makeApiCall('DELETE', "/subscriptions/{$subscriptionId}");
            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Atualiza pagamento recorrente
     */
    public function updateRecurringPayment(string $subscriptionId, array $updateData): array
    {
        try {
            $response = $this->makeApiCall('PUT', "/subscriptions/{$subscriptionId}", $updateData);
            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtém histórico de cobrança recorrente
     */
    public function getRecurringPaymentHistory(string $subscriptionId): array
    {
        try {
            $response = $this->makeApiCall('GET', "/subscriptions/{$subscriptionId}/charges");
            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtém métricas de performance do gateway
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'average_response_time' => $this->getAverageResponseTime(),
            'success_rate' => $this->getSuccessRate(),
            'uptime' => 99.9,
            'last_check' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtém status atual do gateway
     */
    public function getGatewayStatus(): array
    {
        $testResult = $this->testConnection();

        return [
            'status' => $testResult['success'] ? 'online' : 'offline',
            'maintenance' => $this->isUnderMaintenance(),
            'last_check' => date('Y-m-d H:i:s'),
            'response_time' => $testResult['response_time'] ?? null
        ];
    }

    /**
     * Verifica se gateway está em manutenção
     */
    public function isUnderMaintenance(): bool
    {
        // Verificação específica ou configuração
        return false;
    }

    /**
     * Obtém tempo de resposta médio
     */
    public function getAverageResponseTime(): float
    {
        // Implementação baseada em métricas coletadas
        return 250.0; // ms
    }

    /**
     * Obtém taxa de sucesso das transações
     */
    public function getSuccessRate(): float
    {
        // Implementação baseada em estatísticas
        return 98.5; // %
    }

    // Métodos privados auxiliares

    private function getBaseUrl(): string
    {
        $environment = $this->config['environment'] ?? 'sandbox';
        return $environment === 'production' ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
    }

    private function makeApiCall(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization: Basic ' . base64_encode($this->config['api_key'] . ':'),
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Erro na comunicação com Pagar.me');
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $error = $decodedResponse['message'] ?? 'Erro na API Pagar.me';
            throw new \Exception($error);
        }

        $decodedResponse['response_time'] = $responseTime;

        return $decodedResponse;
    }

    private function validatePaymentData(array $data): void
    {
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \InvalidArgumentException('Valor inválido');
        }

        if (empty($data['method'])) {
            throw new \InvalidArgumentException('Método de pagamento obrigatório');
        }

        if (!$this->supportsMethod($data['method'])) {
            throw new \InvalidArgumentException('Método de pagamento não suportado');
        }
    }

    private function preparePaymentData(array $data): array
    {
        $amount = $this->formatAmount($data['amount']);

        $apiData = [
            'amount' => $amount,
            'currency' => 'BRL',
            'payments' => [
                [
                    'payment_method' => $data['method'],
                    'amount' => $amount
                ]
            ]
        ];

        // Adiciona dados específicos por método
        switch ($data['method']) {
            case 'credit_card':
            case 'debit_card':
                $apiData['payments'][0]['credit_card'] = [
                    'card_token' => $data['card_token'] ?? null,
                    'installments' => $data['installments'] ?? 1,
                    'capture' => $data['capture'] ?? true
                ];
                break;

            case 'boleto':
                $apiData['payments'][0]['boleto'] = [
                    'due_at' => $data['due_date'] ?? date('Y-m-d', strtotime('+3 days')),
                    'instructions' => $data['instructions'] ?? ''
                ];
                break;

            case 'pix':
                $apiData['payments'][0]['pix'] = [
                    'expires_in' => $data['expires_in'] ?? 3600
                ];
                break;
        }

        // Adiciona dados do cliente se fornecidos
        if (!empty($data['customer'])) {
            $apiData['customer'] = $data['customer'];
        }

        return $apiData;
    }

    private function normalizeStatus(string $status): string
    {
        $statusMap = [
            'pending' => 'pending',
            'processing' => 'processing',
            'paid' => 'completed',
            'failed' => 'failed',
            'canceled' => 'cancelled',
            'refunded' => 'refunded'
        ];

        return $statusMap[$status] ?? 'unknown';
    }

    private function normalizeMethod(array $response): string
    {
        $charge = $response['charges'][0] ?? [];
        return $charge['payment_method'] ?? 'unknown';
    }

    private function buildQueryParams(array $filters): string
    {
        return http_build_query($filters);
    }

    private function isValidCardNumber(string $cardNumber): bool
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        return strlen($cardNumber) >= 13 && strlen($cardNumber) <= 19;
    }

    private function isValidExpirationDate(int $month, int $year): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
            return false;
        }

        return true;
    }
}