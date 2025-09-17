<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\DTOs;

use ClubifyCheckout\Core\BaseDTO;
use InvalidArgumentException;

/**
 * DTO para dados de transação
 *
 * Encapsula informações completas sobre uma transação
 * de pagamento, incluindo status, dados do gateway,
 * histórico de eventos e metadados relacionados.
 *
 * Funcionalidades principais:
 * - Rastreamento completo de status
 * - Histórico de eventos e tentativas
 * - Dados de reconciliação
 * - Informações de risco e antifraude
 * - Metadados extensíveis
 * - Auditoria e compliance
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas dados de transação
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível por DTOs específicos
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Usa abstrações
 */
class TransactionData extends BaseDTO
{
    public string $id;
    public string $paymentId;
    public string $gatewayTransactionId;
    public string $gateway;
    public string $status;
    public string $type;
    public float $amount;
    public string $currency;
    public ?string $authorizationId;
    public ?string $captureId;
    public ?string $refundId;
    public ?string $customerId;
    public ?string $orderId;
    public ?string $sessionId;
    public ?string $organizationId;
    public ?array $paymentMethod;
    public ?array $customer;
    public ?array $billing;
    public ?array $shipping;
    public ?array $gatewayData;
    public ?array $riskData;
    public ?array $antiFraudData;
    public ?array $events;
    public ?array $attempts;
    public ?array $refunds;
    public ?array $chargebacks;
    public ?array $fees;
    public ?array $reconciliation;
    public ?array $metadata;
    public ?string $description;
    public ?string $statementDescriptor;
    public ?\DateTime $authorizedAt;
    public ?\DateTime $capturedAt;
    public ?\DateTime $failedAt;
    public ?\DateTime $cancelledAt;
    public ?\DateTime $refundedAt;
    public ?\DateTime $createdAt;
    public ?\DateTime $updatedAt;

    /**
     * Status possíveis da transação
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_VOIDED = 'voided';

    /**
     * Tipos de transação
     */
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_AUTHORIZATION = 'authorization';
    public const TYPE_CAPTURE = 'capture';
    public const TYPE_REFUND = 'refund';
    public const TYPE_VOID = 'void';
    public const TYPE_CHARGEBACK = 'chargeback';

    /**
     * Status válidos
     */
    private array $validStatuses = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_AUTHORIZED,
        self::STATUS_CAPTURED,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
        self::STATUS_PARTIALLY_REFUNDED,
        self::STATUS_DISPUTED,
        self::STATUS_VOIDED,
    ];

    /**
     * Tipos válidos
     */
    private array $validTypes = [
        self::TYPE_PAYMENT,
        self::TYPE_AUTHORIZATION,
        self::TYPE_CAPTURE,
        self::TYPE_REFUND,
        self::TYPE_VOID,
        self::TYPE_CHARGEBACK,
    ];

    /**
     * Regras de validação
     */
    protected array $validationRules = [
        'id' => ['required', 'string', 'max:255'],
        'paymentId' => ['required', 'string', 'max:255'],
        'gatewayTransactionId' => ['required', 'string', 'max:255'],
        'gateway' => ['required', 'string', 'max:50'],
        'status' => ['required', 'string'],
        'type' => ['required', 'string'],
        'amount' => ['required', 'numeric', 'min:0'],
        'currency' => ['required', 'string', 'size:3'],
        'customerId' => ['nullable', 'string', 'max:255'],
        'orderId' => ['nullable', 'string', 'max:255'],
        'sessionId' => ['nullable', 'string', 'max:255'],
        'organizationId' => ['nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:500'],
        'statementDescriptor' => ['nullable', 'string', 'max:22'],
    ];

    /**
     * Campos obrigatórios
     */
    protected array $requiredFields = [
        'id',
        'paymentId',
        'gatewayTransactionId',
        'gateway',
        'status',
        'type',
        'amount',
        'currency',
    ];

    /**
     * Mapeamento de campos
     */
    protected array $fieldMapping = [
        'payment_id' => 'paymentId',
        'gateway_transaction_id' => 'gatewayTransactionId',
        'authorization_id' => 'authorizationId',
        'capture_id' => 'captureId',
        'refund_id' => 'refundId',
        'customer_id' => 'customerId',
        'order_id' => 'orderId',
        'session_id' => 'sessionId',
        'organization_id' => 'organizationId',
        'payment_method' => 'paymentMethod',
        'gateway_data' => 'gatewayData',
        'risk_data' => 'riskData',
        'anti_fraud_data' => 'antiFraudData',
        'statement_descriptor' => 'statementDescriptor',
        'authorized_at' => 'authorizedAt',
        'captured_at' => 'capturedAt',
        'failed_at' => 'failedAt',
        'cancelled_at' => 'cancelledAt',
        'refunded_at' => 'refundedAt',
        'created_at' => 'createdAt',
        'updated_at' => 'updatedAt',
    ];

    /**
     * Inicializa DTO com dados
     */
    public function __construct(array $data = [])
    {
        // Aplica mapeamento de campos
        $data = $this->mapFields($data);

        // Define valores padrão
        $data = array_merge([
            'currency' => 'BRL',
            'events' => [],
            'attempts' => [],
            'refunds' => [],
            'chargebacks' => [],
            'fees' => [],
            'metadata' => [],
            'createdAt' => new \DateTime(),
            'updatedAt' => new \DateTime(),
        ], $data);

        // Converte strings de data para DateTime
        $this->convertDateFields($data);

        parent::__construct($data);
    }

    /**
     * Validação customizada
     */
    protected function customValidation(): void
    {
        $this->validateStatus();
        $this->validateType();
        $this->validateAmount();
        $this->validateCurrency();
        $this->validateGateway();
        $this->validateStatusTransitions();
        $this->validatePaymentMethod();
        $this->validateEvents();
        $this->validateRefunds();
    }

    /**
     * Valida status da transação
     */
    private function validateStatus(): void
    {
        if (!in_array($this->status, $this->validStatuses)) {
            throw new InvalidArgumentException("Status inválido: {$this->status}");
        }
    }

    /**
     * Valida tipo da transação
     */
    private function validateType(): void
    {
        if (!in_array($this->type, $this->validTypes)) {
            throw new InvalidArgumentException("Tipo inválido: {$this->type}");
        }
    }

    /**
     * Valida valor da transação
     */
    private function validateAmount(): void
    {
        if ($this->amount < 0) {
            throw new InvalidArgumentException("Valor não pode ser negativo");
        }

        // Para refunds, permite valor zero
        if ($this->type !== self::TYPE_REFUND && $this->amount <= 0) {
            throw new InvalidArgumentException("Valor deve ser maior que zero");
        }
    }

    /**
     * Valida moeda
     */
    private function validateCurrency(): void
    {
        $validCurrencies = ['BRL', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'];

        if (!in_array($this->currency, $validCurrencies)) {
            throw new InvalidArgumentException("Moeda não suportada: {$this->currency}");
        }
    }

    /**
     * Valida gateway
     */
    private function validateGateway(): void
    {
        $validGateways = ['pagarme', 'stripe', 'paypal', 'mercadopago'];

        if (!in_array($this->gateway, $validGateways)) {
            throw new InvalidArgumentException("Gateway não suportado: {$this->gateway}");
        }
    }

    /**
     * Valida transições de status
     */
    private function validateStatusTransitions(): void
    {
        // Define transições válidas de status
        $validTransitions = [
            self::STATUS_PENDING => [
                self::STATUS_PROCESSING,
                self::STATUS_AUTHORIZED,
                self::STATUS_PAID,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PROCESSING => [
                self::STATUS_AUTHORIZED,
                self::STATUS_PAID,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_AUTHORIZED => [
                self::STATUS_CAPTURED,
                self::STATUS_PAID,
                self::STATUS_VOIDED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_CAPTURED => [
                self::STATUS_PAID,
                self::STATUS_REFUNDED,
                self::STATUS_PARTIALLY_REFUNDED,
                self::STATUS_DISPUTED,
            ],
            self::STATUS_PAID => [
                self::STATUS_REFUNDED,
                self::STATUS_PARTIALLY_REFUNDED,
                self::STATUS_DISPUTED,
            ],
        ];

        // Valida baseado no histórico de eventos se disponível
        if (!empty($this->events)) {
            $previousStatus = $this->getPreviousStatus();
            if ($previousStatus && isset($validTransitions[$previousStatus])) {
                if (!in_array($this->status, $validTransitions[$previousStatus])) {
                    throw new InvalidArgumentException(
                        "Transição de status inválida: {$previousStatus} -> {$this->status}"
                    );
                }
            }
        }
    }

    /**
     * Valida método de pagamento
     */
    private function validatePaymentMethod(): void
    {
        if (empty($this->paymentMethod)) {
            return;
        }

        $method = $this->paymentMethod;

        if (empty($method['type'])) {
            throw new InvalidArgumentException("Tipo do método de pagamento é obrigatório");
        }

        $validMethods = ['credit_card', 'debit_card', 'boleto', 'pix', 'apple_pay', 'google_pay'];
        if (!in_array($method['type'], $validMethods)) {
            throw new InvalidArgumentException("Método de pagamento inválido: {$method['type']}");
        }
    }

    /**
     * Valida eventos da transação
     */
    private function validateEvents(): void
    {
        if (empty($this->events)) {
            return;
        }

        foreach ($this->events as $event) {
            if (empty($event['type']) || empty($event['timestamp'])) {
                throw new InvalidArgumentException("Evento deve conter tipo e timestamp");
            }
        }
    }

    /**
     * Valida dados de refund
     */
    private function validateRefunds(): void
    {
        if (empty($this->refunds)) {
            return;
        }

        $totalRefunded = 0;
        foreach ($this->refunds as $refund) {
            if (empty($refund['amount']) || $refund['amount'] <= 0) {
                throw new InvalidArgumentException("Valor do refund deve ser maior que zero");
            }
            $totalRefunded += $refund['amount'];
        }

        if ($totalRefunded > $this->amount) {
            throw new InvalidArgumentException("Valor total de refunds excede valor da transação");
        }
    }

    /**
     * Converte campos de data
     */
    private function convertDateFields(array &$data): void
    {
        $dateFields = [
            'authorizedAt',
            'capturedAt',
            'failedAt',
            'cancelledAt',
            'refundedAt',
            'createdAt',
            'updatedAt',
        ];

        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = new \DateTime($data[$field]);
            }
        }
    }

    /**
     * Obtém status anterior baseado no histórico
     */
    private function getPreviousStatus(): ?string
    {
        if (empty($this->events) || count($this->events) < 2) {
            return null;
        }

        // Ordena eventos por timestamp
        $events = $this->events;
        usort($events, function ($a, $b) {
            return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
        });

        // Busca último evento de mudança de status
        for ($i = count($events) - 2; $i >= 0; $i--) {
            if ($events[$i]['type'] === 'status_changed') {
                return $events[$i]['old_status'] ?? null;
            }
        }

        return null;
    }

    /**
     * Adiciona evento à transação
     */
    public function addEvent(string $type, array $data = []): void
    {
        if (empty($this->events)) {
            $this->events = [];
        }

        $event = array_merge([
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data,
        ], $data);

        $this->events[] = $event;
        $this->updatedAt = new \DateTime();
    }

    /**
     * Atualiza status da transação
     */
    public function updateStatus(string $newStatus, array $eventData = []): void
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        // Adiciona evento de mudança de status
        $this->addEvent('status_changed', array_merge([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], $eventData));

        // Atualiza timestamps específicos
        $now = new \DateTime();
        switch ($newStatus) {
            case self::STATUS_AUTHORIZED:
                $this->authorizedAt = $now;
                break;
            case self::STATUS_CAPTURED:
            case self::STATUS_PAID:
                $this->capturedAt = $now;
                break;
            case self::STATUS_FAILED:
                $this->failedAt = $now;
                break;
            case self::STATUS_CANCELLED:
            case self::STATUS_VOIDED:
                $this->cancelledAt = $now;
                break;
            case self::STATUS_REFUNDED:
            case self::STATUS_PARTIALLY_REFUNDED:
                $this->refundedAt = $now;
                break;
        }
    }

    /**
     * Adiciona refund à transação
     */
    public function addRefund(array $refundData): void
    {
        if (empty($this->refunds)) {
            $this->refunds = [];
        }

        $refund = array_merge([
            'id' => 'ref_' . uniqid(),
            'amount' => 0,
            'reason' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ], $refundData);

        $this->refunds[] = $refund;

        // Atualiza status baseado no valor total refunded
        $totalRefunded = $this->getTotalRefunded();
        if ($totalRefunded >= $this->amount) {
            $this->updateStatus(self::STATUS_REFUNDED);
        } else {
            $this->updateStatus(self::STATUS_PARTIALLY_REFUNDED);
        }
    }

    /**
     * Obtém valor total refunded
     */
    public function getTotalRefunded(): float
    {
        if (empty($this->refunds)) {
            return 0.0;
        }

        return array_sum(array_column($this->refunds, 'amount'));
    }

    /**
     * Verifica se transação está em status final
     */
    public function isFinalStatus(): bool
    {
        $finalStatuses = [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
            self::STATUS_VOIDED,
        ];

        return in_array($this->status, $finalStatuses);
    }

    /**
     * Verifica se transação foi bem-sucedida
     */
    public function isSuccessful(): bool
    {
        $successStatuses = [
            self::STATUS_AUTHORIZED,
            self::STATUS_CAPTURED,
            self::STATUS_PAID,
        ];

        return in_array($this->status, $successStatuses);
    }

    /**
     * Verifica se transação pode ser capturada
     */
    public function canBeCapured(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    /**
     * Verifica se transação pode ser estornada
     */
    public function canBeRefunded(): bool
    {
        $refundableStatuses = [
            self::STATUS_CAPTURED,
            self::STATUS_PAID,
            self::STATUS_PARTIALLY_REFUNDED,
        ];

        if (!in_array($this->status, $refundableStatuses)) {
            return false;
        }

        $totalRefunded = $this->getTotalRefunded();
        return $totalRefunded < $this->amount;
    }

    /**
     * Obtém valor disponível para refund
     */
    public function getRefundableAmount(): float
    {
        if (!$this->canBeRefunded()) {
            return 0.0;
        }

        return $this->amount - $this->getTotalRefunded();
    }

    /**
     * Converte para array para API externa
     */
    public function toApiArray(): array
    {
        $data = $this->toArray();

        // Formata datas para ISO 8601
        $dateFields = ['authorizedAt', 'capturedAt', 'failedAt', 'cancelledAt', 'refundedAt', 'createdAt', 'updatedAt'];
        foreach ($dateFields as $field) {
            if ($this->$field instanceof \DateTime) {
                $data[snake_case($field)] = $this->$field->format('c');
            }
        }

        return $data;
    }

    /**
     * Converte para array sem dados sensíveis para logs
     */
    public function toLogArray(): array
    {
        $data = $this->toArray();

        // Remove dados sensíveis
        if (isset($data['gatewayData'])) {
            unset($data['gatewayData']['api_key'], $data['gatewayData']['secret']);
        }

        if (isset($data['paymentMethod']['card'])) {
            $data['paymentMethod']['card'] = [
                'last_four' => $data['paymentMethod']['card']['last_four'] ?? null,
                'brand' => $data['paymentMethod']['card']['brand'] ?? null,
            ];
        }

        return $data;
    }
}