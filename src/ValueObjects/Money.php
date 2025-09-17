<?php

declare(strict_types=1);

namespace Clubify\Checkout\ValueObjects;

use Clubify\Checkout\Enums\Currency;
use InvalidArgumentException;

/**
 * Value Object para representar valores monetários
 *
 * Demonstra recursos PHP 8.2+:
 * - Constructor Property Promotion
 * - Readonly Properties
 * - Union Types
 * - Match Expressions
 * - Named Arguments
 */
final readonly class Money implements \JsonSerializable, \Stringable
{
    /**
     * Construtor com Property Promotion
     */
    public function __construct(
        public int $amountInCents,
        public Currency $currency = Currency::BRL,
        public ?string $description = null,
    ) {
        if ($this->amountInCents < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    /**
     * Cria instância a partir de valor decimal
     */
    public static function fromDecimal(
        float $amount,
        Currency $currency = Currency::BRL,
        ?string $description = null,
    ): self {
        return new self(
            amountInCents: (int) round($amount * 100),
            currency: $currency,
            description: $description,
        );
    }

    /**
     * Cria instância a partir de string
     */
    public static function fromString(
        string $amount,
        Currency $currency = Currency::BRL,
        ?string $description = null,
    ): self {
        $cleaned = preg_replace('/[^\d.,]/', '', $amount);
        $decimal = (float) str_replace(',', '.', $cleaned);

        return self::fromDecimal(
            amount: $decimal,
            currency: $currency,
            description: $description,
        );
    }

    /**
     * Obtém valor em decimais
     */
    public function toDecimal(): float
    {
        return $this->amountInCents / 100;
    }

    /**
     * Obtém valor formatado
     */
    public function toFormattedString(): string
    {
        $decimal = $this->toDecimal();

        return match ($this->currency) {
            Currency::BRL => 'R$ ' . number_format($decimal, 2, ',', '.'),
            Currency::USD => '$' . number_format($decimal, 2, '.', ','),
            Currency::EUR => '€' . number_format($decimal, 2, ',', '.'),
            Currency::GBP => '£' . number_format($decimal, 2, '.', ','),
            default => $this->currency->getSymbol() . ' ' . number_format($decimal, 2),
        };
    }

    /**
     * Soma com outro Money
     */
    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);

        return new self(
            amountInCents: $this->amountInCents + $other->amountInCents,
            currency: $this->currency,
            description: $this->combineDescriptions($other),
        );
    }

    /**
     * Subtrai outro Money
     */
    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);

        $newAmount = $this->amountInCents - $other->amountInCents;

        if ($newAmount < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self(
            amountInCents: $newAmount,
            currency: $this->currency,
            description: $this->combineDescriptions($other),
        );
    }

    /**
     * Multiplica por um fator
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Factor cannot be negative');
        }

        return new self(
            amountInCents: (int) round($this->amountInCents * $factor),
            currency: $this->currency,
            description: $this->description,
        );
    }

    /**
     * Divide por um divisor
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive');
        }

        return new self(
            amountInCents: (int) round($this->amountInCents / $divisor),
            currency: $this->currency,
            description: $this->description,
        );
    }

    /**
     * Calcula porcentagem
     */
    public function percentage(float $percent): self
    {
        return $this->multiply($percent / 100);
    }

    /**
     * Adiciona porcentagem
     */
    public function addPercentage(float $percent): self
    {
        return $this->add($this->percentage($percent));
    }

    /**
     * Subtrai porcentagem
     */
    public function subtractPercentage(float $percent): self
    {
        return $this->subtract($this->percentage($percent));
    }

    /**
     * Verifica se é maior que outro Money
     */
    public function isGreaterThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    /**
     * Verifica se é menor que outro Money
     */
    public function isLessThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents < $other->amountInCents;
    }

    /**
     * Verifica se é igual a outro Money
     */
    public function isEqualTo(Money $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountInCents === $other->amountInCents;
    }

    /**
     * Verifica se é zero
     */
    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    /**
     * Verifica se é positivo
     */
    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    /**
     * Distribui valor em partes iguais
     */
    public function distribute(int $parts): array
    {
        if ($parts <= 0) {
            throw new InvalidArgumentException('Parts must be positive');
        }

        $baseAmount = intval($this->amountInCents / $parts);
        $remainder = $this->amountInCents % $parts;

        $result = [];
        for ($i = 0; $i < $parts; $i++) {
            $amount = $baseAmount + ($i < $remainder ? 1 : 0);
            $result[] = new self(
                amountInCents: $amount,
                currency: $this->currency,
                description: $this->description,
            );
        }

        return $result;
    }

    /**
     * Distribui valor por proporções
     */
    public function distributeByRatio(array $ratios): array
    {
        if (empty($ratios)) {
            throw new InvalidArgumentException('Ratios cannot be empty');
        }

        $totalRatio = array_sum($ratios);
        if ($totalRatio <= 0) {
            throw new InvalidArgumentException('Total ratio must be positive');
        }

        $result = [];
        $totalDistributed = 0;

        foreach ($ratios as $i => $ratio) {
            if ($i === count($ratios) - 1) {
                // Último item recebe o restante para evitar erros de arredondamento
                $amount = $this->amountInCents - $totalDistributed;
            } else {
                $amount = (int) round(($this->amountInCents * $ratio) / $totalRatio);
                $totalDistributed += $amount;
            }

            $result[] = new self(
                amountInCents: $amount,
                currency: $this->currency,
                description: $this->description,
            );
        }

        return $result;
    }

    /**
     * Converte para outra moeda (requer taxa de câmbio)
     */
    public function convertTo(Currency $targetCurrency, float $exchangeRate): self
    {
        if ($targetCurrency === $this->currency) {
            return $this;
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be positive');
        }

        return new self(
            amountInCents: (int) round($this->amountInCents * $exchangeRate),
            currency: $targetCurrency,
            description: $this->description,
        );
    }

    /**
     * Garante que as moedas são iguais
     */
    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency->value} vs {$other->currency->value}"
            );
        }
    }

    /**
     * Combina descrições
     */
    private function combineDescriptions(Money $other): ?string
    {
        return match (true) {
            $this->description === null && $other->description === null => null,
            $this->description === null => $other->description,
            $other->description === null => $this->description,
            $this->description === $other->description => $this->description,
            default => "{$this->description} + {$other->description}",
        };
    }

    /**
     * Implementação de JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return [
            'amount_in_cents' => $this->amountInCents,
            'amount_decimal' => $this->toDecimal(),
            'currency' => $this->currency->value,
            'formatted' => $this->toFormattedString(),
            'description' => $this->description,
        ];
    }

    /**
     * Implementação de Stringable
     */
    public function __toString(): string
    {
        return $this->toFormattedString();
    }

    /**
     * Factory para valores comuns
     */
    public static function zero(Currency $currency = Currency::BRL): self
    {
        return new self(
            amountInCents: 0,
            currency: $currency,
        );
    }

    /**
     * Factory para um centavo
     */
    public static function oneCent(Currency $currency = Currency::BRL): self
    {
        return new self(
            amountInCents: 1,
            currency: $currency,
        );
    }

    /**
     * Factory para uma unidade monetária
     */
    public static function oneUnit(Currency $currency = Currency::BRL): self
    {
        return new self(
            amountInCents: 100,
            currency: $currency,
        );
    }
}