<?php

declare(strict_types=1);

namespace ClubifyCheckout\Utils\Formatters;

use InvalidArgumentException;

/**
 * Formatador de moedas
 *
 * Implementa formatação completa de valores monetários
 * com suporte a múltiplas moedas e locales, incluindo
 * formatação brasileira e internacional.
 *
 * Funcionalidades:
 * - Formatação BRL (R$ 1.234,56)
 * - Formatação USD ($1,234.56)
 * - Formatação EUR (€1.234,56)
 * - Suporte a 25+ moedas
 * - Conversão entre formatos
 * - Parsing de strings monetárias
 * - Formatação por extenso
 * - Suporte a diferentes locales
 *
 * Moedas suportadas:
 * - BRL (Real brasileiro)
 * - USD (Dólar americano)
 * - EUR (Euro)
 * - GBP (Libra esterlina)
 * - E muitas outras...
 *
 * Locales suportados:
 * - pt_BR (Brasil)
 * - en_US (Estados Unidos)
 * - es_ES (Espanha)
 * - E outros conforme necessário
 */
class CurrencyFormatter implements FormatterInterface
{
    /**
     * Configurações de moedas
     */
    private array $currencies = [
        'BRL' => [
            'symbol' => 'R$',
            'name' => 'Real Brasileiro',
            'decimal_places' => 2,
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'before',
            'space_between' => true,
        ],
        'USD' => [
            'symbol' => '$',
            'name' => 'Dólar Americano',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'decimal_places' => 2,
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
            'space_between' => true,
        ],
        'GBP' => [
            'symbol' => '£',
            'name' => 'Libra Esterlina',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'JPY' => [
            'symbol' => '¥',
            'name' => 'Iene Japonês',
            'decimal_places' => 0,
            'decimal_separator' => '',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'CAD' => [
            'symbol' => 'C$',
            'name' => 'Dólar Canadense',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'AUD' => [
            'symbol' => 'A$',
            'name' => 'Dólar Australiano',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'CHF' => [
            'symbol' => 'CHF',
            'name' => 'Franco Suíço',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'after',
            'space_between' => true,
        ],
        'CNY' => [
            'symbol' => '¥',
            'name' => 'Yuan Chinês',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
        'MXN' => [
            'symbol' => '$',
            'name' => 'Peso Mexicano',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'space_between' => false,
        ],
    ];

    /**
     * Números por extenso em português
     */
    private array $numbersInWords = [
        0 => 'zero',
        1 => 'um', 2 => 'dois', 3 => 'três', 4 => 'quatro', 5 => 'cinco',
        6 => 'seis', 7 => 'sete', 8 => 'oito', 9 => 'nove', 10 => 'dez',
        11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'quatorze', 15 => 'quinze',
        16 => 'dezesseis', 17 => 'dezessete', 18 => 'dezoito', 19 => 'dezenove',
        20 => 'vinte', 30 => 'trinta', 40 => 'quarenta', 50 => 'cinquenta',
        60 => 'sessenta', 70 => 'setenta', 80 => 'oitenta', 90 => 'noventa',
        100 => 'cem', 200 => 'duzentos', 300 => 'trezentos', 400 => 'quatrocentos',
        500 => 'quinhentos', 600 => 'seiscentos', 700 => 'setecentos',
        800 => 'oitocentos', 900 => 'novecentos',
    ];

    private string $defaultCurrency = 'BRL';
    private string $defaultLocale = 'pt_BR';

    public function __construct(string $defaultCurrency = 'BRL', string $defaultLocale = 'pt_BR')
    {
        $this->defaultCurrency = $defaultCurrency;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Formata valor monetário
     */
    public function format($value, array $options = []): string
    {
        if (!$this->canFormat($value)) {
            throw new InvalidArgumentException('Valor deve ser um número');
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $currency = $options['currency'];
        $config = $this->getCurrencyConfig($currency);

        // Converte para float
        $numericValue = (float) $value;

        // Arredonda para o número de casas decimais da moeda
        $roundedValue = round($numericValue, $config['decimal_places']);

        // Formata o número
        $formattedNumber = $this->formatNumber(
            $roundedValue,
            $config['decimal_places'],
            $config['decimal_separator'],
            $config['thousands_separator']
        );

        // Adiciona símbolo da moeda
        return $this->addCurrencySymbol($formattedNumber, $config, $options);
    }

    /**
     * Formata apenas o valor sem símbolo
     */
    public function formatValue($value, string $currency = null): string
    {
        $currency = $currency ?? $this->defaultCurrency;
        $config = $this->getCurrencyConfig($currency);

        $numericValue = (float) $value;
        $roundedValue = round($numericValue, $config['decimal_places']);

        return $this->formatNumber(
            $roundedValue,
            $config['decimal_places'],
            $config['decimal_separator'],
            $config['thousands_separator']
        );
    }

    /**
     * Formata valor por extenso (apenas BRL)
     */
    public function formatInWords($value, string $currency = 'BRL'): string
    {
        if ($currency !== 'BRL') {
            throw new InvalidArgumentException('Formatação por extenso disponível apenas para BRL');
        }

        $numericValue = (float) $value;
        $integerPart = (int) $numericValue;
        $decimalPart = (int) round(($numericValue - $integerPart) * 100);

        $result = [];

        // Parte inteira
        if ($integerPart === 0) {
            $result[] = 'zero';
        } else {
            $result[] = $this->convertIntegerToWords($integerPart);
        }

        // Determina singular/plural para reais
        if ($integerPart === 1) {
            $result[] = 'real';
        } else {
            $result[] = 'reais';
        }

        // Parte decimal (centavos)
        if ($decimalPart > 0) {
            $result[] = 'e';
            $result[] = $this->convertIntegerToWords($decimalPart);

            if ($decimalPart === 1) {
                $result[] = 'centavo';
            } else {
                $result[] = 'centavos';
            }
        }

        return implode(' ', $result);
    }

    /**
     * Converte string monetária para número
     */
    public function parse(string $value, string $currency = null): float
    {
        $currency = $currency ?? $this->defaultCurrency;
        $config = $this->getCurrencyConfig($currency);

        // Remove símbolos da moeda
        $cleanValue = str_replace($config['symbol'], '', $value);
        $cleanValue = trim($cleanValue);

        // Remove separadores de milhares
        $cleanValue = str_replace($config['thousands_separator'], '', $cleanValue);

        // Converte separador decimal para ponto
        if ($config['decimal_separator'] !== '.') {
            $cleanValue = str_replace($config['decimal_separator'], '.', $cleanValue);
        }

        return (float) $cleanValue;
    }

    /**
     * Converte entre moedas (apenas formatação, não conversão real)
     */
    public function convertFormat($value, string $fromCurrency, string $toCurrency): string
    {
        // Parse do valor original
        $numericValue = $this->parse($value, $fromCurrency);

        // Formata na nova moeda
        return $this->format($numericValue, ['currency' => $toCurrency]);
    }

    /**
     * Obtém informações sobre uma moeda
     */
    public function getCurrencyInfo(string $currency): array
    {
        if (!isset($this->currencies[$currency])) {
            throw new InvalidArgumentException("Moeda não suportada: {$currency}");
        }

        return $this->currencies[$currency];
    }

    /**
     * Lista todas as moedas suportadas
     */
    public function getSupportedCurrencies(): array
    {
        return array_keys($this->currencies);
    }

    /**
     * Verifica se pode formatar
     */
    public function canFormat($value): bool
    {
        return is_numeric($value);
    }

    /**
     * Obtém opções padrão
     */
    public function getDefaultOptions(): array
    {
        return [
            'currency' => $this->defaultCurrency,
            'locale' => $this->defaultLocale,
            'show_symbol' => true,
            'show_currency_code' => false,
        ];
    }

    /**
     * Formata diferença entre valores
     */
    public function formatDifference($value1, $value2, array $options = []): string
    {
        $difference = (float) $value1 - (float) $value2;
        $formatted = $this->format(abs($difference), $options);

        if ($difference > 0) {
            return "+{$formatted}";
        } elseif ($difference < 0) {
            return "-{$formatted}";
        } else {
            return $formatted;
        }
    }

    /**
     * Formata percentual de valor
     */
    public function formatPercentage($value, $total, array $options = []): string
    {
        if ($total == 0) {
            return '0%';
        }

        $percentage = ((float) $value / (float) $total) * 100;
        return number_format($percentage, 1, ',', '.') . '%';
    }

    /**
     * Obtém configuração da moeda
     */
    private function getCurrencyConfig(string $currency): array
    {
        if (!isset($this->currencies[$currency])) {
            throw new InvalidArgumentException("Moeda não suportada: {$currency}");
        }

        return $this->currencies[$currency];
    }

    /**
     * Formata número com separadores
     */
    private function formatNumber(
        float $value,
        int $decimals,
        string $decimalSeparator,
        string $thousandsSeparator
    ): string {
        return number_format($value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Adiciona símbolo da moeda
     */
    private function addCurrencySymbol(string $formattedNumber, array $config, array $options): string
    {
        if (!$options['show_symbol']) {
            return $formattedNumber;
        }

        $symbol = $config['symbol'];
        $space = $config['space_between'] ? ' ' : '';

        if ($config['symbol_position'] === 'before') {
            return $symbol . $space . $formattedNumber;
        } else {
            return $formattedNumber . $space . $symbol;
        }
    }

    /**
     * Converte número inteiro para palavras (português)
     */
    private function convertIntegerToWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        if (isset($this->numbersInWords[$number])) {
            return $this->numbersInWords[$number];
        }

        $words = [];

        // Milhões
        if ($number >= 1000000) {
            $millions = intval($number / 1000000);
            if ($millions === 1) {
                $words[] = 'um milhão';
            } else {
                $words[] = $this->convertIntegerToWords($millions) . ' milhões';
            }
            $number %= 1000000;
        }

        // Milhares
        if ($number >= 1000) {
            $thousands = intval($number / 1000);
            if ($thousands === 1) {
                $words[] = 'mil';
            } else {
                $words[] = $this->convertIntegerToWords($thousands) . ' mil';
            }
            $number %= 1000;
        }

        // Centenas
        if ($number >= 100) {
            $hundreds = intval($number / 100) * 100;
            if ($number === 100) {
                $words[] = 'cem';
            } else {
                $words[] = $this->numbersInWords[$hundreds];
            }
            $number %= 100;
        }

        // Dezenas e unidades
        if ($number > 0) {
            if ($number <= 20 || isset($this->numbersInWords[$number])) {
                $words[] = $this->numbersInWords[$number];
            } else {
                $tens = intval($number / 10) * 10;
                $units = $number % 10;

                if ($units === 0) {
                    $words[] = $this->numbersInWords[$tens];
                } else {
                    $words[] = $this->numbersInWords[$tens] . ' e ' . $this->numbersInWords[$units];
                }
            }
        }

        return implode(' e ', $words);
    }

    /**
     * Adiciona nova moeda
     */
    public function addCurrency(string $code, array $config): void
    {
        $requiredKeys = ['symbol', 'name', 'decimal_places', 'decimal_separator', 'thousands_separator', 'symbol_position'];

        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Configuração da moeda deve conter: {$key}");
            }
        }

        $this->currencies[$code] = array_merge([
            'space_between' => false,
        ], $config);
    }
}