<?php

declare(strict_types=1);

namespace Clubify\Checkout\Attributes;

use Attribute;

/**
 * Attribute para marcar dados sensíveis que devem ser mascarados
 *
 * Usado para compliance PCI DSS e proteção de dados
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Sensitive
{
    public function __construct(
        public readonly string $maskType = 'full',
        public readonly string $maskChar = '*',
        public readonly int $visibleChars = 4,
        public readonly bool $logSafe = false,
    ) {
    }

    /**
     * Mascara um valor baseado no tipo
     */
    public function mask(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        return match ($this->maskType) {
            'full' => str_repeat($this->maskChar, strlen($value)),
            'partial' => $this->partialMask($value),
            'start' => $this->maskChar . substr($value, 1),
            'end' => substr($value, 0, -1) . $this->maskChar,
            'middle' => $this->middleMask($value),
            'card' => $this->cardMask($value),
            'email' => $this->emailMask($value),
            'cpf' => $this->cpfMask($value),
            default => $this->partialMask($value),
        };
    }

    /**
     * Máscara parcial (mostra apenas alguns caracteres)
     */
    private function partialMask(string $value): string
    {
        $length = strlen($value);

        if ($length <= $this->visibleChars) {
            return str_repeat($this->maskChar, $length);
        }

        $visibleStart = (int) ceil($this->visibleChars / 2);
        $visibleEnd = $this->visibleChars - $visibleStart;

        return substr($value, 0, $visibleStart) .
               str_repeat($this->maskChar, $length - $this->visibleChars) .
               substr($value, -$visibleEnd);
    }

    /**
     * Máscara do meio (mantém início e fim)
     */
    private function middleMask(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat($this->maskChar, $length);
        }

        $keepStart = 2;
        $keepEnd = 2;

        return substr($value, 0, $keepStart) .
               str_repeat($this->maskChar, $length - $keepStart - $keepEnd) .
               substr($value, -$keepEnd);
    }

    /**
     * Máscara para cartão de crédito
     */
    private function cardMask(string $value): string
    {
        $cleaned = preg_replace('/\D/', '', $value);
        $length = strlen($cleaned);

        if ($length < 4) {
            return str_repeat($this->maskChar, $length);
        }

        return str_repeat($this->maskChar, $length - 4) . substr($cleaned, -4);
    }

    /**
     * Máscara para email
     */
    private function emailMask(string $value): string
    {
        if (!str_contains($value, '@')) {
            return $this->partialMask($value);
        }

        [$local, $domain] = explode('@', $value, 2);

        $maskedLocal = strlen($local) > 2
            ? substr($local, 0, 1) . str_repeat($this->maskChar, strlen($local) - 2) . substr($local, -1)
            : str_repeat($this->maskChar, strlen($local));

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Máscara para CPF
     */
    private function cpfMask(string $value): string
    {
        $cleaned = preg_replace('/\D/', '', $value);

        if (strlen($cleaned) !== 11) {
            return $this->partialMask($value);
        }

        return substr($cleaned, 0, 3) . '.' .
               str_repeat($this->maskChar, 3) . '.' .
               str_repeat($this->maskChar, 3) . '-' .
               substr($cleaned, -2);
    }

    /**
     * Verifica se o valor pode ser logado com segurança
     */
    public function isLogSafe(): bool
    {
        return $this->logSafe;
    }

    /**
     * Prepara valor para logging
     */
    public function forLogging(mixed $value): mixed
    {
        if ($this->logSafe) {
            return $value;
        }

        if (is_string($value)) {
            return $this->mask($value);
        }

        return '[SENSITIVE]';
    }
}
