<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Formatters;

use InvalidArgumentException;

/**
 * Formatador de documentos brasileiros
 *
 * Implementa formatação completa de documentos brasileiros
 * incluindo CPF, CNPJ, RG, títulos eleitorais e outros
 * documentos oficiais com máscaras e validações.
 *
 * Funcionalidades:
 * - Formatação de CPF (123.456.789-09)
 * - Formatação de CNPJ (12.345.678/0001-90)
 * - Formatação de RG (12.345.678-9)
 * - Formatação de CEP (12345-678)
 * - Formatação de título eleitoral
 * - Mascaramento para privacidade
 * - Detecção automática de tipo
 * - Limpeza de formatação
 *
 * Documentos suportados:
 * - CPF: 11 dígitos
 * - CNPJ: 14 dígitos
 * - RG: 7-9 dígitos
 * - CEP: 8 dígitos
 * - Título eleitoral: 12 dígitos
 * - Carteira de trabalho: variável
 */
class DocumentFormatter implements FormatterInterface
{
    /**
     * Máscaras para formatação
     */
    private array $masks = [
        'cpf' => '###.###.###-##',
        'cnpj' => '##.###.###/####-##',
        'rg' => '#.###.###-#',
        'rg_sp' => '##.###.###-#',
        'cep' => '#####-###',
        'titulo_eleitoral' => '####.####.####',
        'carteira_trabalho' => '#######/##',
        'pis' => '###.#####.##-#',
        'phone_mobile' => '(##) #####-####',
        'phone_landline' => '(##) ####-####',
        'phone_0800' => '#### ### ####',
    ];

    /**
     * Padrões para detecção automática de tipo
     */
    private array $patterns = [
        'cpf' => '/^\d{11}$/',
        'cnpj' => '/^\d{14}$/',
        'rg' => '/^\d{7,9}$/',
        'cep' => '/^\d{8}$/',
        'titulo_eleitoral' => '/^\d{12}$/',
        'carteira_trabalho' => '/^\d{7,11}$/',
        'pis' => '/^\d{11}$/',
    ];

    /**
     * Formata documento
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
            throw new InvalidArgumentException('Tipo de documento não identificado');
        }

        // Verifica se tem máscara para o tipo
        if (!isset($this->masks[$type])) {
            throw new InvalidArgumentException("Tipo de documento não suportado: {$type}");
        }

        return $this->applyMask($cleanValue, $this->masks[$type], $options);
    }

    /**
     * Formata CPF
     */
    public function formatCPF(string $cpf): string
    {
        return $this->format($cpf, ['type' => 'cpf']);
    }

    /**
     * Formata CNPJ
     */
    public function formatCNPJ(string $cnpj): string
    {
        return $this->format($cnpj, ['type' => 'cnpj']);
    }

    /**
     * Formata CEP
     */
    public function formatCEP(string $cep): string
    {
        return $this->format($cep, ['type' => 'cep']);
    }

    /**
     * Formata RG
     */
    public function formatRG(string $rg, string $state = 'sp'): string
    {
        $type = $state === 'sp' ? 'rg_sp' : 'rg';
        return $this->format($rg, ['type' => $type]);
    }

    /**
     * Mascara documento para privacidade
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
            return str_repeat('*', strlen($cleanValue));
        }

        return $this->applyPrivacyMask($cleanValue, $type, $options);
    }

    /**
     * Limpa formatação
     */
    public function clean(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Detecta tipo de documento automaticamente
     */
    public function detectType(string $value): ?string
    {
        $cleanValue = $this->clean($value);

        foreach ($this->patterns as $type => $pattern) {
            if (preg_match($pattern, $cleanValue)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Obtém informações sobre o documento
     */
    public function getDocumentInfo(string $value): array
    {
        $cleanValue = $this->clean($value);
        $type = $this->detectType($cleanValue);

        if (!$type) {
            return ['valid' => false];
        }

        $formatted = $this->format($cleanValue, ['type' => $type]);
        $masked = $this->mask($cleanValue, ['type' => $type]);

        $info = [
            'valid' => true,
            'type' => $type,
            'clean' => $cleanValue,
            'formatted' => $formatted,
            'masked' => $masked,
            'length' => strlen($cleanValue),
        ];

        // Adiciona informações específicas por tipo
        switch ($type) {
            case 'cpf':
                $info['type_name'] = 'CPF';
                $info['is_individual'] = true;
                break;
            case 'cnpj':
                $info['type_name'] = 'CNPJ';
                $info['is_individual'] = false;
                $info['is_matriz'] = substr($cleanValue, 8, 4) === '0001';
                $info['filial_number'] = substr($cleanValue, 8, 4);
                $info['base_number'] = substr($cleanValue, 0, 8);
                break;
            case 'cep':
                $info['type_name'] = 'CEP';
                $info['region'] = $this->getCepRegion($cleanValue);
                break;
        }

        return $info;
    }

    /**
     * Valida se documento está formatado corretamente
     */
    public function isFormatted(string $value, string $type = null): bool
    {
        $type = $type ?? $this->detectType($this->clean($value));

        if (!$type || !isset($this->masks[$type])) {
            return false;
        }

        $mask = $this->masks[$type];
        $pattern = '/^' . str_replace('#', '\d', preg_quote($mask, '/')) . '$/';

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Converte entre formatos
     */
    public function convert(string $value, string $fromType, string $toType): string
    {
        // Limpa o valor original
        $cleanValue = $this->clean($value);

        // Formata no novo tipo
        return $this->format($cleanValue, ['type' => $toType]);
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
            'show_first' => 3,
            'show_last' => 2,
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
     * Adiciona novo tipo de documento
     */
    public function addDocumentType(string $type, string $mask, string $pattern): void
    {
        $this->masks[$type] = $mask;
        $this->patterns[$type] = $pattern;
    }

    /**
     * Formata lista de documentos
     */
    public function formatBatch(array $documents, array $options = []): array
    {
        $results = [];

        foreach ($documents as $key => $document) {
            try {
                $results[$key] = [
                    'original' => $document,
                    'formatted' => $this->format($document, $options),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'original' => $document,
                    'formatted' => null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Aplica máscara ao valor
     */
    private function applyMask(string $value, string $mask, array $options): string
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
        $showFirst = $options['show_first'];
        $showLast = $options['show_last'];

        switch ($type) {
            case 'cpf':
                // Mostra: 123.***.***-09
                $masked = substr($value, 0, 3) . str_repeat($maskChar, 6) . substr($value, -2);
                return $this->applyMask($masked, $this->masks['cpf'], $options);

            case 'cnpj':
                // Mostra: 12.***.***/****-90
                $masked = substr($value, 0, 2) . str_repeat($maskChar, 10) . substr($value, -2);
                return $this->applyMask($masked, $this->masks['cnpj'], $options);

            case 'rg':
                // Mostra: ***.***.**-9
                $masked = str_repeat($maskChar, strlen($value) - 1) . substr($value, -1);
                return $this->applyMask($masked, $this->masks['rg'], $options);

            case 'cep':
                // Mostra: *****-678
                $masked = str_repeat($maskChar, 5) . substr($value, -3);
                return $this->applyMask($masked, $this->masks['cep'], $options);

            default:
                // Genérico: mostra primeiro e últimos caracteres
                if (strlen($value) <= $showFirst + $showLast) {
                    return str_repeat($maskChar, strlen($value));
                }

                $first = substr($value, 0, $showFirst);
                $last = substr($value, -$showLast);
                $middle = str_repeat($maskChar, strlen($value) - $showFirst - $showLast);

                return $first . $middle . $last;
        }
    }

    /**
     * Obtém região pelo CEP
     */
    private function getCepRegion(string $cep): string
    {
        $firstDigit = (int) $cep[0];

        $regions = [
            0 => 'São Paulo - SP (região metropolitana)',
            1 => 'São Paulo - SP (interior)',
            2 => 'Rio de Janeiro - RJ e Espírito Santo - ES',
            3 => 'Minas Gerais - MG',
            4 => 'Bahia - BA e Sergipe - SE',
            5 => 'Paraná - PR e Santa Catarina - SC',
            6 => 'Pernambuco - PE, Rio Grande do Norte - RN, Paraíba - PB e Alagoas - AL',
            7 => 'Ceará - CE e Piauí - PI',
            8 => 'Rio Grande do Sul - RS',
            9 => 'Goiás - GO, Tocantins - TO, Mato Grosso - MT, Mato Grosso do Sul - MS, Rondônia - RO, Acre - AC, Amazonas - AM, Roraima - RR, Pará - PA, Amapá - AP, Maranhão - MA e Distrito Federal - DF',
        ];

        return $regions[$firstDigit] ?? 'Região desconhecida';
    }

    /**
     * Valida estrutura básica do documento
     */
    public function validateStructure(string $value, string $type): bool
    {
        $cleanValue = $this->clean($value);

        if (!isset($this->patterns[$type])) {
            return false;
        }

        return preg_match($this->patterns[$type], $cleanValue) === 1;
    }

    /**
     * Compara dois documentos (ignora formatação)
     */
    public function equals(string $document1, string $document2): bool
    {
        return $this->clean($document1) === $this->clean($document2);
    }
}
