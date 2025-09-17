<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Validators;

/**
 * Validador de telefones brasileiros
 *
 * Implementa validação completa de números de telefone
 * do Brasil, incluindo celulares, fixos, 0800, e números
 * especiais, seguindo as regras da ANATEL.
 *
 * Funcionalidades:
 * - Validação de celulares (9 dígitos + DDD)
 * - Validação de telefones fixos (8 dígitos + DDD)
 * - Suporte a números 0800, 0300, 4004, etc.
 * - Detecção automática de tipo de telefone
 * - Formatação automática brasileira
 * - Validação de DDDs válidos
 * - Suporte a números internacionais básicos
 *
 * Formatos suportados:
 * - (11) 99999-9999 (celular)
 * - (11) 3333-3333 (fixo)
 * - 0800 123 4567 (gratuito)
 * - +55 11 99999-9999 (internacional)
 *
 * Regras ANATEL:
 * - Celular: DDD + 9 + 8 dígitos
 * - Fixo: DDD + 7-8 dígitos (dependendo da região)
 * - DDDs válidos: 11-99 (conforme mapeamento oficial)
 */
class PhoneValidator implements ValidatorInterface
{
    private string $lastErrorMessage = '';

    /**
     * DDDs válidos no Brasil (conforme ANATEL)
     */
    private array $validDDDs = [
        // São Paulo
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        // Rio de Janeiro e Espírito Santo
        21, 22, 24, 27, 28,
        // Minas Gerais
        31, 32, 33, 34, 35, 37, 38,
        // Bahia e Sergipe
        71, 73, 74, 75, 77, 79,
        // Pernambuco, Alagoas, Paraíba e Rio Grande do Norte
        81, 82, 83, 84, 87,
        // Ceará, Piauí e Maranhão
        85, 86, 88, 89, 98, 99,
        // Distrito Federal, Goiás, Tocantins, Mato Grosso e Mato Grosso do Sul
        61, 62, 63, 64, 65, 66, 67,
        // Paraná e Santa Catarina
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        // Rio Grande do Sul
        51, 53, 54, 55,
        // Acre, Rondônia, Amazonas, Roraima, Pará e Amapá
        68, 69, 92, 95, 91, 93, 94, 96, 97,
    ];

    /**
     * Prefixos especiais válidos
     */
    private array $specialPrefixes = [
        '0800', // Ligação gratuita
        '0300', // Custo compartilhado
        '4004', // Não gratuita
        '4020', // Não gratuita
        '2222', // Telemarketing
        '3003', // Custo compartilhado
        '1056', // Telecomunicações
        '1057', // Telecomunicações
    ];

    /**
     * Configurações do validador
     */
    private array $config = [
        'allow_international' => true,
        'allow_special_numbers' => true,
        'require_area_code' => true,
        'strict_mobile_format' => true,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Valida telefone
     */
    public function validate($value): bool
    {
        $result = $this->validateDetailed($value);
        return $result['valid'];
    }

    /**
     * Obtém mensagem de erro
     */
    public function getErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Validação detalhada
     */
    public function validateDetailed($value): array
    {
        $this->lastErrorMessage = '';

        // Verificação básica de tipo
        if (!is_string($value) && !is_numeric($value)) {
            return $this->setError('Telefone deve ser uma string ou número');
        }

        // Converte para string e limpa
        $phone = $this->clean((string) $value);

        // Verifica se não está vazio
        if (empty($phone)) {
            return $this->setError('Telefone não pode estar vazio');
        }

        // Verifica comprimento mínimo e máximo
        if (strlen($phone) < 8 || strlen($phone) > 14) {
            return $this->setError('Telefone deve ter entre 8 e 14 dígitos');
        }

        // Detecta e valida tipo de telefone
        $phoneType = $this->detectPhoneType($phone);

        switch ($phoneType) {
            case 'mobile':
                return $this->validateMobile($phone);
            case 'landline':
                return $this->validateLandline($phone);
            case 'special':
                return $this->validateSpecial($phone);
            case 'international':
                return $this->validateInternational($phone);
            default:
                return $this->setError('Formato de telefone não reconhecido');
        }
    }

    /**
     * Limpa formatação do telefone
     */
    public function clean(string $phone): string
    {
        // Remove tudo exceto números e o sinal +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Se começa com +55, remove para processar como número brasileiro
        if (str_starts_with($cleaned, '+55')) {
            $cleaned = substr($cleaned, 3);
        }

        return $cleaned;
    }

    /**
     * Formata telefone brasileiro
     */
    public function format(string $phone): string
    {
        $cleanPhone = $this->clean($phone);
        $type = $this->detectPhoneType($cleanPhone);

        switch ($type) {
            case 'mobile':
                return $this->formatMobile($cleanPhone);
            case 'landline':
                return $this->formatLandline($cleanPhone);
            case 'special':
                return $this->formatSpecial($cleanPhone);
            default:
                return $phone; // Retorna original se não conseguir formatar
        }
    }

    /**
     * Detecta tipo do telefone
     */
    public function detectPhoneType(string $phone): string
    {
        $cleanPhone = $this->clean($phone);

        // Número internacional
        if (str_starts_with($phone, '+') && !str_starts_with($phone, '+55')) {
            return 'international';
        }

        // Números especiais
        foreach ($this->specialPrefixes as $prefix) {
            if (str_starts_with($cleanPhone, $prefix)) {
                return 'special';
            }
        }

        // Com DDD (11 dígitos = celular, 10 dígitos = fixo)
        if (strlen($cleanPhone) === 11) {
            $ddd = (int) substr($cleanPhone, 0, 2);
            if (in_array($ddd, $this->validDDDs) && $cleanPhone[2] === '9') {
                return 'mobile';
            }
        }

        if (strlen($cleanPhone) === 10) {
            $ddd = (int) substr($cleanPhone, 0, 2);
            if (in_array($ddd, $this->validDDDs)) {
                return 'landline';
            }
        }

        // Sem DDD (9 dígitos = celular, 8 dígitos = fixo)
        if (strlen($cleanPhone) === 9 && $cleanPhone[0] === '9') {
            return 'mobile';
        }

        if (strlen($cleanPhone) === 8) {
            return 'landline';
        }

        return 'unknown';
    }

    /**
     * Obtém informações sobre o telefone
     */
    public function getPhoneInfo(string $phone): array
    {
        if (!$this->validate($phone)) {
            return ['valid' => false];
        }

        $cleanPhone = $this->clean($phone);
        $type = $this->detectPhoneType($cleanPhone);

        $info = [
            'valid' => true,
            'clean' => $cleanPhone,
            'formatted' => $this->format($cleanPhone),
            'type' => $type,
        ];

        // Adiciona informações específicas por tipo
        switch ($type) {
            case 'mobile':
            case 'landline':
                if (strlen($cleanPhone) >= 10) {
                    $info['area_code'] = substr($cleanPhone, 0, 2);
                    $info['number'] = substr($cleanPhone, 2);
                    $info['region'] = $this->getRegionByAreaCode((int) $info['area_code']);
                }
                break;
            case 'special':
                $info['service_type'] = $this->getSpecialServiceType($cleanPhone);
                break;
        }

        return $info;
    }

    /**
     * Valida celular
     */
    private function validateMobile(string $phone): bool
    {
        // Celular com DDD (11 dígitos)
        if (strlen($phone) === 11) {
            $ddd = (int) substr($phone, 0, 2);
            if (!in_array($ddd, $this->validDDDs)) {
                return $this->setError('DDD inválido para celular');
            }

            if ($this->config['strict_mobile_format'] && $phone[2] !== '9') {
                return $this->setError('Celular deve começar com 9 após o DDD');
            }

            return ['valid' => true, 'message' => ''];
        }

        // Celular sem DDD (9 dígitos)
        if (strlen($phone) === 9) {
            if ($this->config['require_area_code']) {
                return $this->setError('DDD é obrigatório para celulares');
            }

            if ($this->config['strict_mobile_format'] && $phone[0] !== '9') {
                return $this->setError('Celular deve começar com 9');
            }

            return ['valid' => true, 'message' => ''];
        }

        return $this->setError('Celular deve ter 9 dígitos (sem DDD) ou 11 dígitos (com DDD)');
    }

    /**
     * Valida telefone fixo
     */
    private function validateLandline(string $phone): bool
    {
        // Fixo com DDD (10 dígitos)
        if (strlen($phone) === 10) {
            $ddd = (int) substr($phone, 0, 2);
            if (!in_array($ddd, $this->validDDDs)) {
                return $this->setError('DDD inválido para telefone fixo');
            }

            return ['valid' => true, 'message' => ''];
        }

        // Fixo sem DDD (8 dígitos)
        if (strlen($phone) === 8) {
            if ($this->config['require_area_code']) {
                return $this->setError('DDD é obrigatório para telefones fixos');
            }

            return ['valid' => true, 'message' => ''];
        }

        return $this->setError('Telefone fixo deve ter 8 dígitos (sem DDD) ou 10 dígitos (com DDD)');
    }

    /**
     * Valida números especiais
     */
    private function validateSpecial(string $phone): bool
    {
        if (!$this->config['allow_special_numbers']) {
            return $this->setError('Números especiais não são permitidos');
        }

        // Verifica se começa com prefixo válido
        foreach ($this->specialPrefixes as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                // Valida comprimento específico por tipo
                $expectedLength = $this->getExpectedLengthForPrefix($prefix);
                if (strlen($phone) === $expectedLength) {
                    return ['valid' => true, 'message' => ''];
                }
            }
        }

        return $this->setError('Número especial inválido');
    }

    /**
     * Valida números internacionais
     */
    private function validateInternational(string $phone): bool
    {
        if (!$this->config['allow_international']) {
            return $this->setError('Números internacionais não são permitidos');
        }

        // Validação básica para números internacionais
        if (strlen($phone) >= 8 && strlen($phone) <= 15) {
            return ['valid' => true, 'message' => ''];
        }

        return $this->setError('Número internacional inválido');
    }

    /**
     * Formata celular
     */
    private function formatMobile(string $phone): string
    {
        if (strlen($phone) === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        }

        if (strlen($phone) === 9) {
            return substr($phone, 0, 5) . '-' . substr($phone, 5);
        }

        return $phone;
    }

    /**
     * Formata telefone fixo
     */
    private function formatLandline(string $phone): string
    {
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }

        if (strlen($phone) === 8) {
            return substr($phone, 0, 4) . '-' . substr($phone, 4);
        }

        return $phone;
    }

    /**
     * Formata números especiais
     */
    private function formatSpecial(string $phone): string
    {
        if (str_starts_with($phone, '0800') && strlen($phone) === 11) {
            return '0800 ' . substr($phone, 4, 3) . ' ' . substr($phone, 7);
        }

        return $phone;
    }

    /**
     * Obtém região pelo DDD
     */
    private function getRegionByAreaCode(int $areaCode): string
    {
        $regions = [
            11 => 'São Paulo - SP',
            12 => 'São José dos Campos - SP',
            13 => 'Santos - SP',
            14 => 'Bauru - SP',
            15 => 'Sorocaba - SP',
            16 => 'Ribeirão Preto - SP',
            17 => 'São José do Rio Preto - SP',
            18 => 'Presidente Prudente - SP',
            19 => 'Campinas - SP',
            21 => 'Rio de Janeiro - RJ',
            22 => 'Campos dos Goytacazes - RJ',
            24 => 'Petrópolis - RJ',
            27 => 'Vitória - ES',
            28 => 'Cachoeiro de Itapemirim - ES',
            31 => 'Belo Horizonte - MG',
            32 => 'Juiz de Fora - MG',
            33 => 'Governador Valadares - MG',
            34 => 'Uberlândia - MG',
            35 => 'Poços de Caldas - MG',
            37 => 'Divinópolis - MG',
            38 => 'Montes Claros - MG',
            // ... mais regiões
        ];

        return $regions[$areaCode] ?? 'Região desconhecida';
    }

    /**
     * Obtém tipo de serviço para números especiais
     */
    private function getSpecialServiceType(string $phone): string
    {
        $types = [
            '0800' => 'Ligação gratuita',
            '0300' => 'Custo compartilhado',
            '4004' => 'Número não gratuito',
            '4020' => 'Número não gratuito',
            '2222' => 'Telemarketing',
            '3003' => 'Custo compartilhado',
        ];

        foreach ($types as $prefix => $type) {
            if (str_starts_with($phone, $prefix)) {
                return $type;
            }
        }

        return 'Serviço especial';
    }

    /**
     * Obtém comprimento esperado por prefixo
     */
    private function getExpectedLengthForPrefix(string $prefix): int
    {
        $lengths = [
            '0800' => 11,
            '0300' => 11,
            '4004' => 8,
            '4020' => 8,
            '2222' => 8,
            '3003' => 8,
        ];

        return $lengths[$prefix] ?? 8;
    }

    /**
     * Define erro e retorna resultado
     */
    private function setError(string $message): array
    {
        $this->lastErrorMessage = $message;
        return ['valid' => false, 'message' => $message];
    }

    /**
     * Verifica se dois telefones são iguais (ignora formatação)
     */
    public function equals(string $phone1, string $phone2): bool
    {
        return $this->clean($phone1) === $this->clean($phone2);
    }

    /**
     * Converte para formato internacional
     */
    public function toInternational(string $phone): string
    {
        $cleanPhone = $this->clean($phone);
        $type = $this->detectPhoneType($cleanPhone);

        if ($type === 'mobile' || $type === 'landline') {
            // Adiciona código do país (+55) se necessário
            if (!str_starts_with($cleanPhone, '55')) {
                return '+55' . $cleanPhone;
            }
        }

        return $phone;
    }
}