<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Rules;

use Clubify\Checkout\Utils\Validators\CreditCardValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel Validation Rule para Cartão de Crédito
 */
final class CreditCardRule implements ValidationRule
{
    /**
     * Credit Card Validator
     */
    private CreditCardValidator $validator;

    /**
     * Bandeiras aceitas (opcional)
     */
    private array $acceptedBrands;

    /**
     * Construtor
     */
    public function __construct(array $acceptedBrands = [])
    {
        $this->validator = new CreditCardValidator();
        $this->acceptedBrands = $acceptedBrands;
    }

    /**
     * Executa a validação
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('O campo :attribute deve ser uma string.');
            return;
        }

        if (!$this->validator->validate($value)) {
            $errors = $this->validator->getErrors();
            $fail('O campo :attribute não é um cartão de crédito válido: ' . implode(', ', $errors));
            return;
        }

        // Verifica bandeiras aceitas se especificadas
        if (!empty($this->acceptedBrands)) {
            $brand = $this->validator->getBrand($value);
            if (!in_array($brand, $this->acceptedBrands, true)) {
                $acceptedList = implode(', ', $this->acceptedBrands);
                $fail("O campo :attribute deve ser um cartão das bandeiras: {$acceptedList}. Detectado: {$brand}");
            }
        }
    }

    /**
     * Validação estática para uso direto
     */
    public static function isValid(string $cardNumber): bool
    {
        $validator = new CreditCardValidator();
        return $validator->validate($cardNumber);
    }

    /**
     * Obtém bandeira do cartão
     */
    public static function getBrand(string $cardNumber): string
    {
        $validator = new CreditCardValidator();
        return $validator->getBrand($cardNumber);
    }

    /**
     * Mascara número do cartão
     */
    public static function mask(string $cardNumber): string
    {
        $validator = new CreditCardValidator();
        return $validator->mask($cardNumber);
    }

    /**
     * Remove formatação do cartão
     */
    public static function clean(string $cardNumber): string
    {
        $validator = new CreditCardValidator();
        return $validator->clean($cardNumber);
    }

    /**
     * Verifica se é bandeira específica
     */
    public static function isBrand(string $cardNumber, string $brand): bool
    {
        $validator = new CreditCardValidator();
        return $validator->getBrand($cardNumber) === $brand;
    }

    /**
     * Factory methods para bandeiras específicas
     */
    public static function visa(): self
    {
        return new self(['visa']);
    }

    public static function mastercard(): self
    {
        return new self(['mastercard']);
    }

    public static function amex(): self
    {
        return new self(['amex']);
    }

    public static function elo(): self
    {
        return new self(['elo']);
    }

    public static function hipercard(): self
    {
        return new self(['hipercard']);
    }

    public static function dinersClub(): self
    {
        return new self(['diners_club']);
    }

    public static function discover(): self
    {
        return new self(['discover']);
    }

    public static function jcb(): self
    {
        return new self(['jcb']);
    }

    /**
     * Aceita múltiplas bandeiras
     */
    public static function acceptBrands(array $brands): self
    {
        return new self($brands);
    }

    /**
     * Aceita apenas bandeiras brasileiras
     */
    public static function brazilianBrands(): self
    {
        return new self(['visa', 'mastercard', 'elo', 'hipercard']);
    }

    /**
     * Aceita apenas bandeiras internacionais
     */
    public static function internationalBrands(): self
    {
        return new self(['visa', 'mastercard', 'amex', 'diners_club', 'discover', 'jcb']);
    }
}
