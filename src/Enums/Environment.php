<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

/**
 * Enum para ambientes de execução do SDK
 *
 * Define os ambientes disponíveis conforme esperado pela API Clubify Checkout.
 * A API usa apenas dois ambientes:
 * - test: Ambiente de testes/desenvolvimento
 * - live: Ambiente de produção (ao vivo)
 *
 * Formato das API keys: clb_(org|multi|tenant)_(test|live)_[hex32]
 *
 * Aliases aceitos: development, sandbox → test | production → live
 */
enum Environment: string
{
    case TEST = 'test';
    case LIVE = 'live';

    /**
     * Normaliza valores de ambiente para os valores aceitos pela API
     *
     * @param string $value Valor do ambiente (pode ser alias)
     * @return string Valor normalizado (test ou live)
     */
    public static function normalize(string $value): string
    {
        return match (strtolower($value)) {
            'test', 'dev', 'development', 'sandbox' => 'test',
            'live', 'prod', 'production' => 'live',
            default => $value, // Retorna o valor original se não for alias conhecido
        };
    }

    /**
     * Cria um Environment a partir de um valor, normalizando aliases
     *
     * @param string $value Valor do ambiente
     * @return self
     */
    public static function fromValue(string $value): self
    {
        $normalized = self::normalize($value);
        return self::from($normalized);
    }

    public function isProduction(): bool
    {
        return $this === self::LIVE;
    }

    public function isDevelopment(): bool
    {
        return $this === self::TEST;
    }

    public function isTest(): bool
    {
        return $this === self::TEST;
    }

    public function isLive(): bool
    {
        return $this === self::LIVE;
    }

    /**
     * Retorna o valor para enviar à API (sempre test ou live)
     */
    public function toApiValue(): string
    {
        return $this->value;
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::TEST => 'https://checkout.svelve.com/api/v1',
            self::LIVE => 'https://checkout.svelve.com/api/v1',
        };
    }

}
