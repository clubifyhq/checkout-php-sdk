<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\DTOs;

use Clubify\Checkout\Data\BaseData;
use Clubify\Checkout\Utils\Validators\CreditCardValidator;
use Clubify\Checkout\Utils\Validators\EmailValidator;
use Clubify\Checkout\Utils\Validators\CPFValidator;
use Clubify\Checkout\Utils\Validators\CNPJValidator;
use InvalidArgumentException;

/**
 * DTO para dados de pagamento
 *
 * Encapsula e valida todos os dados necessários
 * para processar um pagamento, incluindo informações
 * do cliente, método de pagamento e configurações.
 *
 * Implementa validação robusta seguindo padrões:
 * - PCI-DSS para dados de cartão
 * - Validação de documentos brasileiros
 * - Verificação de formatos de dados
 * - Sanitização de entradas
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas dados de pagamento
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível por DTOs específicos
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Usa validadores abstratos
 */
class PaymentData extends BaseData
{
    public string $amount;
    public string $currency;
    public string $paymentMethod;
    public ?string $customerId;
    public ?string $orderId;
    public ?string $sessionId;
    public ?string $organizationId;
    public ?array $customer;
    public ?array $billing;
    public ?array $shipping;
    public ?array $card;
    public ?array $boleto;
    public ?array $pix;
    public ?array $installments;
    public ?array $metadata;
    public ?array $antifraud;
    public ?string $description;
    public ?string $statementDescriptor;
    public ?string $successUrl;
    public ?string $cancelUrl;
    public ?string $webhookUrl;

    /**
     * Regras de validação
     */
    protected array $validationRules = [
        'amount' => ['required', 'numeric', 'min:0.01'],
        'currency' => ['required', 'string', 'in:BRL,USD,EUR'],
        'paymentMethod' => ['required', 'string', 'in:credit_card,debit_card,boleto,pix,apple_pay,google_pay'],
        'customerId' => ['nullable', 'string', 'max:255'],
        'orderId' => ['nullable', 'string', 'max:255'],
        'sessionId' => ['nullable', 'string', 'max:255'],
        'organizationId' => ['nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:500'],
        'statementDescriptor' => ['nullable', 'string', 'max:22'], // Limite do Stripe
    ];

    /**
     * Campos obrigatórios
     */
    protected array $requiredFields = [
        'amount',
        'currency',
        'paymentMethod',
    ];

    /**
     * Mapeamento de campos
     */
    protected array $fieldMapping = [
        'value' => 'amount',
        'method' => 'paymentMethod',
        'customer_id' => 'customerId',
        'order_id' => 'orderId',
        'session_id' => 'sessionId',
        'organization_id' => 'organizationId',
        'statement_descriptor' => 'statementDescriptor',
        'success_url' => 'successUrl',
        'cancel_url' => 'cancelUrl',
        'webhook_url' => 'webhookUrl',
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
            'metadata' => [],
        ], $data);

        parent::__construct($data);
    }

    /**
     * Validação customizada
     */
    protected function customValidation(): void
    {
        $this->validateAmount();
        $this->validatePaymentMethod();
        $this->validateCustomerData();
        $this->validateBillingData();
        $this->validateShippingData();
        $this->validateCardData();
        $this->validateBoletoData();
        $this->validatePixData();
        $this->validateInstallments();
        $this->validateUrls();
    }

    /**
     * Valida valor do pagamento
     */
    private function validateAmount(): void
    {
        $amount = (float) $this->amount;

        if ($amount <= 0) {
            throw new InvalidArgumentException("Valor deve ser maior que zero");
        }

        // Limites por moeda
        $limits = [
            'BRL' => ['min' => 0.50, 'max' => 999999.99],
            'USD' => ['min' => 0.50, 'max' => 999999.99],
            'EUR' => ['min' => 0.50, 'max' => 999999.99],
        ];

        $currency = $this->currency;
        if (isset($limits[$currency])) {
            if ($amount < $limits[$currency]['min']) {
                throw new InvalidArgumentException("Valor mínimo para {$currency}: {$limits[$currency]['min']}");
            }

            if ($amount > $limits[$currency]['max']) {
                throw new InvalidArgumentException("Valor máximo para {$currency}: {$limits[$currency]['max']}");
            }
        }
    }

    /**
     * Valida método de pagamento
     */
    private function validatePaymentMethod(): void
    {
        // Validações específicas por método
        switch ($this->paymentMethod) {
            case 'credit_card':
            case 'debit_card':
                if (empty($this->card)) {
                    throw new InvalidArgumentException("Dados do cartão são obrigatórios para {$this->paymentMethod}");
                }
                break;

            case 'boleto':
                if (empty($this->boleto)) {
                    throw new InvalidArgumentException("Dados do boleto são obrigatórios");
                }
                break;

            case 'pix':
                if (empty($this->pix)) {
                    throw new InvalidArgumentException("Dados do PIX são obrigatórios");
                }
                break;
        }
    }

    /**
     * Valida dados do cliente
     */
    private function validateCustomerData(): void
    {
        if (empty($this->customer)) {
            return;
        }

        $customer = $this->customer;

        // Valida campos obrigatórios
        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (empty($customer[$field])) {
                throw new InvalidArgumentException("Campo obrigatório do cliente: {$field}");
            }
        }

        // Valida email
        $emailValidator = new EmailValidator();
        if (!$emailValidator->validate($customer['email'])) {
            throw new InvalidArgumentException("Email do cliente inválido");
        }

        // Valida documento se fornecido
        if (!empty($customer['document'])) {
            $this->validateDocument($customer['document'], $customer['document_type'] ?? 'cpf');
        }

        // Valida telefone se fornecido
        if (!empty($customer['phone'])) {
            if (!$this->validatePhone($customer['phone'])) {
                throw new InvalidArgumentException("Telefone do cliente inválido");
            }
        }
    }

    /**
     * Valida dados de cobrança
     */
    private function validateBillingData(): void
    {
        if (empty($this->billing)) {
            return;
        }

        $billing = $this->billing;

        // Campos obrigatórios para endereço de cobrança
        $required = ['street', 'number', 'city', 'state', 'zip_code'];
        foreach ($required as $field) {
            if (empty($billing[$field])) {
                throw new InvalidArgumentException("Campo obrigatório do endereço de cobrança: {$field}");
            }
        }

        // Valida CEP se brasileiro
        if (!empty($billing['country']) && $billing['country'] === 'BR') {
            if (!$this->validateZipCode($billing['zip_code'])) {
                throw new InvalidArgumentException("CEP inválido");
            }
        }
    }

    /**
     * Valida dados de entrega
     */
    private function validateShippingData(): void
    {
        if (empty($this->shipping)) {
            return;
        }

        $shipping = $this->shipping;

        // Campos obrigatórios para endereço de entrega
        $required = ['street', 'number', 'city', 'state', 'zip_code'];
        foreach ($required as $field) {
            if (empty($shipping[$field])) {
                throw new InvalidArgumentException("Campo obrigatório do endereço de entrega: {$field}");
            }
        }

        // Valida CEP se brasileiro
        if (!empty($shipping['country']) && $shipping['country'] === 'BR') {
            if (!$this->validateZipCode($shipping['zip_code'])) {
                throw new InvalidArgumentException("CEP de entrega inválido");
            }
        }
    }

    /**
     * Valida dados do cartão
     */
    private function validateCardData(): void
    {
        if (empty($this->card) || !in_array($this->paymentMethod, ['credit_card', 'debit_card'])) {
            return;
        }

        $card = $this->card;
        $cardValidator = new CreditCardValidator();

        // Se for token, apenas valida o token
        if (!empty($card['token'])) {
            if (strlen($card['token']) < 10) {
                throw new InvalidArgumentException("Token do cartão inválido");
            }
            return;
        }

        // Valida dados completos do cartão
        $required = ['number', 'holder_name', 'expiry_month', 'expiry_year', 'cvv'];
        foreach ($required as $field) {
            if (empty($card[$field])) {
                throw new InvalidArgumentException("Campo obrigatório do cartão: {$field}");
            }
        }

        // Valida usando CreditCardValidator
        if (!$cardValidator->validate($card)) {
            throw new InvalidArgumentException("Dados do cartão inválidos");
        }
    }

    /**
     * Valida dados do boleto
     */
    private function validateBoletoData(): void
    {
        if (empty($this->boleto) || $this->paymentMethod !== 'boleto') {
            return;
        }

        $boleto = $this->boleto;

        // Valida data de vencimento
        if (!empty($boleto['due_date'])) {
            $dueDate = new \DateTime($boleto['due_date']);
            $now = new \DateTime();

            if ($dueDate <= $now) {
                throw new InvalidArgumentException("Data de vencimento deve ser futura");
            }

            // Máximo de 90 dias
            $maxDate = clone $now;
            $maxDate->modify('+90 days');

            if ($dueDate > $maxDate) {
                throw new InvalidArgumentException("Data de vencimento não pode exceder 90 dias");
            }
        }

        // Valida instruções se fornecidas
        if (!empty($boleto['instructions'])) {
            if (is_array($boleto['instructions'])) {
                foreach ($boleto['instructions'] as $instruction) {
                    if (strlen($instruction) > 80) {
                        throw new InvalidArgumentException("Instrução do boleto muito longa (máximo 80 caracteres)");
                    }
                }
            }
        }
    }

    /**
     * Valida dados do PIX
     */
    private function validatePixData(): void
    {
        if (empty($this->pix) || $this->paymentMethod !== 'pix') {
            return;
        }

        $pix = $this->pix;

        // Valida tempo de expiração
        if (!empty($pix['expires_in'])) {
            $expiresIn = (int) $pix['expires_in'];

            if ($expiresIn < 60) { // Mínimo 1 minuto
                throw new InvalidArgumentException("Tempo de expiração mínimo: 60 segundos");
            }

            if ($expiresIn > 86400) { // Máximo 24 horas
                throw new InvalidArgumentException("Tempo de expiração máximo: 86400 segundos (24 horas)");
            }
        }
    }

    /**
     * Valida dados de parcelamento
     */
    private function validateInstallments(): void
    {
        if (empty($this->installments)) {
            return;
        }

        $installments = $this->installments;

        if (!empty($installments['number'])) {
            $number = (int) $installments['number'];

            if ($number < 1) {
                throw new InvalidArgumentException("Número de parcelas deve ser maior que zero");
            }

            if ($number > 24) {
                throw new InvalidArgumentException("Número máximo de parcelas: 24");
            }

            // Valida valor mínimo por parcela (R$ 5,00)
            if ($this->currency === 'BRL') {
                $installmentAmount = (float) $this->amount / $number;
                if ($installmentAmount < 5.00) {
                    throw new InvalidArgumentException("Valor mínimo por parcela: R$ 5,00");
                }
            }
        }
    }

    /**
     * Valida URLs
     */
    private function validateUrls(): void
    {
        $urls = ['successUrl', 'cancelUrl', 'webhookUrl'];

        foreach ($urls as $urlField) {
            if (!empty($this->$urlField)) {
                if (!filter_var($this->$urlField, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException("{$urlField} inválida");
                }

                // Verifica se é HTTPS (exceto localhost)
                $parsed = parse_url($this->$urlField);
                if ($parsed['scheme'] !== 'https' && $parsed['host'] !== 'localhost') {
                    throw new InvalidArgumentException("{$urlField} deve usar HTTPS");
                }
            }
        }
    }

    /**
     * Valida documento (CPF/CNPJ)
     */
    private function validateDocument(string $document, string $type): void
    {
        switch ($type) {
            case 'cpf':
                $validator = new CPFValidator();
                if (!$validator->validate($document)) {
                    throw new InvalidArgumentException("CPF inválido");
                }
                break;

            case 'cnpj':
                $validator = new CNPJValidator();
                if (!$validator->validate($document)) {
                    throw new InvalidArgumentException("CNPJ inválido");
                }
                break;

            default:
                throw new InvalidArgumentException("Tipo de documento inválido: {$type}");
        }
    }

    /**
     * Valida telefone brasileiro
     */
    private function validatePhone(string $phone): bool
    {
        // Remove caracteres não numéricos
        $cleanPhone = preg_replace('/\D/', '', $phone);

        // Telefone brasileiro: 11 dígitos (celular) ou 10 dígitos (fixo)
        return preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $cleanPhone);
    }

    /**
     * Valida CEP brasileiro
     */
    private function validateZipCode(string $zipCode): bool
    {
        // Remove caracteres não numéricos
        $cleanZip = preg_replace('/\D/', '', $zipCode);

        // CEP brasileiro: 8 dígitos
        return preg_match('/^\d{8}$/', $cleanZip);
    }

    /**
     * Obtém dados sanitizados para processamento
     */
    public function toProcessingArray(): array
    {
        $data = $this->toArray();

        // Remove dados sensíveis se necessário
        if (isset($data['card']['cvv'])) {
            // CVV não deve ser armazenado
            unset($data['card']['cvv']);
        }

        return $data;
    }

    /**
     * Obtém apenas dados essenciais para log
     */
    public function toLogArray(): array
    {
        $data = $this->toArray();

        // Remove dados sensíveis para log
        if (isset($data['card'])) {
            $data['card'] = [
                'last_four' => substr($data['card']['number'] ?? '', -4),
                'brand' => $data['card']['brand'] ?? null,
                'holder_name' => $data['card']['holder_name'] ?? null,
            ];
        }

        return $data;
    }
}
