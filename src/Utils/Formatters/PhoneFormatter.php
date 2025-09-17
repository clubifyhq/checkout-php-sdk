<?php

declare(strict_types=1);

namespace ClubifyCheckout\Utils\Formatters;

use InvalidArgumentException;

/**
 * Formatador de telefones brasileiros
 *
 * Implementa formatação completa de números de telefone
 * do Brasil, incluindo celulares, fixos, 0800 e números
 * especiais, com máscaras apropriadas por tipo.
 *
 * Funcionalidades:
 * - Formatação de celulares (11) 99999-9999
 * - Formatação de fixos (11) 3333-3333
 * - Formatação 0800 (0800 123 4567)
 * - Formatação internacional (+55 11 99999-9999)
 * - Detecção automática de tipo
 * - Mascaramento para privacidade
 * - Conversão entre formatos
 * - Validação de DDDs
 *
 * Tipos suportados:
 * - mobile: Celular com 9º dígito
 * - landline: Telefone fixo
 * - toll_free: 0800, 0300, etc.
 * - premium: 0900, etc.
 * - international: +55 11 99999-9999
 */
class PhoneFormatter implements FormatterInterface
{
    /**
     * Máscaras por tipo de telefone
     */
    private array $masks = [
        'mobile' => '(##) #####-####',
        'landline' => '(##) ####-####',
        'toll_free' => '#### ### ####',
        'premium' => '#### ### ####',
        'special' => '#### ####',
        'international' => '+## ## #####-####',
        'mobile_short' => '#####-####',
        'landline_short' => '####-####',
    ];

    /**
     * DDDs válidos por região
     */
    private array $validDDDs = [
        11 => 'São Paulo - SP (capital)',
        12 => 'São Paulo - SP (Vale do Paraíba)',
        13 => 'São Paulo - SP (Baixada Santista)',
        14 => 'São Paulo - SP (Bauru)',
        15 => 'São Paulo - SP (Sorocaba)',
        16 => 'São Paulo - SP (Ribeirão Preto)',
        17 => 'São Paulo - SP (São José do Rio Preto)',
        18 => 'São Paulo - SP (Presidente Prudente)',
        19 => 'São Paulo - SP (Campinas)',
        21 => 'Rio de Janeiro - RJ',
        22 => 'Rio de Janeiro - RJ (Norte Fluminense)',
        24 => 'Rio de Janeiro - RJ (Região Serrana)',
        27 => 'Espírito Santo - ES',
        28 => 'Espírito Santo - ES (Sul)',
        31 => 'Minas Gerais - MG (Belo Horizonte)',
        32 => 'Minas Gerais - MG (Juiz de Fora)',
        33 => 'Minas Gerais - MG (Governador Valadares)',
        34 => 'Minas Gerais - MG (Uberlândia)',
        35 => 'Minas Gerais - MG (Poços de Caldas)',
        37 => 'Minas Gerais - MG (Divinópolis)',
        38 => 'Minas Gerais - MG (Montes Claros)',
        41 => 'Paraná - PR (Curitiba)',
        42 => 'Paraná - PR (Ponta Grossa)',
        43 => 'Paraná - PR (Londrina)',
        44 => 'Paraná - PR (Maringá)',
        45 => 'Paraná - PR (Cascavel)',
        46 => 'Paraná - PR (Francisco Beltrão)',
        47 => 'Santa Catarina - SC (Norte)',
        48 => 'Santa Catarina - SC (Grande Florianópolis)',
        49 => 'Santa Catarina - SC (Oeste)',
        51 => 'Rio Grande do Sul - RS (Porto Alegre)',
        53 => 'Rio Grande do Sul - RS (Pelotas)',
        54 => 'Rio Grande do Sul - RS (Caxias do Sul)',
        55 => 'Rio Grande do Sul - RS (Santa Maria)',
        61 => 'Distrito Federal - DF e Goiás - GO',
        62 => 'Goiás - GO',
        63 => 'Tocantins - TO',
        64 => 'Goiás - GO (Sudoeste)',
        65 => 'Mato Grosso - MT',
        66 => 'Mato Grosso - MT (Norte)',
        67 => 'Mato Grosso do Sul - MS',
        68 => 'Acre - AC',
        69 => 'Rondônia - RO',
        71 => 'Bahia - BA (Salvador)',
        73 => 'Bahia - BA (Sul)',
        74 => 'Bahia - BA (Juazeiro)',
        75 => 'Bahia - BA (Feira de Santana)',
        77 => 'Bahia - BA (Vitória da Conquista)',
        79 => 'Sergipe - SE',
        81 => 'Pernambuco - PE',
        82 => 'Alagoas - AL',
        83 => 'Paraíba - PB',
        84 => 'Rio Grande do Norte - RN',
        85 => 'Ceará - CE',
        86 => 'Piauí - PI',
        87 => 'Pernambuco - PE (Interior)',
        88 => 'Ceará - CE (Interior)',
        89 => 'Piauí - PI (Interior)',
        91 => 'Pará - PA (Belém)',
        92 => 'Amazonas - AM',
        93 => 'Pará - PA (Santarém)',
        94 => 'Pará - PA (Marabá)',
        95 => 'Roraima - RR',
        96 => 'Amapá - AP',
        97 => 'Amazonas - AM (Interior)',
        98 => 'Maranhão - MA',
        99 => 'Maranhão - MA (Interior)',
    ];

    /**
     * Prefixos especiais
     */
    private array $specialPrefixes = [
        '0800' => 'Ligação gratuita',
        '0300' => 'Custo compartilhado',
        '0500' => 'Ligação a cobrar',
        '0900' => 'Ligação premium',
        '4004' => 'Não gratuita',
        '4020' => 'Não gratuita',
        '2222' => 'Telemarketing',
        '3003' => 'Custo compartilhado',
        '1056' => 'Telecomunicações',
        '1057' => 'Telecomunicações',
    ];

    /**
     * Formata telefone
     */
    public function format($value, array $options = []): string
    {
        if (!$this->canFormat($value)) {
            throw new InvalidArgumentException('Valor deve ser uma string ou número');
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $cleanValue = $this->clean((string) $value);

        // Detecta tipo automaticamente se não especificado
        $type = $options['type'] ?? $this->detectType($cleanValue);

        if (!$type) {
            throw new InvalidArgumentException('Tipo de telefone não identificado');
        }

        return $this->applyFormatByType($cleanValue, $type, $options);
    }

    /**
     * Formata celular
     */
    public function formatMobile(string $phone, bool $includeDDD = true): string
    {
        $cleanPhone = $this->clean($phone);
        $type = $includeDDD && strlen($cleanPhone) === 11 ? 'mobile' : 'mobile_short';
        return $this->format($cleanPhone, ['type' => $type]);
    }

    /**
     * Formata telefone fixo
     */
    public function formatLandline(string $phone, bool $includeDDD = true): string
    {
        $cleanPhone = $this->clean($phone);
        $type = $includeDDD && strlen($cleanPhone) === 10 ? 'landline' : 'landline_short';
        return $this->format($cleanPhone, ['type' => $type]);
    }

    /**
     * Formata número internacional
     */
    public function formatInternational(string $phone): string
    {
        $cleanPhone = $this->clean($phone);

        // Se já tem código do país, formata diretamente
        if (str_starts_with($cleanPhone, '55') && strlen($cleanPhone) >= 12) {
            return $this->format($cleanPhone, ['type' => 'international']);
        }

        // Adiciona código do Brasil se necessário
        if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 11) {
            $cleanPhone = '55' . $cleanPhone;
            return $this->format($cleanPhone, ['type' => 'international']);
        }

        throw new InvalidArgumentException('Número inválido para formatação internacional');
    }

    /**
     * Mascara telefone para privacidade
     */
    public function mask($value, array $options = []): string
    {
        if (!$this->canFormat($value)) {
            throw new InvalidArgumentException('Valor deve ser uma string ou número');
        }

        $options = array_merge($this->getDefaultOptions(), $options);
        $cleanValue = $this->clean((string) $value);
        $type = $options['type'] ?? $this->detectType($cleanValue);

        if (!$type) {
            return str_repeat($options['mask_char'], strlen($cleanValue));
        }

        return $this->applyPrivacyMask($cleanValue, $type, $options);
    }

    /**
     * Limpa formatação
     */
    public function clean(string $value): string
    {
        // Remove tudo exceto números e o sinal +
        $cleaned = preg_replace('/[^\d+]/', '', $value);

        // Se começa com +55, remove para processar como número brasileiro
        if (str_starts_with($cleaned, '+55')) {
            $cleaned = substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '55') && strlen($cleaned) > 11) {
            $cleaned = substr($cleaned, 2);
        }

        return $cleaned;
    }

    /**
     * Detecta tipo do telefone
     */
    public function detectType(string $value): ?string
    {
        $cleanPhone = $this->clean($value);

        // Números especiais
        foreach ($this->specialPrefixes as $prefix => $description) {
            if (str_starts_with($cleanPhone, $prefix)) {
                return in_array($prefix, ['0800', '0300', '0500']) ? 'toll_free' : 'premium';
            }
        }

        // Internacional (com código do país)
        if (strlen($cleanPhone) > 11) {
            return 'international';
        }

        // Com DDD
        if (strlen($cleanPhone) === 11) {
            $ddd = (int) substr($cleanPhone, 0, 2);
            if (isset($this->validDDDs[$ddd]) && $cleanPhone[2] === '9') {
                return 'mobile';
            }
        }

        if (strlen($cleanPhone) === 10) {
            $ddd = (int) substr($cleanPhone, 0, 2);
            if (isset($this->validDDDs[$ddd])) {
                return 'landline';
            }
        }

        // Sem DDD
        if (strlen($cleanPhone) === 9 && $cleanPhone[0] === '9') {
            return 'mobile_short';
        }

        if (strlen($cleanPhone) === 8) {
            return 'landline_short';
        }

        return null;
    }

    /**
     * Obtém informações sobre o telefone
     */
    public function getPhoneInfo(string $phone): array
    {
        $cleanPhone = $this->clean($phone);
        $type = $this->detectType($cleanPhone);

        if (!$type) {
            return ['valid' => false];
        }

        $info = [
            'valid' => true,
            'clean' => $cleanPhone,
            'formatted' => $this->format($cleanPhone, ['type' => $type]),
            'masked' => $this->mask($cleanPhone, ['type' => $type]),
            'type' => $type,
            'type_name' => $this->getTypeName($type),
        ];

        // Adiciona informações específicas por tipo
        if (in_array($type, ['mobile', 'landline']) && strlen($cleanPhone) >= 10) {
            $ddd = (int) substr($cleanPhone, 0, 2);
            $info['area_code'] = $ddd;
            $info['area_name'] = $this->validDDDs[$ddd] ?? 'DDD inválido';
            $info['number'] = substr($cleanPhone, 2);
        }

        if (in_array($type, ['toll_free', 'premium'])) {
            $prefix = substr($cleanPhone, 0, 4);
            $info['service_type'] = $this->specialPrefixes[$prefix] ?? 'Serviço especial';
        }

        return $info;
    }

    /**
     * Converte para formato internacional
     */
    public function toInternational(string $phone): string
    {
        $cleanPhone = $this->clean($phone);
        $type = $this->detectType($cleanPhone);

        if (in_array($type, ['mobile', 'landline'])) {
            return '+55 ' . $this->format($cleanPhone, ['type' => $type]);
        }

        return $phone;
    }

    /**
     * Converte para formato nacional
     */
    public function toNational(string $phone): string
    {
        $cleanPhone = $this->clean($phone);

        // Remove código do país se presente
        if (str_starts_with($cleanPhone, '55') && strlen($cleanPhone) > 11) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        return $this->format($cleanPhone);
    }

    /**
     * Verifica se pode formatar
     */
    public function canFormat($value): bool
    {
        return is_string($value) || is_numeric($value);
    }

    /**
     * Obtém opções padrão
     */
    public function getDefaultOptions(): array
    {
        return [
            'type' => null,
            'mask_char' => '*',
            'show_area_code' => true,
            'international_format' => false,
        ];
    }

    /**
     * Lista tipos suportados
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->masks);
    }

    /**
     * Lista DDDs válidos
     */
    public function getValidDDDs(): array
    {
        return $this->validDDDs;
    }

    /**
     * Verifica se DDD é válido
     */
    public function isValidDDD(int $ddd): bool
    {
        return isset($this->validDDDs[$ddd]);
    }

    /**
     * Obtém região pelo DDD
     */
    public function getRegionByDDD(int $ddd): ?string
    {
        return $this->validDDDs[$ddd] ?? null;
    }

    /**
     * Aplica formatação por tipo
     */
    private function applyFormatByType(string $value, string $type, array $options): string
    {
        if (!isset($this->masks[$type])) {
            throw new InvalidArgumentException("Tipo não suportado: {$type}");
        }

        $mask = $this->masks[$type];

        // Para números internacionais, ajusta a máscara
        if ($type === 'international' && strlen($value) > 13) {
            $mask = '+## ## #####-####';
        }

        return $this->applyMask($value, $mask);
    }

    /**
     * Aplica máscara ao valor
     */
    private function applyMask(string $value, string $mask): string
    {
        $formatted = '';
        $valueIndex = 0;

        for ($i = 0; $i < strlen($mask); $i++) {
            if ($mask[$i] === '#') {
                if ($valueIndex < strlen($value)) {
                    $formatted .= $value[$valueIndex];
                    $valueIndex++;
                }
            } else {
                $formatted .= $mask[$i];
            }
        }

        return $formatted;
    }

    /**
     * Aplica máscara de privacidade
     */
    private function applyPrivacyMask(string $value, string $type, array $options): string
    {
        $maskChar = $options['mask_char'];

        switch ($type) {
            case 'mobile':
                // (11) 9****-1234
                if (strlen($value) === 11) {
                    $masked = substr($value, 0, 3) . str_repeat($maskChar, 4) . substr($value, -4);
                    return $this->applyMask($masked, $this->masks['mobile']);
                }
                break;

            case 'landline':
                // (11) ****-1234
                if (strlen($value) === 10) {
                    $masked = substr($value, 0, 2) . str_repeat($maskChar, 4) . substr($value, -4);
                    return $this->applyMask($masked, $this->masks['landline']);
                }
                break;

            case 'toll_free':
            case 'premium':
                // 0800 *** 1234
                $masked = substr($value, 0, 4) . str_repeat($maskChar, 3) . substr($value, -4);
                return $this->applyMask($masked, $this->masks['toll_free']);

            default:
                // Genérico: mostra primeiros 2 e últimos 4
                if (strlen($value) > 6) {
                    $first = substr($value, 0, 2);
                    $last = substr($value, -4);
                    $middle = str_repeat($maskChar, strlen($value) - 6);
                    return $first . $middle . $last;
                }
        }

        // Fallback: mascara tudo exceto últimos 4 dígitos
        if (strlen($value) > 4) {
            return str_repeat($maskChar, strlen($value) - 4) . substr($value, -4);
        }

        return str_repeat($maskChar, strlen($value));
    }

    /**
     * Obtém nome do tipo
     */
    private function getTypeName(string $type): string
    {
        $names = [
            'mobile' => 'Celular',
            'landline' => 'Telefone fixo',
            'toll_free' => 'Ligação gratuita',
            'premium' => 'Ligação premium',
            'special' => 'Número especial',
            'international' => 'Internacional',
            'mobile_short' => 'Celular (sem DDD)',
            'landline_short' => 'Fixo (sem DDD)',
        ];

        return $names[$type] ?? 'Desconhecido';
    }

    /**
     * Compara dois telefones (ignora formatação)
     */
    public function equals(string $phone1, string $phone2): bool
    {
        return $this->clean($phone1) === $this->clean($phone2);
    }

    /**
     * Formata lista de telefones
     */
    public function formatBatch(array $phones, array $options = []): array
    {
        $results = [];

        foreach ($phones as $key => $phone) {
            try {
                $results[$key] = [
                    'original' => $phone,
                    'formatted' => $this->format($phone, $options),
                    'info' => $this->getPhoneInfo($phone),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'original' => $phone,
                    'formatted' => null,
                    'info' => null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}