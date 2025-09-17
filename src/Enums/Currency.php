<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

/**
 * Enum para moedas suportadas
 *
 * Demonstra recursos avançados do PHP 8.2+ com Enums
 */
enum Currency: string
{
    case BRL = 'BRL';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case INR = 'INR';

    /**
     * Obtém símbolo da moeda
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::BRL => 'R$',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY => '¥',
            self::CAD => 'C$',
            self::AUD => 'A$',
            self::CHF => 'Fr',
            self::CNY => '¥',
            self::INR => '₹',
        };
    }

    /**
     * Obtém nome completo da moeda
     */
    public function getName(): string
    {
        return match ($this) {
            self::BRL => 'Real Brasileiro',
            self::USD => 'Dólar Americano',
            self::EUR => 'Euro',
            self::GBP => 'Libra Esterlina',
            self::JPY => 'Iene Japonês',
            self::CAD => 'Dólar Canadense',
            self::AUD => 'Dólar Australiano',
            self::CHF => 'Franco Suíço',
            self::CNY => 'Yuan Chinês',
            self::INR => 'Rupia Indiana',
        };
    }

    /**
     * Obtém número de casas decimais
     */
    public function getDecimalPlaces(): int
    {
        return match ($this) {
            self::JPY => 0, // Iene não usa decimais
            default => 2,
        };
    }

    /**
     * Verifica se é uma moeda principal
     */
    public function isMajor(): bool
    {
        return match ($this) {
            self::USD, self::EUR, self::GBP, self::JPY => true,
            default => false,
        };
    }

    /**
     * Obtém moedas por região
     */
    public static function getByRegion(string $region): array
    {
        return match (strtoupper($region)) {
            'AMERICAS' => [self::USD, self::BRL, self::CAD],
            'EUROPE' => [self::EUR, self::GBP, self::CHF],
            'ASIA' => [self::JPY, self::CNY, self::INR],
            'OCEANIA' => [self::AUD],
            default => self::cases(),
        };
    }
}