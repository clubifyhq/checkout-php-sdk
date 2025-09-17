<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Rules;

use Clubify\Checkout\Utils\Validators\CPFValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel Validation Rule para CPF
 */
final class CPFRule implements ValidationRule
{
    /**
     * CPF Validator
     */
    private CPFValidator $validator;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->validator = new CPFValidator();
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
            $fail('O campo :attribute não é um CPF válido: ' . implode(', ', $errors));
        }
    }

    /**
     * Validação estática para uso direto
     */
    public static function isValid(string $cpf): bool
    {
        $validator = new CPFValidator();
        return $validator->validate($cpf);
    }

    /**
     * Formata CPF para exibição
     */
    public static function format(string $cpf): string
    {
        $validator = new CPFValidator();
        return $validator->format($cpf);
    }

    /**
     * Remove formatação do CPF
     */
    public static function clean(string $cpf): string
    {
        $validator = new CPFValidator();
        return $validator->clean($cpf);
    }
}
