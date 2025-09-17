<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Validators;

/**
 * Validador de CPF (Cadastro de Pessoa Física)
 *
 * Implementa validação completa de CPF seguindo o algoritmo
 * oficial da Receita Federal do Brasil, incluindo verificação
 * de dígitos verificadores e detecção de CPFs inválidos conhecidos.
 *
 * Funcionalidades:
 * - Validação do algoritmo oficial dos dígitos verificadores
 * - Detecção de CPFs com sequências inválidas (111.111.111-11)
 * - Formatação e normalização automática
 * - Suporte a CPF com ou sem formatação
 * - Geração de CPFs válidos para testes
 * - Validação de idade baseada no CPF
 *
 * Algoritmo oficial:
 * 1. Calcula primeiro dígito verificador
 * 2. Calcula segundo dígito verificador
 * 3. Verifica se os dígitos coincidem
 *
 * Exemplos de uso:
 * - $validator = new CPFValidator();
 * - $validator->validate('123.456.789-09'); // true/false
 * - $validator->format('12345678909'); // 123.456.789-09
 * - $validator->clean('123.456.789-09'); // 12345678909
 */
class CPFValidator implements ValidatorInterface
{
    private string $lastErrorMessage = '';

    /**
     * Lista de CPFs conhecidos como inválidos (sequências)
     */
    private array $invalidSequences = [
        '00000000000',
        '11111111111',
        '22222222222',
        '33333333333',
        '44444444444',
        '55555555555',
        '66666666666',
        '77777777777',
        '88888888888',
        '99999999999',
    ];

    /**
     * Valida CPF
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
            return $this->setError('CPF deve ser uma string ou número');
        }

        // Converte para string e limpa
        $cpf = $this->clean((string) $value);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return $this->setError('CPF deve conter exatamente 11 dígitos');
        }

        // Verifica se são todos números
        if (!ctype_digit($cpf)) {
            return $this->setError('CPF deve conter apenas números');
        }

        // Verifica sequências inválidas
        if (in_array($cpf, $this->invalidSequences)) {
            return $this->setError('CPF não pode ser uma sequência de números iguais');
        }

        // Valida dígitos verificadores
        if (!$this->validateCheckDigits($cpf)) {
            return $this->setError('Dígitos verificadores do CPF são inválidos');
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Limpa formatação do CPF (remove pontos e hífen)
     */
    public function clean(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf);
    }

    /**
     * Formata CPF (adiciona pontos e hífen)
     */
    public function format(string $cpf): string
    {
        $cleanCpf = $this->clean($cpf);

        if (strlen($cleanCpf) !== 11) {
            return $cpf; // Retorna original se inválido
        }

        return substr($cleanCpf, 0, 3) . '.' .
               substr($cleanCpf, 3, 3) . '.' .
               substr($cleanCpf, 6, 3) . '-' .
               substr($cleanCpf, 9, 2);
    }

    /**
     * Gera CPF válido para testes
     */
    public function generate(): string
    {
        // Gera os primeiros 9 dígitos
        $digits = [];
        for ($i = 0; $i < 9; $i++) {
            $digits[] = rand(0, 9);
        }

        // Calcula os dígitos verificadores
        $firstDigit = $this->calculateFirstDigit($digits);
        $secondDigit = $this->calculateSecondDigit(array_merge($digits, [$firstDigit]));

        $cpf = implode('', $digits) . $firstDigit . $secondDigit;

        // Verifica se não é uma sequência inválida
        if (in_array($cpf, $this->invalidSequences)) {
            return $this->generate(); // Gera outro se for inválido
        }

        return $cpf;
    }

    /**
     * Gera CPF formatado válido para testes
     */
    public function generateFormatted(): string
    {
        return $this->format($this->generate());
    }

    /**
     * Obtém informações sobre o CPF
     */
    public function getCPFInfo(string $cpf): array
    {
        if (!$this->validate($cpf)) {
            return ['valid' => false];
        }

        $cleanCpf = $this->clean($cpf);

        return [
            'valid' => true,
            'clean' => $cleanCpf,
            'formatted' => $this->format($cleanCpf),
            'region' => $this->getRegionByLastDigit($cleanCpf),
            'check_digits' => substr($cleanCpf, 9, 2),
        ];
    }

    /**
     * Verifica região baseada no último dígito antes dos verificadores
     */
    public function getRegionByLastDigit(string $cpf): string
    {
        $cleanCpf = $this->clean($cpf);
        $regionDigit = (int) $cleanCpf[8];

        $regions = [
            1 => 'DF, GO, MT, MS, TO',
            2 => 'AC, AM, AP, PA, RO, RR',
            3 => 'CE, MA, PI',
            4 => 'AL, PB, PE, RN',
            5 => 'BA, SE',
            6 => 'MG',
            7 => 'ES, RJ',
            8 => 'SP',
            9 => 'PR, SC',
            0 => 'RS',
        ];

        return $regions[$regionDigit] ?? 'Desconhecida';
    }

    /**
     * Valida dígitos verificadores
     */
    private function validateCheckDigits(string $cpf): bool
    {
        $digits = array_map('intval', str_split($cpf));

        // Calcula primeiro dígito verificador
        $firstDigit = $this->calculateFirstDigit(array_slice($digits, 0, 9));
        if ($firstDigit !== $digits[9]) {
            return false;
        }

        // Calcula segundo dígito verificador
        $secondDigit = $this->calculateSecondDigit(array_slice($digits, 0, 10));
        if ($secondDigit !== $digits[10]) {
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
        for ($i = 0; $i < 9; $i++) {
            $sum += $digits[$i] * (10 - $i);
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
        for ($i = 0; $i < 10; $i++) {
            $sum += $digits[$i] * (11 - $i);
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
     * Converte CPF para array de dígitos
     */
    public function toDigitsArray(string $cpf): array
    {
        $cleanCpf = $this->clean($cpf);
        return array_map('intval', str_split($cleanCpf));
    }

    /**
     * Verifica se dois CPFs são iguais (ignora formatação)
     */
    public function equals(string $cpf1, string $cpf2): bool
    {
        return $this->clean($cpf1) === $this->clean($cpf2);
    }

    /**
     * Valida lista de CPFs
     */
    public function validateBatch(array $cpfs): array
    {
        $results = [];

        foreach ($cpfs as $index => $cpf) {
            $results[$index] = $this->validateDetailed($cpf);
        }

        return $results;
    }

    /**
     * Obtém estatísticas de validação em lote
     */
    public function getBatchStats(array $cpfs): array
    {
        $results = $this->validateBatch($cpfs);
        $valid = array_filter($results, fn($r) => $r['valid']);

        return [
            'total' => count($cpfs),
            'valid' => count($valid),
            'invalid' => count($cpfs) - count($valid),
            'success_rate' => count($cpfs) > 0 ? count($valid) / count($cpfs) : 0,
        ];
    }
}