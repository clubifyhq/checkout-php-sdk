<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Validators;

/**
 * Validador de CNPJ (Cadastro Nacional da Pessoa Jurídica)
 *
 * Implementa validação completa de CNPJ seguindo o algoritmo
 * oficial da Receita Federal do Brasil, incluindo verificação
 * de dígitos verificadores e detecção de CNPJs inválidos conhecidos.
 *
 * Funcionalidades:
 * - Validação do algoritmo oficial dos dígitos verificadores
 * - Detecção de CNPJs com sequências inválidas
 * - Formatação e normalização automática
 * - Suporte a CNPJ com ou sem formatação
 * - Geração de CNPJs válidos para testes
 * - Identificação de matriz vs filial
 *
 * Algoritmo oficial:
 * 1. Calcula primeiro dígito verificador usando pesos específicos
 * 2. Calcula segundo dígito verificador usando pesos específicos
 * 3. Verifica se os dígitos coincidem
 *
 * Estrutura do CNPJ:
 * XX.XXX.XXX/YYYY-ZZ
 * - XX.XXX.XXX: Número base da empresa
 * - YYYY: Número da filial (0001 = matriz)
 * - ZZ: Dígitos verificadores
 *
 * Exemplos de uso:
 * - $validator = new CNPJValidator();
 * - $validator->validate('11.222.333/0001-81'); // true/false
 * - $validator->format('11222333000181'); // 11.222.333/0001-81
 * - $validator->isMatriz('11222333000181'); // true se for matriz
 */
class CNPJValidator implements ValidatorInterface
{
    private string $lastErrorMessage = '';

    /**
     * Lista de CNPJs conhecidos como inválidos (sequências)
     */
    private array $invalidSequences = [
        '00000000000000',
        '11111111111111',
        '22222222222222',
        '33333333333333',
        '44444444444444',
        '55555555555555',
        '66666666666666',
        '77777777777777',
        '88888888888888',
        '99999999999999',
    ];

    /**
     * Pesos para cálculo do primeiro dígito verificador
     */
    private array $firstDigitWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Pesos para cálculo do segundo dígito verificador
     */
    private array $secondDigitWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Valida CNPJ
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
            return $this->setError('CNPJ deve ser uma string ou número');
        }

        // Converte para string e limpa
        $cnpj = $this->clean((string) $value);

        // Verifica se tem 14 dígitos
        if (strlen($cnpj) !== 14) {
            return $this->setError('CNPJ deve conter exatamente 14 dígitos');
        }

        // Verifica se são todos números
        if (!ctype_digit($cnpj)) {
            return $this->setError('CNPJ deve conter apenas números');
        }

        // Verifica sequências inválidas
        if (in_array($cnpj, $this->invalidSequences)) {
            return $this->setError('CNPJ não pode ser uma sequência de números iguais');
        }

        // Valida dígitos verificadores
        if (!$this->validateCheckDigits($cnpj)) {
            return $this->setError('Dígitos verificadores do CNPJ são inválidos');
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Limpa formatação do CNPJ (remove pontos, barra e hífen)
     */
    public function clean(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj);
    }

    /**
     * Formata CNPJ (adiciona pontos, barra e hífen)
     */
    public function format(string $cnpj): string
    {
        $cleanCnpj = $this->clean($cnpj);

        if (strlen($cleanCnpj) !== 14) {
            return $cnpj; // Retorna original se inválido
        }

        return substr($cleanCnpj, 0, 2) . '.' .
               substr($cleanCnpj, 2, 3) . '.' .
               substr($cleanCnpj, 5, 3) . '/' .
               substr($cleanCnpj, 8, 4) . '-' .
               substr($cleanCnpj, 12, 2);
    }

    /**
     * Verifica se é matriz (filial = 0001)
     */
    public function isMatriz(string $cnpj): bool
    {
        $cleanCnpj = $this->clean($cnpj);

        if (strlen($cleanCnpj) !== 14) {
            return false;
        }

        return substr($cleanCnpj, 8, 4) === '0001';
    }

    /**
     * Verifica se é filial (filial != 0001)
     */
    public function isFilial(string $cnpj): bool
    {
        return !$this->isMatriz($cnpj) && $this->validate($cnpj);
    }

    /**
     * Obtém número da filial
     */
    public function getFilialNumber(string $cnpj): ?string
    {
        $cleanCnpj = $this->clean($cnpj);

        if (strlen($cleanCnpj) !== 14) {
            return null;
        }

        return substr($cleanCnpj, 8, 4);
    }

    /**
     * Obtém número base (sem filial e dígitos verificadores)
     */
    public function getBaseNumber(string $cnpj): ?string
    {
        $cleanCnpj = $this->clean($cnpj);

        if (strlen($cleanCnpj) !== 14) {
            return null;
        }

        return substr($cleanCnpj, 0, 8);
    }

    /**
     * Gera CNPJ válido para testes
     */
    public function generate(bool $isMatriz = true): string
    {
        // Gera os primeiros 8 dígitos (número base)
        $digits = [];
        for ($i = 0; $i < 8; $i++) {
            $digits[] = rand(0, 9);
        }

        // Adiciona número da filial
        $filialNumber = $isMatriz ? '0001' : sprintf('%04d', rand(2, 9999));
        foreach (str_split($filialNumber) as $digit) {
            $digits[] = (int) $digit;
        }

        // Calcula os dígitos verificadores
        $firstDigit = $this->calculateFirstDigit($digits);
        $secondDigit = $this->calculateSecondDigit(array_merge($digits, [$firstDigit]));

        $cnpj = implode('', $digits) . $firstDigit . $secondDigit;

        // Verifica se não é uma sequência inválida
        if (in_array($cnpj, $this->invalidSequences)) {
            return $this->generate($isMatriz); // Gera outro se for inválido
        }

        return $cnpj;
    }

    /**
     * Gera CNPJ formatado válido para testes
     */
    public function generateFormatted(bool $isMatriz = true): string
    {
        return $this->format($this->generate($isMatriz));
    }

    /**
     * Gera CNPJ de filial baseado em uma matriz
     */
    public function generateFilial(string $matrizCnpj, int $filialNumber = null): ?string
    {
        if (!$this->validate($matrizCnpj) || !$this->isMatriz($matrizCnpj)) {
            return null;
        }

        $baseNumber = $this->getBaseNumber($matrizCnpj);
        $filialNumber = $filialNumber ?? rand(2, 9999);
        $filialString = sprintf('%04d', $filialNumber);

        // Monta CNPJ da filial
        $digits = array_merge(
            array_map('intval', str_split($baseNumber)),
            array_map('intval', str_split($filialString))
        );

        // Calcula dígitos verificadores
        $firstDigit = $this->calculateFirstDigit($digits);
        $secondDigit = $this->calculateSecondDigit(array_merge($digits, [$firstDigit]));

        return implode('', $digits) . $firstDigit . $secondDigit;
    }

    /**
     * Obtém informações sobre o CNPJ
     */
    public function getCNPJInfo(string $cnpj): array
    {
        if (!$this->validate($cnpj)) {
            return ['valid' => false];
        }

        $cleanCnpj = $this->clean($cnpj);

        return [
            'valid' => true,
            'clean' => $cleanCnpj,
            'formatted' => $this->format($cleanCnpj),
            'base_number' => $this->getBaseNumber($cleanCnpj),
            'filial_number' => $this->getFilialNumber($cleanCnpj),
            'is_matriz' => $this->isMatriz($cleanCnpj),
            'is_filial' => $this->isFilial($cleanCnpj),
            'check_digits' => substr($cleanCnpj, 12, 2),
        ];
    }

    /**
     * Valida dígitos verificadores
     */
    private function validateCheckDigits(string $cnpj): bool
    {
        $digits = array_map('intval', str_split($cnpj));

        // Calcula primeiro dígito verificador
        $firstDigit = $this->calculateFirstDigit(array_slice($digits, 0, 12));
        if ($firstDigit !== $digits[12]) {
            return false;
        }

        // Calcula segundo dígito verificador
        $secondDigit = $this->calculateSecondDigit(array_slice($digits, 0, 13));
        if ($secondDigit !== $digits[13]) {
            return false;
        }

        return true;
    }

    /**
     * Calcula primeiro dígito verificador
     */
    private function calculateFirstDigit(array $digits): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $digits[$i] * $this->firstDigitWeights[$i];
        }

        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    /**
     * Calcula segundo dígito verificador
     */
    private function calculateSecondDigit(array $digits): int
    {
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $digits[$i] * $this->secondDigitWeights[$i];
        }

        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
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
     * Converte CNPJ para array de dígitos
     */
    public function toDigitsArray(string $cnpj): array
    {
        $cleanCnpj = $this->clean($cnpj);
        return array_map('intval', str_split($cleanCnpj));
    }

    /**
     * Verifica se dois CNPJs são iguais (ignora formatação)
     */
    public function equals(string $cnpj1, string $cnpj2): bool
    {
        return $this->clean($cnpj1) === $this->clean($cnpj2);
    }

    /**
     * Verifica se CNPJs pertencem à mesma empresa (mesmo número base)
     */
    public function sameCompany(string $cnpj1, string $cnpj2): bool
    {
        if (!$this->validate($cnpj1) || !$this->validate($cnpj2)) {
            return false;
        }

        return $this->getBaseNumber($cnpj1) === $this->getBaseNumber($cnpj2);
    }

    /**
     * Valida lista de CNPJs
     */
    public function validateBatch(array $cnpjs): array
    {
        $results = [];

        foreach ($cnpjs as $index => $cnpj) {
            $results[$index] = $this->validateDetailed($cnpj);
        }

        return $results;
    }

    /**
     * Obtém estatísticas de validação em lote
     */
    public function getBatchStats(array $cnpjs): array
    {
        $results = $this->validateBatch($cnpjs);
        $valid = array_filter($results, fn($r) => $r['valid']);

        return [
            'total' => count($cnpjs),
            'valid' => count($valid),
            'invalid' => count($cnpjs) - count($valid),
            'success_rate' => count($cnpjs) > 0 ? count($valid) / count($cnpjs) : 0,
        ];
    }
}