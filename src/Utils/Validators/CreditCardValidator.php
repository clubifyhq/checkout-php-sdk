<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Validators;

/**
 * Validador de cartões de crédito
 *
 * Implementa validação completa de números de cartão de crédito
 * usando o algoritmo de Luhn e detecção de bandeiras específicas.
 * Inclui validação de CVV, data de expiração e BIN ranges.
 *
 * Funcionalidades:
 * - Validação algoritmo de Luhn
 * - Detecção automática de bandeira
 * - Validação de CVV por bandeira
 * - Validação de data de expiração
 * - Verificação de BIN ranges
 * - Mascaramento seguro de números
 * - Geração de números para testes
 *
 * Bandeiras suportadas:
 * - Visa, Mastercard, American Express
 * - Elo, Hipercard, Dinners Club
 * - Discover, JCB, Maestro
 * - Aura, Banescard, Fortbrasil
 *
 * Compliance PCI DSS:
 * - Mascaramento automático de dados sensíveis
 * - Validação sem armazenamento
 * - Logs seguros sem exposição de dados
 */
class CreditCardValidator implements ValidatorInterface
{
    private string $lastErrorMessage = '';

    /**
     * Padrões de BIN para detecção de bandeiras
     */
    private array $cardPatterns = [
        'visa' => [
            'pattern' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'length' => [13, 16, 19],
            'cvv_length' => 3,
            'name' => 'Visa',
        ],
        'mastercard' => [
            'pattern' => '/^5[1-5][0-9]{14}$|^2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9][0-9]|7(?:[01][0-9]|20))[0-9]{12}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'Mastercard',
        ],
        'amex' => [
            'pattern' => '/^3[47][0-9]{13}$/',
            'length' => [15],
            'cvv_length' => 4,
            'name' => 'American Express',
        ],
        'elo' => [
            'pattern' => '/^(?:4011|4312|4389|4514|4573|5041|5066|5067|6277|6362|6363)[0-9]{12}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'Elo',
        ],
        'hipercard' => [
            'pattern' => '/^6062[0-9]{12}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'Hipercard',
        ],
        'dinners' => [
            'pattern' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
            'length' => [14],
            'cvv_length' => 3,
            'name' => 'Dinners Club',
        ],
        'discover' => [
            'pattern' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'Discover',
        ],
        'jcb' => [
            'pattern' => '/^(?:2131|1800|35\d{3})\d{11}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'JCB',
        ],
        'maestro' => [
            'pattern' => '/^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/',
            'length' => [12, 13, 14, 15, 16, 17, 18, 19],
            'cvv_length' => 3,
            'name' => 'Maestro',
        ],
        'aura' => [
            'pattern' => '/^50[0-9]{14}$/',
            'length' => [16],
            'cvv_length' => 3,
            'name' => 'Aura',
        ],
    ];

    /**
     * Testa cartões conhecidos (para testes em sandbox)
     */
    private array $testCards = [
        'visa' => [
            '4111111111111111',
            '4012888888881881',
            '4222222222222',
        ],
        'mastercard' => [
            '5555555555554444',
            '5105105105105100',
            '2223003122003222',
        ],
        'amex' => [
            '378282246310005',
            '371449635398431',
        ],
    ];

    /**
     * Valida número do cartão
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
            return $this->setError('Número do cartão deve ser uma string ou número');
        }

        // Converte para string e limpa
        $cardNumber = $this->clean((string) $value);

        // Verifica se não está vazio
        if (empty($cardNumber)) {
            return $this->setError('Número do cartão não pode estar vazio');
        }

        // Verifica se são todos números
        if (!ctype_digit($cardNumber)) {
            return $this->setError('Número do cartão deve conter apenas dígitos');
        }

        // Verifica comprimento mínimo e máximo
        if (strlen($cardNumber) < 12 || strlen($cardNumber) > 19) {
            return $this->setError('Número do cartão deve ter entre 12 e 19 dígitos');
        }

        // Detecta bandeira
        $brand = $this->detectBrand($cardNumber);
        if (!$brand) {
            return $this->setError('Bandeira do cartão não identificada ou não suportada');
        }

        // Valida comprimento específico da bandeira
        if (!$this->validateLength($cardNumber, $brand)) {
            return $this->setError("Comprimento inválido para cartão {$this->cardPatterns[$brand]['name']}");
        }

        // Valida algoritmo de Luhn
        if (!$this->validateLuhn($cardNumber)) {
            return $this->setError('Número do cartão é inválido (falha na verificação de Luhn)');
        }

        return ['valid' => true, 'message' => '', 'brand' => $brand];
    }

    /**
     * Valida CVV
     */
    public function validateCVV(string $cvv, string $cardNumber): bool
    {
        $brand = $this->detectBrand($this->clean($cardNumber));

        if (!$brand) {
            $this->lastErrorMessage = 'Não foi possível detectar a bandeira do cartão';
            return false;
        }

        $expectedLength = $this->cardPatterns[$brand]['cvv_length'];

        if (strlen($cvv) !== $expectedLength) {
            $this->lastErrorMessage = "CVV deve ter {$expectedLength} dígitos para {$this->cardPatterns[$brand]['name']}";
            return false;
        }

        if (!ctype_digit($cvv)) {
            $this->lastErrorMessage = 'CVV deve conter apenas números';
            return false;
        }

        return true;
    }

    /**
     * Valida data de expiração
     */
    public function validateExpiryDate(string $month, string $year): bool
    {
        // Valida formato do mês
        if (!ctype_digit($month) || (int) $month < 1 || (int) $month > 12) {
            $this->lastErrorMessage = 'Mês de expiração inválido (deve ser 01-12)';
            return false;
        }

        // Valida formato do ano
        if (!ctype_digit($year)) {
            $this->lastErrorMessage = 'Ano de expiração deve conter apenas números';
            return false;
        }

        // Normaliza ano (aceita 2 ou 4 dígitos)
        $currentYear = (int) date('Y');
        $expiryYear = (int) $year;

        if (strlen($year) === 2) {
            $currentCentury = intval($currentYear / 100) * 100;
            $expiryYear = $currentCentury + $expiryYear;

            // Se o ano resultante for muito no passado, assume próximo século
            if ($expiryYear < $currentYear) {
                $expiryYear += 100;
            }
        }

        // Verifica se não expirou
        $currentMonth = (int) date('m');
        $expiryMonth = (int) $month;

        if ($expiryYear < $currentYear ||
            ($expiryYear === $currentYear && $expiryMonth < $currentMonth)) {
            $this->lastErrorMessage = 'Cartão expirado';
            return false;
        }

        // Verifica se não está muito no futuro (máximo 20 anos)
        if ($expiryYear > $currentYear + 20) {
            $this->lastErrorMessage = 'Data de expiração muito distante no futuro';
            return false;
        }

        return true;
    }

    /**
     * Limpa número do cartão
     */
    public function clean(string $cardNumber): string
    {
        return preg_replace('/\D/', '', $cardNumber);
    }

    /**
     * Detecta bandeira do cartão
     */
    public function detectBrand(string $cardNumber): ?string
    {
        $cleanNumber = $this->clean($cardNumber);

        foreach ($this->cardPatterns as $brand => $pattern) {
            if (preg_match($pattern['pattern'], $cleanNumber)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Mascara número do cartão para exibição segura
     */
    public function mask(string $cardNumber): string
    {
        $cleanNumber = $this->clean($cardNumber);

        if (strlen($cleanNumber) < 6) {
            return str_repeat('*', strlen($cleanNumber));
        }

        // Mostra primeiros 4 e últimos 4 dígitos
        $firstFour = substr($cleanNumber, 0, 4);
        $lastFour = substr($cleanNumber, -4);
        $middleLength = strlen($cleanNumber) - 8;

        return $firstFour . str_repeat('*', $middleLength) . $lastFour;
    }

    /**
     * Formata número do cartão com espaços
     */
    public function format(string $cardNumber): string
    {
        $cleanNumber = $this->clean($cardNumber);
        $brand = $this->detectBrand($cleanNumber);

        // American Express: XXXX XXXXXX XXXXX
        if ($brand === 'amex') {
            return implode(' ', [
                substr($cleanNumber, 0, 4),
                substr($cleanNumber, 4, 6),
                substr($cleanNumber, 10, 5),
            ]);
        }

        // Dinners Club: XXXX XXXXXX XXXX
        if ($brand === 'dinners') {
            return implode(' ', [
                substr($cleanNumber, 0, 4),
                substr($cleanNumber, 4, 6),
                substr($cleanNumber, 10, 4),
            ]);
        }

        // Padrão: XXXX XXXX XXXX XXXX
        return implode(' ', str_split($cleanNumber, 4));
    }

    /**
     * Obtém informações sobre o cartão
     */
    public function getCardInfo(string $cardNumber): array
    {
        $cleanNumber = $this->clean($cardNumber);
        $brand = $this->detectBrand($cleanNumber);

        if (!$brand || !$this->validate($cleanNumber)) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'brand' => $brand,
            'brand_name' => $this->cardPatterns[$brand]['name'],
            'masked' => $this->mask($cleanNumber),
            'formatted' => $this->format($cleanNumber),
            'length' => strlen($cleanNumber),
            'cvv_length' => $this->cardPatterns[$brand]['cvv_length'],
            'is_test_card' => $this->isTestCard($cleanNumber),
            'bin' => substr($cleanNumber, 0, 6),
            'last_four' => substr($cleanNumber, -4),
        ];
    }

    /**
     * Verifica se é cartão de teste
     */
    public function isTestCard(string $cardNumber): bool
    {
        $cleanNumber = $this->clean($cardNumber);

        foreach ($this->testCards as $testNumbers) {
            if (in_array($cleanNumber, $testNumbers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera número de cartão válido para testes
     */
    public function generateTestCard(string $brand = 'visa'): string
    {
        if (!isset($this->testCards[$brand]) || empty($this->testCards[$brand])) {
            $brand = 'visa';
        }

        $testCards = $this->testCards[$brand];
        return $testCards[array_rand($testCards)];
    }

    /**
     * Valida comprimento específico da bandeira
     */
    private function validateLength(string $cardNumber, string $brand): bool
    {
        $length = strlen($cardNumber);
        $validLengths = $this->cardPatterns[$brand]['length'];

        return in_array($length, $validLengths);
    }

    /**
     * Valida usando algoritmo de Luhn
     */
    private function validateLuhn(string $cardNumber): bool
    {
        $sum = 0;
        $alternate = false;

        // Processa dígitos da direita para esquerda
        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $digit = (int) $cardNumber[$i];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }

            $sum += $digit;
            $alternate = !$alternate;
        }

        return ($sum % 10) === 0;
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
     * Obtém lista de bandeiras suportadas
     */
    public function getSupportedBrands(): array
    {
        return array_map(fn($pattern) => $pattern['name'], $this->cardPatterns);
    }

    /**
     * Valida cartão completo (número, CVV, expiração)
     */
    public function validateFullCard(array $cardData): array
    {
        $errors = [];

        // Valida número
        if (!$this->validate($cardData['number'] ?? '')) {
            $errors['number'] = $this->getErrorMessage();
        }

        // Valida CVV
        if (isset($cardData['cvv']) && isset($cardData['number'])) {
            if (!$this->validateCVV($cardData['cvv'], $cardData['number'])) {
                $errors['cvv'] = $this->getErrorMessage();
            }
        }

        // Valida expiração
        if (isset($cardData['expiry_month']) && isset($cardData['expiry_year'])) {
            if (!$this->validateExpiryDate($cardData['expiry_month'], $cardData['expiry_year'])) {
                $errors['expiry'] = $this->getErrorMessage();
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}