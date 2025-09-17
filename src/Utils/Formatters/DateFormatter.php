<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Formatters;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Formatador de datas
 *
 * Implementa formatação completa de datas e timestamps
 * com suporte a múltiplos formatos, fusos horários e
 * locales, incluindo formatação brasileira e internacional.
 *
 * Funcionalidades:
 * - Formatação brasileira (dd/mm/aaaa)
 * - Formatação ISO (yyyy-mm-dd)
 * - Formatação por extenso
 * - Cálculo de diferenças
 * - Formatação relativa (há 2 horas)
 * - Suporte a fusos horários
 * - Validação de datas
 * - Parsing de strings
 *
 * Formatos suportados:
 * - Brasileiro: 15/03/2024, 15 de março de 2024
 * - Internacional: March 15, 2024, 2024-03-15
 * - Relativo: há 2 horas, em 3 dias
 * - Personalizado: qualquer formato DateTime
 */
class DateFormatter implements FormatterInterface
{
    /**
     * Formatos predefinidos
     */
    private array $formats = [
        'brazilian_short' => 'd/m/Y',
        'brazilian_long' => 'd \d\e F \d\e Y',
        'brazilian_datetime' => 'd/m/Y H:i:s',
        'iso_date' => 'Y-m-d',
        'iso_datetime' => 'Y-m-d H:i:s',
        'iso_full' => 'c',
        'american_short' => 'm/d/Y',
        'american_long' => 'F j, Y',
        'european_short' => 'd.m.Y',
        'time_only' => 'H:i:s',
        'time_short' => 'H:i',
        'mysql_datetime' => 'Y-m-d H:i:s',
        'mysql_date' => 'Y-m-d',
        'timestamp' => 'U',
        'rfc2822' => 'r',
        'atom' => 'c',
    ];

    /**
     * Nomes dos meses em português
     */
    private array $monthNames = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    /**
     * Nomes dos dias da semana em português
     */
    private array $dayNames = [
        0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira',
        3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado',
    ];

    /**
     * Meses abreviados em português
     */
    private array $monthNamesShort = [
        1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr',
        5 => 'mai', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez',
    ];

    /**
     * Dias da semana abreviados em português
     */
    private array $dayNamesShort = [
        0 => 'dom', 1 => 'seg', 2 => 'ter',
        3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sáb',
    ];

    private string $defaultTimezone = 'America/Sao_Paulo';
    private string $defaultLocale = 'pt_BR';

    public function __construct(string $defaultTimezone = 'America/Sao_Paulo', string $defaultLocale = 'pt_BR')
    {
        $this->defaultTimezone = $defaultTimezone;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Formata data
     */
    public function format($value, array $options = []): string
    {
        if (!$this->canFormat($value)) {
            throw new InvalidArgumentException('Valor deve ser uma data válida');
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $dateTime = $this->createDateTime($value, $options['timezone']);

        $format = $options['format'];

        // Se é um formato predefinido, usa a definição
        if (isset($this->formats[$format])) {
            $format = $this->formats[$format];
        }

        // Formata a data
        $formatted = $dateTime->format($format);

        // Aplica localização se necessário
        if ($options['locale'] === 'pt_BR') {
            $formatted = $this->applyPortugueseLocale($formatted, $dateTime);
        }

        return $formatted;
    }

    /**
     * Formata data por extenso
     */
    public function formatLong($value, array $options = []): string
    {
        $options = array_merge($options, ['format' => 'brazilian_long']);
        return $this->format($value, $options);
    }

    /**
     * Formata data relativa (há X tempo)
     */
    public function formatRelative($value, array $options = []): string
    {
        if (!$this->canFormat($value)) {
            throw new InvalidArgumentException('Valor deve ser uma data válida');
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $dateTime = $this->createDateTime($value, $options['timezone']);
        $now = new DateTime('now', new DateTimeZone($options['timezone']));

        $diff = $now->getTimestamp() - $dateTime->getTimestamp();
        $absDiff = abs($diff);

        // Define se é passado ou futuro
        $isFuture = $diff < 0;

        // Calcula intervalos
        $intervals = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        foreach ($intervals as $unit => $seconds) {
            $count = floor($absDiff / $seconds);

            if ($count >= 1) {
                return $this->formatRelativeString($unit, (int) $count, $isFuture);
            }
        }

        return 'agora';
    }

    /**
     * Formata diferença entre datas
     */
    public function formatDifference($date1, $date2, array $options = []): string
    {
        $options = array_merge($this->getDefaultOptions(), $options);

        $dateTime1 = $this->createDateTime($date1, $options['timezone']);
        $dateTime2 = $this->createDateTime($date2, $options['timezone']);

        $diff = $dateTime1->diff($dateTime2);

        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'ano' : 'anos');
        }

        if ($diff->m > 0) {
            $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'mês' : 'meses');
        }

        if ($diff->d > 0) {
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'dia' : 'dias');
        }

        if (empty($parts) && $diff->h > 0) {
            $parts[] = $diff->h . ' ' . ($diff->h === 1 ? 'hora' : 'horas');
        }

        if (empty($parts) && $diff->i > 0) {
            $parts[] = $diff->i . ' ' . ($diff->i === 1 ? 'minuto' : 'minutos');
        }

        if (empty($parts)) {
            $parts[] = $diff->s . ' ' . ($diff->s === 1 ? 'segundo' : 'segundos');
        }

        return implode(', ', array_slice($parts, 0, 2));
    }

    /**
     * Formata período entre datas
     */
    public function formatPeriod($startDate, $endDate, array $options = []): string
    {
        $options = array_merge($this->getDefaultOptions(), $options);

        $start = $this->createDateTime($startDate, $options['timezone']);
        $end = $this->createDateTime($endDate, $options['timezone']);

        $startFormatted = $this->format($start, $options);
        $endFormatted = $this->format($end, $options);

        return "{$startFormatted} até {$endFormatted}";
    }

    /**
     * Formata idade baseada na data de nascimento
     */
    public function formatAge($birthDate, array $options = []): string
    {
        $options = array_merge($this->getDefaultOptions(), $options);

        $birth = $this->createDateTime($birthDate, $options['timezone']);
        $now = new DateTime('now', new DateTimeZone($options['timezone']));

        $age = $birth->diff($now)->y;

        return $age . ' ' . ($age === 1 ? 'ano' : 'anos');
    }

    /**
     * Converte string para DateTime
     */
    public function parse(string $value, string $format = null): DateTime
    {
        if ($format) {
            $dateTime = DateTime::createFromFormat($format, $value);
            if ($dateTime === false) {
                throw new InvalidArgumentException("Não foi possível converter '{$value}' usando o formato '{$format}'");
            }
            return $dateTime;
        }

        // Tenta formatos comuns
        $commonFormats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y',
            'd/m/Y H:i:s',
            'm/d/Y',
            'd.m.Y',
        ];

        foreach ($commonFormats as $format) {
            $dateTime = DateTime::createFromFormat($format, $value);
            if ($dateTime !== false) {
                return $dateTime;
            }
        }

        // Tenta parsing automático
        try {
            return new DateTime($value);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Não foi possível converter '{$value}' para data válida");
        }
    }

    /**
     * Verifica se uma data é válida
     */
    public function isValid($value): bool
    {
        try {
            $this->createDateTime($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém informações sobre a data
     */
    public function getDateInfo($value, array $options = []): array
    {
        if (!$this->canFormat($value)) {
            return ['valid' => false];
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $dateTime = $this->createDateTime($value, $options['timezone']);

        return [
            'valid' => true,
            'formatted' => $this->format($value, $options),
            'iso' => $dateTime->format('c'),
            'timestamp' => $dateTime->getTimestamp(),
            'day_of_week' => (int) $dateTime->format('w'),
            'day_name' => $this->dayNames[(int) $dateTime->format('w')],
            'month' => (int) $dateTime->format('n'),
            'month_name' => $this->monthNames[(int) $dateTime->format('n')],
            'year' => (int) $dateTime->format('Y'),
            'is_weekend' => in_array((int) $dateTime->format('w'), [0, 6]),
            'is_today' => $dateTime->format('Y-m-d') === (new DateTime())->format('Y-m-d'),
            'timezone' => $dateTime->getTimezone()->getName(),
        ];
    }

    /**
     * Verifica se pode formatar
     */
    public function canFormat($value): bool
    {
        if ($value instanceof DateTime) {
            return true;
        }

        if (is_string($value) || is_numeric($value)) {
            try {
                $this->createDateTime($value);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Obtém opções padrão
     */
    public function getDefaultOptions(): array
    {
        return [
            'format' => 'brazilian_short',
            'timezone' => $this->defaultTimezone,
            'locale' => $this->defaultLocale,
        ];
    }

    /**
     * Lista formatos disponíveis
     */
    public function getAvailableFormats(): array
    {
        return $this->formats;
    }

    /**
     * Adiciona formato personalizado
     */
    public function addFormat(string $name, string $format): void
    {
        $this->formats[$name] = $format;
    }

    /**
     * Cria DateTime a partir de valor
     */
    private function createDateTime($value, string $timezone = null): DateTime
    {
        $timezone = $timezone ?? $this->defaultTimezone;
        $tz = new DateTimeZone($timezone);

        if ($value instanceof DateTime) {
            return $value->setTimezone($tz);
        }

        if (is_numeric($value)) {
            return (new DateTime('@' . $value))->setTimezone($tz);
        }

        if (is_string($value)) {
            return new DateTime($value, $tz);
        }

        throw new InvalidArgumentException('Tipo de data não suportado');
    }

    /**
     * Aplica localização portuguesa
     */
    private function applyPortugueseLocale(string $formatted, DateTime $dateTime): string
    {
        // Substitui nomes de meses
        foreach ($this->monthNames as $monthNum => $monthName) {
            $englishMonth = $dateTime->format('F');
            if ($dateTime->format('n') == $monthNum) {
                $formatted = str_replace($englishMonth, $monthName, $formatted);
                $formatted = str_replace(ucfirst($englishMonth), ucfirst($monthName), $formatted);
            }
        }

        // Substitui nomes de dias
        foreach ($this->dayNames as $dayNum => $dayName) {
            $englishDay = $dateTime->format('l');
            if ($dateTime->format('w') == $dayNum) {
                $formatted = str_replace($englishDay, $dayName, $formatted);
                $formatted = str_replace(ucfirst($englishDay), ucfirst($dayName), $formatted);
            }
        }

        return $formatted;
    }

    /**
     * Formata string relativa
     */
    private function formatRelativeString(string $unit, int $count, bool $isFuture): string
    {
        $units = [
            'year' => ['ano', 'anos'],
            'month' => ['mês', 'meses'],
            'week' => ['semana', 'semanas'],
            'day' => ['dia', 'dias'],
            'hour' => ['hora', 'horas'],
            'minute' => ['minuto', 'minutos'],
            'second' => ['segundo', 'segundos'],
        ];

        $unitName = $units[$unit][$count === 1 ? 0 : 1];

        if ($isFuture) {
            return "em {$count} {$unitName}";
        } else {
            return "há {$count} {$unitName}";
        }
    }

    /**
     * Converte timezone
     */
    public function convertTimezone($value, string $fromTimezone, string $toTimezone): DateTime
    {
        $dateTime = $this->createDateTime($value, $fromTimezone);
        return $dateTime->setTimezone(new DateTimeZone($toTimezone));
    }

    /**
     * Obtém início do dia
     */
    public function startOfDay($value, array $options = []): DateTime
    {
        $options = array_merge($this->getDefaultOptions(), $options);
        $dateTime = $this->createDateTime($value, $options['timezone']);
        return $dateTime->setTime(0, 0, 0);
    }

    /**
     * Obtém fim do dia
     */
    public function endOfDay($value, array $options = []): DateTime
    {
        $options = array_merge($this->getDefaultOptions(), $options);
        $dateTime = $this->createDateTime($value, $options['timezone']);
        return $dateTime->setTime(23, 59, 59);
    }
}