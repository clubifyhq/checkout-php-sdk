<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Rules;

use Clubify\Checkout\Utils\Validators\CNPJValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel Validation Rule para CNPJ
 */
final class CNPJRule implements ValidationRule
{
    /**
     * CNPJ Validator
     */
    private CNPJValidator $validator;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->validator = new CNPJValidator();
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
            $fail('O campo :attribute não é um CNPJ válido: ' . implode(', ', $errors));
        }
    }

    /**
     * Validação estática para uso direto
     */
    public static function isValid(string $cnpj): bool
    {
        $validator = new CNPJValidator();
        return $validator->validate($cnpj);
    }

    /**
     * Formata CNPJ para exibição
     */
    public static function format(string $cnpj): string
    {
        $validator = new CNPJValidator();
        return $validator->format($cnpj);
    }

    /**
     * Remove formatação do CNPJ
     */
    public static function clean(string $cnpj): string
    {
        $validator = new CNPJValidator();
        return $validator->clean($cnpj);
    }

    /**
     * Verifica se é matriz ou filial
     */
    public static function isMatriz(string $cnpj): bool
    {
        $validator = new CNPJValidator();
        return $validator->isMatriz($cnpj);
    }

    /**
     * Obtém CNPJ da matriz
     */
    public static function getMatriz(string $cnpj): string
    {
        $validator = new CNPJValidator();
        return $validator->getMatriz($cnpj);
    }
}
