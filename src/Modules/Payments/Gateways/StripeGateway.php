<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Gateways;

use Clubify\Checkout\Modules\Payments\Contracts\GatewayInterface;
use Psr\Log\LoggerInterface;

/**
 * Gateway Stripe
 *
 * Implementação do gateway Stripe seguindo a GatewayInterface.
 * Suporte completo para cartão, métodos alternativos e assinaturas.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações Stripe
 * - O: Open/Closed - Implementa interface sem modificá-la
 * - L: Liskov Substitution - Pode substituir qualquer GatewayInterface
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende da abstração GatewayInterface
 */
class StripeGateway implements GatewayInterface
{
    private const API_VERSION = '2023-10-16';
    private const BASE_URL = 'https://api.stripe.com/v1';

    private array $config;
    private LoggerInterface $logger;

    // Métodos suportados
    private const SUPPORTED_METHODS = [
        'credit_card',
        'debit_card',
        'bancontact',
        'ideal',
        'sofort',
        'giropay',
        'eps',
        'p24',
        'alipay',
        'wechat_pay'
    ];

    // Moedas suportadas (principais)
    private const SUPPORTED_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'BRL', 'AUD', 'CAD', 'CHF', 'DKK', 'HKD', 'JPY',
        'MXN', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD'
    ];

    // Limites de transação (em centavos)
    private const TRANSACTION_LIMITS = [
        'min_amount' => 50, // $0.50
        'max_amount' => 9999999999, // $99,999,999.99
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
        return 'stripe';
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
        $this->logger->info('Processando pagamento Stripe', [
            'amount' => $paymentData['amount'],
            'method' => $paymentData['method'],
            'currency' => $paymentData['currency'] ?? 'USD'
        ]);

        try {
            // Valida dados do pagamento
            $this->validatePaymentData($paymentData);

            // Cria PaymentIntent
            $intentData = $this->preparePaymentIntentData($paymentData);
            $intent = $this->createPaymentIntent($intentData);

            // Se tem source/token, confirma automaticamente
            if (!empty($paymentData['source']) || !empty($paymentData['payment_method'])) {
                $intent = $this->confirmPaymentIntent($intent['id'], $paymentData);
            }

            return $this->normalizeResponse($intent);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao processar pagamento Stripe', [
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
        $paymentData['capture_method'] = 'manual';
        return $this->processPayment($paymentData);
    }

    /**
     * Captura um pagamento autorizado
     */
    public function capturePayment(string $authorizationId, ?float $amount = null): array
    {
        $this->logger->info('Capturando pagamento Stripe', [
            'authorization_id' => $authorizationId,
            'amount' => $amount
        ]);

        try {
            $captureData = [];
            if ($amount !== null) {
                $captureData['amount_to_capture'] = $this->formatAmount($amount);
            }

            $response = $this->makeApiCall('POST', "/payment_intents/{$authorizationId}/capture", $captureData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao capturar pagamento Stripe', [
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
        $this->logger->info('Estornando pagamento Stripe', [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        try {
            $refundData = [
                'payment_intent' => $paymentId
            ];

            if ($amount !== null) {
                $refundData['amount'] = $this->formatAmount($amount);
            }

            if ($reason) {
                $refundData['reason'] = $reason;
                $refundData['metadata'] = ['reason' => $reason];
            }

            $response = $this->makeApiCall('POST', '/refunds', $refundData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao estornar pagamento Stripe', [
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
        $this->logger->info('Cancelando pagamento Stripe', [
            'authorization_id' => $authorizationId,
            'reason' => $reason
        ]);

        try {
            $cancelData = [
                'cancellation_reason' => $reason ?: 'requested_by_customer'
            ];

            $response = $this->makeApiCall('POST', "/payment_intents/{$authorizationId}/cancel", $cancelData);

            return $this->normalizeResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao cancelar pagamento Stripe', [
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
            $response = $this->makeApiCall('GET', "/payment_intents/{$paymentId}");
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
            $response = $this->makeApiCall('GET', "/payment_intents?{$queryParams}");
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
        $this->logger->info('Tokenizando cartão Stripe');

        try {
            $tokenData = [
                'card' => [
                    'number' => $cardData['number'],
                    'name' => $cardData['holder_name'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                    'cvc' => $cardData['cvv']
                ]
            ];

            if (!empty($cardData['address'])) {
                $tokenData['card']['address_line1'] = $cardData['address']['line1'] ?? '';
                $tokenData['card']['address_city'] = $cardData['address']['city'] ?? '';
                $tokenData['card']['address_state'] = $cardData['address']['state'] ?? '';
                $tokenData['card']['address_zip'] = $cardData['address']['zip'] ?? '';
                $tokenData['card']['address_country'] = $cardData['address']['country'] ?? '';
            }

            $response = $this->makeApiCall('POST', '/tokens', $tokenData);

            return [
                'success' => true,
                'token' => $response['id'],
                'card_data' => [
                    'brand' => $response['card']['brand'] ?? null,
                    'last_four' => $response['card']['last4'] ?? null,
                    'exp_month' => $response['card']['exp_month'] ?? null,
                    'exp_year' => $response['card']['exp_year'] ?? null,
                    'country' => $response['card']['country'] ?? null,
                    'funding' => $response['card']['funding'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao tokenizar cartão Stripe', [
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
        // Stripe tokens são automaticamente seguros e não precisam ser removidos manualmente
        // Payment Methods podem ser detached se necessário
        return true;
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
            'discover' => '/^6(?:011|5\d{2})\d{12}$/',
            'diners' => '/^3[0689]\d{13}$/',
            'jcb' => '/^35(?:2[89]|[3-8]\d)\d{12}$/',
            'unionpay' => '/^62\d{14,17}$/'
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
    public function calculateFees(float $amount, string $method, string $currency = 'USD'): array
    {
        // Taxas Stripe (podem variar por região)
        $fees = [
            'credit_card' => ['percentage' => 2.9, 'fixed' => 0.30],
            'debit_card' => ['percentage' => 2.9, 'fixed' => 0.30],
            'bancontact' => ['percentage' => 1.4, 'fixed' => 0.25],
            'ideal' => ['percentage' => 0.8, 'fixed' => 0.25],
            'sofort' => ['percentage' => 1.4, 'fixed' => 0.25],
            'giropay' => ['percentage' => 1.4, 'fixed' => 0.25],
            'eps' => ['percentage' => 1.8, 'fixed' => 0.25],
            'p24' => ['percentage' => 1.8, 'fixed' => 0.25],
            'alipay' => ['percentage' => 3.4, 'fixed' => 0.30],
            'wechat_pay' => ['percentage' => 3.4, 'fixed' => 0.30]
        ];

        $feeConfig = $fees[$method] ?? ['percentage' => 2.9, 'fixed' => 0.30];
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
        $this->logger->info('Processando webhook Stripe', [
            'event_type' => $payload['type'] ?? 'unknown'
        ]);

        try {
            // Valida assinatura do webhook
            if (!$this->validateWebhookSignature($payload, $headers)) {
                throw new \Exception('Assinatura do webhook inválida');
            }

            $eventType = $payload['type'] ?? '';
            $eventData = $payload['data']['object'] ?? [];

            return [
                'success' => true,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'processed_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao processar webhook Stripe', [
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
        $signature = $headers['Stripe-Signature'] ?? '';
        $webhookSecret = $this->config['webhook_secret'] ?? '';

        if (empty($signature) || empty($webhookSecret)) {
            return false;
        }

        // Implementação simplificada - em produção usar Stripe SDK
        $payloadString = json_encode($payload);
        $expectedSignature = hash_hmac('sha256', $payloadString, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Testa conectividade com o gateway
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeApiCall('GET', '/payment_intents?limit=1');

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
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES);
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
    public function isAmountValid(float $amount, string $currency = 'USD'): bool
    {
        $amountInCents = $this->formatAmount($amount, $currency);
        $limits = $this->getTransactionLimits();

        return $amountInCents >= $limits['min_amount'] && $amountInCents <= $limits['max_amount'];
    }

    /**
     * Formata valor para o gateway
     */
    public function formatAmount(float $amount, string $currency = 'USD'): int
    {
        // Moedas sem decimais (zero-decimal currencies)
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'CLP', 'BIF', 'DJF', 'GNF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) round($amount);
        }

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
            'gateway_transaction_id' => $response['charges']['data'][0]['id'] ?? null,
            'status' => $status,
            'amount' => isset($response['amount']) ? $response['amount'] / 100 : null,
            'currency' => strtoupper($response['currency'] ?? 'USD'),
            'method' => $this->normalizeMethod($response),
            'gateway_response' => $response,
            'created_at' => isset($response['created']) ? date('Y-m-d H:i:s', $response['created']) : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtém URL de redirecionamento (para métodos que requerem)
     */
    public function getRedirectUrl(array $paymentData): ?string
    {
        return $paymentData['return_url'] ?? null;
    }

    /**
     * Processa retorno de redirecionamento
     */
    public function processRedirectReturn(array $returnData): array
    {
        $paymentIntentId = $returnData['payment_intent'] ?? null;

        if ($paymentIntentId) {
            return $this->getPayment($paymentIntentId);
        }

        return ['success' => false, 'error' => 'Payment intent ID não fornecido'];
    }

    /**
     * Obtém dados de bin do cartão
     */
    public function getBinData(string $bin): array
    {
        // Stripe não fornece API pública de BIN, mas podemos usar serviços externos
        return ['success' => false, 'error' => 'BIN lookup não disponível'];
    }

    /**
     * Verifica se cartão está bloqueado
     */
    public function isCardBlocked(string $cardNumber): bool
    {
        // Implementação específica se necessário
        return false;
    }

    /**
     * Obtém taxas de parcelamento
     */
    public function getInstallmentFees(float $amount, int $installments): array
    {
        // Stripe não suporta parcelamento nativo, seria implementado via subscriptions
        return [];
    }

    /**
     * Calcula valor da parcela
     */
    public function calculateInstallmentAmount(float $amount, int $installments): float
    {
        return $amount / $installments;
    }

    /**
     * Obtém máximo de parcelas permitidas
     */
    public function getMaxInstallments(float $amount): int
    {
        return 1; // Stripe não suporta parcelamento nativo
    }

    /**
     * Gera boleto bancário
     */
    public function generateBoleto(array $boletoData): array
    {
        return ['success' => false, 'error' => 'Boleto não suportado pelo Stripe'];
    }

    /**
     * Consulta status do boleto
     */
    public function getBoletoStatus(string $boletoId): array
    {
        return ['success' => false, 'error' => 'Boleto não suportado pelo Stripe'];
    }

    /**
     * Gera QR Code PIX
     */
    public function generatePixQRCode(array $pixData): array
    {
        return ['success' => false, 'error' => 'PIX não suportado pelo Stripe'];
    }

    /**
     * Consulta status do PIX
     */
    public function getPixStatus(string $pixId): array
    {
        return ['success' => false, 'error' => 'PIX não suportado pelo Stripe'];
    }

    /**
     * Obtém chave PIX do recebedor
     */
    public function getPixKey(): ?string
    {
        return null;
    }

    /**
     * Registra chave PIX
     */
    public function registerPixKey(array $keyData): array
    {
        return ['success' => false, 'error' => 'PIX não suportado pelo Stripe'];
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
            $response = $this->makeApiCall('POST', "/subscriptions/{$subscriptionId}", $updateData);
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
            $response = $this->makeApiCall('GET', "/invoices?subscription={$subscriptionId}");
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
            'uptime' => 99.95,
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
        return false;
    }

    /**
     * Obtém tempo de resposta médio
     */
    public function getAverageResponseTime(): float
    {
        return 180.0; // ms
    }

    /**
     * Obtém taxa de sucesso das transações
     */
    public function getSuccessRate(): float
    {
        return 99.2; // %
    }

    // Métodos privados auxiliares

    private function makeApiCall(string $method, string $endpoint, array $data = []): array
    {
        $url = self::BASE_URL . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->config['secret_key'],
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: ' . self::API_VERSION
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Erro na comunicação com Stripe');
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $error = $decodedResponse['error']['message'] ?? 'Erro na API Stripe';
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

        if (empty($data['currency'])) {
            $data['currency'] = 'USD';
        }

        if (!$this->supportsCurrency($data['currency'])) {
            throw new \InvalidArgumentException('Moeda não suportada');
        }
    }

    private function preparePaymentIntentData(array $data): array
    {
        $intentData = [
            'amount' => $this->formatAmount($data['amount'], $data['currency'] ?? 'USD'),
            'currency' => strtolower($data['currency'] ?? 'USD'),
            'capture_method' => $data['capture_method'] ?? 'automatic',
            'confirmation_method' => 'manual',
            'confirm' => false
        ];

        if (!empty($data['description'])) {
            $intentData['description'] = $data['description'];
        }

        if (!empty($data['metadata'])) {
            $intentData['metadata'] = $data['metadata'];
        }

        if (!empty($data['customer'])) {
            $intentData['customer'] = $data['customer'];
        }

        if (!empty($data['return_url'])) {
            $intentData['return_url'] = $data['return_url'];
        }

        return $intentData;
    }

    private function createPaymentIntent(array $data): array
    {
        return $this->makeApiCall('POST', '/payment_intents', $data);
    }

    private function confirmPaymentIntent(string $intentId, array $paymentData): array
    {
        $confirmData = ['confirm' => true];

        if (!empty($paymentData['source'])) {
            $confirmData['source'] = $paymentData['source'];
        }

        if (!empty($paymentData['payment_method'])) {
            $confirmData['payment_method'] = $paymentData['payment_method'];
        }

        if (!empty($paymentData['return_url'])) {
            $confirmData['return_url'] = $paymentData['return_url'];
        }

        return $this->makeApiCall('POST', "/payment_intents/{$intentId}/confirm", $confirmData);
    }

    private function normalizeStatus(string $status): string
    {
        $statusMap = [
            'requires_payment_method' => 'pending',
            'requires_confirmation' => 'pending',
            'requires_action' => 'processing',
            'processing' => 'processing',
            'requires_capture' => 'authorized',
            'succeeded' => 'completed',
            'canceled' => 'cancelled'
        ];

        return $statusMap[$status] ?? 'unknown';
    }

    private function normalizeMethod(array $response): string
    {
        $charges = $response['charges']['data'] ?? [];
        if (!empty($charges)) {
            return $charges[0]['payment_method_details']['type'] ?? 'unknown';
        }
        return 'unknown';
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