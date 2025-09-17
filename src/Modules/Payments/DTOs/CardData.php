<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\DTOs;

use ClubifyCheckout\Core\BaseDTO;
use ClubifyCheckout\Utils\Validators\CreditCardValidator;
use InvalidArgumentException;

/**
 * DTO para dados de cartão de crédito/débito
 *
 * Encapsula e valida dados de cartão seguindo
 * padrões de segurança PCI-DSS e implementando
 * validações robustas para todos os campos.
 *
 * Funcionalidades principais:
 * - Validação de número de cartão (Luhn)
 * - Detecção automática de bandeira
 * - Validação de datas de expiração
 * - Verificação de CVV
 * - Sanitização de dados
 * - Mascaramento para logs
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas dados de cartão
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível por DTOs específicos
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Usa validadores abstratos
 */
class CardData extends BaseDTO
{
    public ?string $token;
    public ?string $number;
    public ?string $holderName;
    public ?string $expiryMonth;
    public ?string $expiryYear;
    public ?string $cvv;
    public ?string $brand;
    public ?string $lastFour;
    public ?string $bin;
    public ?string $customerId;
    public ?bool $saveCard;
    public ?bool $isPrimary;
    public ?array $billingAddress;
    public ?array $metadata;

    /**
     * Regras de validação
     */
    protected array $validationRules = [
        'token' => ['nullable', 'string', 'min:10'],
        'number' => ['nullable', 'string', 'min:13', 'max:19'],
        'holderName' => ['nullable', 'string', 'min:2', 'max:100'],
        'expiryMonth' => ['nullable', 'string', 'size:2'],
        'expiryYear' => ['nullable', 'string', 'size:4'],
        'cvv' => ['nullable', 'string', 'min:3', 'max:4'],
        'brand' => ['nullable', 'string', 'max:20'],
        'customerId' => ['nullable', 'string', 'max:255'],
    ];

    /**
     * Mapeamento de campos
     */
    protected array $fieldMapping = [
        'holder_name' => 'holderName',
        'expiry_month' => 'expiryMonth',
        'expiry_year' => 'expiryYear',
        'last_four' => 'lastFour',
        'customer_id' => 'customerId',
        'save_card' => 'saveCard',
        'is_primary' => 'isPrimary',
        'billing_address' => 'billingAddress',
    ];

    /**
     * Bandeiras de cartão suportadas
     */
    private array $supportedBrands = [
        'visa',
        'mastercard',
        'amex',
        'discover',
        'diners',
        'jcb',
        'elo',
        'hipercard',
        'aura',
        'union_pay',
    ];

    /**
     * Padrões de regex para detecção de bandeiras
     */
    private array $brandPatterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$|^2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9][0-9]|7(?:[01][0-9]|20))[0-9]{12}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
        'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
        'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/',
        'elo' => '/^(?:401178|401179|431274|438935|451416|457393|457631|457632|504175|627780|636297|636368|636369)[0-9]{10}$/',
        'hipercard' => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
        'aura' => '/^50[0-9]{14}$/',
        'union_pay' => '/^62[0-9]{14,17}$/',
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
            'saveCard' => false,
            'isPrimary' => false,
            'metadata' => [],
        ], $data);

        parent::__construct($data);
    }

    /**
     * Validação customizada
     */
    protected function customValidation(): void
    {
        // Se for token, não precisa validar dados do cartão
        if (!empty($this->token)) {
            $this->validateToken();
            return;
        }

        // Valida dados completos do cartão
        $this->validateRequiredFields();
        $this->validateCardNumber();
        $this->validateExpiryDate();
        $this->validateCvv();
        $this->validateHolderName();
        $this->validateBillingAddress();

        // Auto-detecta bandeira
        $this->detectBrand();
        $this->generateBin();
        $this->generateLastFour();
    }

    /**
     * Valida token de cartão
     */
    private function validateToken(): void
    {
        if (strlen($this->token) < 10) {
            throw new InvalidArgumentException("Token do cartão muito curto");
        }

        // Verifica formato básico do token
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->token)) {
            throw new InvalidArgumentException("Formato de token inválido");
        }
    }

    /**
     * Valida campos obrigatórios
     */
    private function validateRequiredFields(): void
    {
        $required = ['number', 'holderName', 'expiryMonth', 'expiryYear', 'cvv'];

        foreach ($required as $field) {
            if (empty($this->$field)) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }
    }

    /**
     * Valida número do cartão
     */
    private function validateCardNumber(): void
    {
        if (empty($this->number)) {
            return;
        }

        // Remove espaços e caracteres especiais
        $this->number = preg_replace('/\D/', '', $this->number);

        // Verifica comprimento
        if (strlen($this->number) < 13 || strlen($this->number) > 19) {
            throw new InvalidArgumentException("Número do cartão deve ter entre 13 e 19 dígitos");
        }

        // Valida usando algoritmo de Luhn
        $validator = new CreditCardValidator();
        if (!$validator->validateNumber($this->number)) {
            throw new InvalidArgumentException("Número do cartão inválido");
        }
    }

    /**
     * Valida data de expiração
     */
    private function validateExpiryDate(): void
    {
        if (empty($this->expiryMonth) || empty($this->expiryYear)) {
            return;
        }

        $month = (int) $this->expiryMonth;
        $year = (int) $this->expiryYear;

        // Valida mês
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Mês de expiração inválido");
        }

        // Valida ano (aceita formato de 2 ou 4 dígitos)
        if (strlen($this->expiryYear) === 2) {
            $year = 2000 + $year;
            $this->expiryYear = (string) $year;
        }

        if ($year < date('Y') || $year > (date('Y') + 20)) {
            throw new InvalidArgumentException("Ano de expiração inválido");
        }

        // Verifica se não expirou
        $expiryDate = new \DateTime();
        $expiryDate->setDate($year, $month, 1);
        $expiryDate->modify('last day of this month');

        if ($expiryDate < new \DateTime()) {
            throw new InvalidArgumentException("Cartão expirado");
        }

        // Padroniza formato
        $this->expiryMonth = str_pad($this->expiryMonth, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Valida CVV
     */
    private function validateCvv(): void
    {
        if (empty($this->cvv)) {
            return;
        }

        // Remove caracteres não numéricos
        $this->cvv = preg_replace('/\D/', '', $this->cvv);

        // Verifica comprimento baseado na bandeira
        $expectedLength = $this->getExpectedCvvLength();

        if (strlen($this->cvv) !== $expectedLength) {
            throw new InvalidArgumentException("CVV deve ter {$expectedLength} dígitos");
        }

        // Verifica se são todos números
        if (!ctype_digit($this->cvv)) {
            throw new InvalidArgumentException("CVV deve conter apenas números");
        }
    }

    /**
     * Valida nome do portador
     */
    private function validateHolderName(): void
    {
        if (empty($this->holderName)) {
            return;
        }

        // Remove espaços extras e normaliza
        $this->holderName = trim(preg_replace('/\s+/', ' ', $this->holderName));

        // Verifica comprimento
        if (strlen($this->holderName) < 2) {
            throw new InvalidArgumentException("Nome do portador muito curto");
        }

        if (strlen($this->holderName) > 100) {
            throw new InvalidArgumentException("Nome do portador muito longo");
        }

        // Verifica caracteres válidos (letras, espaços, alguns caracteres especiais)
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\.\-\']+$/', $this->holderName)) {
            throw new InvalidArgumentException("Nome do portador contém caracteres inválidos");
        }

        // Converte para maiúsculo (padrão de cartões)
        $this->holderName = strtoupper($this->holderName);
    }

    /**
     * Valida endereço de cobrança
     */
    private function validateBillingAddress(): void
    {
        if (empty($this->billingAddress)) {
            return;
        }

        $address = $this->billingAddress;

        // Campos obrigatórios para endereço completo
        $required = ['street', 'number', 'city', 'state', 'zip_code', 'country'];
        foreach ($required as $field) {
            if (empty($address[$field])) {
                throw new InvalidArgumentException("Campo obrigatório do endereço: {$field}");
            }
        }

        // Valida CEP brasileiro
        if ($address['country'] === 'BR') {
            $zipCode = preg_replace('/\D/', '', $address['zip_code']);
            if (!preg_match('/^\d{8}$/', $zipCode)) {
                throw new InvalidArgumentException("CEP inválido");
            }
            $this->billingAddress['zip_code'] = $zipCode;
        }

        // Valida código do país
        if (strlen($address['country']) !== 2) {
            throw new InvalidArgumentException("Código do país deve ter 2 caracteres");
        }
    }

    /**
     * Detecta bandeira do cartão
     */
    private function detectBrand(): void
    {
        if (empty($this->number)) {
            return;
        }

        foreach ($this->brandPatterns as $brand => $pattern) {
            if (preg_match($pattern, $this->number)) {
                $this->brand = $brand;
                return;
            }
        }

        $this->brand = 'unknown';
    }

    /**
     * Gera BIN (primeiros 6 dígitos)
     */
    private function generateBin(): void
    {
        if (!empty($this->number)) {
            $this->bin = substr($this->number, 0, 6);
        }
    }

    /**
     * Gera últimos 4 dígitos
     */
    private function generateLastFour(): void
    {
        if (!empty($this->number)) {
            $this->lastFour = substr($this->number, -4);
        }
    }

    /**
     * Obtém comprimento esperado do CVV baseado na bandeira
     */
    private function getExpectedCvvLength(): int
    {
        return $this->brand === 'amex' ? 4 : 3;
    }

    /**
     * Verifica se é uma bandeira suportada
     */
    public function isSupportedBrand(): bool
    {
        return in_array($this->brand, $this->supportedBrands);
    }

    /**
     * Verifica se cartão está expirado
     */
    public function isExpired(): bool
    {
        if (empty($this->expiryMonth) || empty($this->expiryYear)) {
            return false;
        }

        $expiryDate = new \DateTime();
        $expiryDate->setDate((int) $this->expiryYear, (int) $this->expiryMonth, 1);
        $expiryDate->modify('last day of this month');

        return $expiryDate < new \DateTime();
    }

    /**
     * Verifica se cartão expira em N meses
     */
    public function isExpiringInMonths(int $months): bool
    {
        if (empty($this->expiryMonth) || empty($this->expiryYear)) {
            return false;
        }

        $expiryDate = new \DateTime();
        $expiryDate->setDate((int) $this->expiryYear, (int) $this->expiryMonth, 1);
        $expiryDate->modify('last day of this month');

        $checkDate = new \DateTime();
        $checkDate->modify("+{$months} months");

        return $expiryDate <= $checkDate && $expiryDate > new \DateTime();
    }

    /**
     * Obtém dados mascarados para exibição
     */
    public function toMaskedArray(): array
    {
        $data = $this->toArray();

        // Mascara número do cartão
        if (!empty($data['number'])) {
            $data['number'] = $this->maskCardNumber($data['number']);
        }

        // Remove CVV
        unset($data['cvv']);

        return $data;
    }

    /**
     * Obtém dados para tokenização (remove dados sensíveis após uso)
     */
    public function toTokenizationArray(): array
    {
        return [
            'number' => $this->number,
            'holder_name' => $this->holderName,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'cvv' => $this->cvv,
            'billing_address' => $this->billingAddress,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Obtém dados seguros para armazenamento (sem dados sensíveis)
     */
    public function toSecureArray(): array
    {
        return [
            'token' => $this->token,
            'brand' => $this->brand,
            'last_four' => $this->lastFour,
            'bin' => $this->bin,
            'holder_name' => $this->holderName,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'customer_id' => $this->customerId,
            'save_card' => $this->saveCard,
            'is_primary' => $this->isPrimary,
            'billing_address' => $this->billingAddress,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Mascara número do cartão
     */
    private function maskCardNumber(string $number): string
    {
        if (strlen($number) <= 4) {
            return $number;
        }

        $firstFour = substr($number, 0, 4);
        $lastFour = substr($number, -4);
        $middleLength = strlen($number) - 8;

        return $firstFour . str_repeat('*', $middleLength) . $lastFour;
    }

    /**
     * Limpa dados sensíveis da memória
     */
    public function clearSensitiveData(): void
    {
        $this->number = null;
        $this->cvv = null;
    }

    /**
     * Converte para array sem dados sensíveis para logs
     */
    public function toLogArray(): array
    {
        return [
            'token' => $this->token ? substr($this->token, 0, 8) . '...' : null,
            'brand' => $this->brand,
            'last_four' => $this->lastFour,
            'holder_name' => $this->holderName,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'customer_id' => $this->customerId,
        ];
    }
}