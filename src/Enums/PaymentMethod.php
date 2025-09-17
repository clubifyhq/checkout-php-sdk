<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

/**
 * Enum para métodos de pagamento
 *
 * Demonstra recursos avançados do PHP 8.2+:
 * - Enum with methods
 * - Match expressions
 * - Backed enums
 */
enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PIX = 'pix';
    case BANK_SLIP = 'bank_slip';
    case BANK_TRANSFER = 'bank_transfer';
    case DIGITAL_WALLET = 'digital_wallet';
    case CRYPTOCURRENCY = 'cryptocurrency';
    case INSTALLMENTS = 'installments';

    /**
     * Verifica se o método suporta parcelamento
     */
    public function supportsInstallments(): bool
    {
        return match ($this) {
            self::CREDIT_CARD, self::INSTALLMENTS => true,
            default => false,
        };
    }

    /**
     * Obtém tempo de processamento em minutos
     */
    public function getProcessingTime(): int
    {
        return match ($this) {
            self::PIX => 1,
            self::CREDIT_CARD, self::DEBIT_CARD => 2,
            self::DIGITAL_WALLET => 3,
            self::BANK_TRANSFER => 1440, // 24 horas
            self::BANK_SLIP => 2880, // 48 horas
            self::CRYPTOCURRENCY => 30,
            self::INSTALLMENTS => 5,
        };
    }

    /**
     * Verifica se é processamento instantâneo
     */
    public function isInstant(): bool
    {
        return $this->getProcessingTime() <= 5;
    }

    /**
     * Obtém taxas de processamento (porcentagem)
     */
    public function getProcessingFee(): float
    {
        return match ($this) {
            self::PIX => 0.5,
            self::CREDIT_CARD => 2.9,
            self::DEBIT_CARD => 1.9,
            self::DIGITAL_WALLET => 2.5,
            self::BANK_TRANSFER => 1.0,
            self::BANK_SLIP => 3.5,
            self::CRYPTOCURRENCY => 1.5,
            self::INSTALLMENTS => 3.9,
        };
    }

    /**
     * Obtém descrição amigável
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Cartão de Crédito',
            self::DEBIT_CARD => 'Cartão de Débito',
            self::PIX => 'PIX',
            self::BANK_SLIP => 'Boleto Bancário',
            self::BANK_TRANSFER => 'Transferência Bancária',
            self::DIGITAL_WALLET => 'Carteira Digital',
            self::CRYPTOCURRENCY => 'Criptomoeda',
            self::INSTALLMENTS => 'Parcelamento',
        };
    }

    /**
     * Obtém ícone para interface
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::CREDIT_CARD => '💳',
            self::DEBIT_CARD => '💳',
            self::PIX => '🔄',
            self::BANK_SLIP => '🧾',
            self::BANK_TRANSFER => '🏦',
            self::DIGITAL_WALLET => '📱',
            self::CRYPTOCURRENCY => '₿',
            self::INSTALLMENTS => '📊',
        };
    }

    /**
     * Verifica se requer verificação adicional
     */
    public function requiresVerification(): bool
    {
        return match ($this) {
            self::CRYPTOCURRENCY, self::BANK_TRANSFER => true,
            default => false,
        };
    }

    /**
     * Obtém limite mínimo de transação (em centavos)
     */
    public function getMinAmount(): int
    {
        return match ($this) {
            self::PIX => 100, // R$ 1,00
            self::CREDIT_CARD, self::DEBIT_CARD => 200, // R$ 2,00
            self::BANK_SLIP => 500, // R$ 5,00
            self::BANK_TRANSFER => 1000, // R$ 10,00
            self::DIGITAL_WALLET => 150, // R$ 1,50
            self::CRYPTOCURRENCY => 5000, // R$ 50,00
            self::INSTALLMENTS => 5000, // R$ 50,00
        };
    }

    /**
     * Obtém limite máximo de transação (em centavos)
     */
    public function getMaxAmount(): int
    {
        return match ($this) {
            self::PIX => 10000000, // R$ 100.000,00
            self::CREDIT_CARD => 5000000, // R$ 50.000,00
            self::DEBIT_CARD => 1000000, // R$ 10.000,00
            self::BANK_SLIP => 50000000, // R$ 500.000,00
            self::BANK_TRANSFER => 100000000, // R$ 1.000.000,00
            self::DIGITAL_WALLET => 2000000, // R$ 20.000,00
            self::CRYPTOCURRENCY => 1000000000, // R$ 10.000.000,00
            self::INSTALLMENTS => 10000000, // R$ 100.000,00
        };
    }

    /**
     * Verifica se o valor está dentro dos limites
     */
    public function isAmountValid(int $amountInCents): bool
    {
        return $amountInCents >= $this->getMinAmount()
            && $amountInCents <= $this->getMaxAmount();
    }

    /**
     * Obtém métodos disponíveis para país
     */
    public static function getAvailableForCountry(string $countryCode): array
    {
        return match (strtoupper($countryCode)) {
            'BR' => [
                self::CREDIT_CARD,
                self::DEBIT_CARD,
                self::PIX,
                self::BANK_SLIP,
                self::BANK_TRANSFER,
                self::DIGITAL_WALLET,
                self::INSTALLMENTS,
            ],
            'US' => [
                self::CREDIT_CARD,
                self::DEBIT_CARD,
                self::BANK_TRANSFER,
                self::DIGITAL_WALLET,
                self::CRYPTOCURRENCY,
            ],
            default => [
                self::CREDIT_CARD,
                self::BANK_TRANSFER,
                self::DIGITAL_WALLET,
            ],
        };
    }

    /**
     * Obtém métodos recomendados por faixa de valor
     */
    public static function getRecommendedForAmount(int $amountInCents): array
    {
        return match (true) {
            $amountInCents <= 10000 => [ // Até R$ 100
                self::PIX,
                self::CREDIT_CARD,
                self::DIGITAL_WALLET,
            ],
            $amountInCents <= 100000 => [ // Até R$ 1.000
                self::CREDIT_CARD,
                self::PIX,
                self::INSTALLMENTS,
            ],
            $amountInCents <= 1000000 => [ // Até R$ 10.000
                self::INSTALLMENTS,
                self::BANK_TRANSFER,
                self::CREDIT_CARD,
            ],
            default => [ // Acima de R$ 10.000
                self::BANK_TRANSFER,
                self::CRYPTOCURRENCY,
                self::BANK_SLIP,
            ],
        };
    }

    /**
     * Converte de string com validação
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException(
            "Invalid payment method: {$value}. Available: " .
            implode(', ', array_column(self::cases(), 'value'))
        );
    }

    /**
     * Obtém todos os métodos como array associativo
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'name' => $case->getDisplayName(),
                'icon' => $case->getIcon(),
                'instant' => $case->isInstant(),
                'supports_installments' => $case->supportsInstallments(),
                'processing_time' => $case->getProcessingTime(),
                'processing_fee' => $case->getProcessingFee(),
                'min_amount' => $case->getMinAmount(),
                'max_amount' => $case->getMaxAmount(),
                'requires_verification' => $case->requiresVerification(),
            ];
        }
        return $result;
    }

    /**
     * Calcula taxa total incluindo processamento
     */
    public function calculateTotal(int $amountInCents): array
    {
        $fee = (int) round($amountInCents * ($this->getProcessingFee() / 100));
        $total = $amountInCents + $fee;

        return [
            'amount' => $amountInCents,
            'fee' => $fee,
            'total' => $total,
            'fee_percentage' => $this->getProcessingFee(),
        ];
    }
}