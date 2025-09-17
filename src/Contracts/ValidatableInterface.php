<?php

declare(strict_types=1);

namespace ClubifyCheckout\Contracts;

/**
 * Interface para objetos que podem ser validados
 *
 * Define o contrato para validação de dados em DTOs e outros objetos.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de validação
 * - I: Interface Segregation - Interface específica para validação
 */
interface ValidatableInterface
{
    /**
     * Valida os dados do objeto
     *
     * @throws \ClubifyCheckout\Exceptions\ValidationException
     */
    public function validate(): bool;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array;

    /**
     * Obtém as mensagens de erro de validação
     */
    public function getMessages(): array;

    /**
     * Verifica se os dados são válidos
     */
    public function isValid(): bool;

    /**
     * Obtém os erros de validação
     */
    public function getErrors(): array;
}