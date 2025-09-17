<?php

declare(strict_types=1);

namespace ClubifyCheckout\Utils\Validators;

/**
 * Interface para validadores
 *
 * Define o contrato que todos os validadores devem seguir,
 * garantindo consistência na API e permitindo extensibilidade.
 *
 * Implementações devem:
 * - Validar o formato básico do dado
 * - Retornar true/false para valid/invalid
 * - Implementar validações específicas do domínio
 * - Ser thread-safe e stateless
 * - Ter performance otimizada
 *
 * Padrão Strategy Pattern:
 * - Permite diferentes algoritmos de validação
 * - Facilita testes unitários
 * - Extensível sem modificar código existente
 */
interface ValidatorInterface
{
    /**
     * Valida o valor fornecido
     *
     * @param mixed $value Valor a ser validado
     * @return bool True se válido, false caso contrário
     */
    public function validate($value): bool;

    /**
     * Obtém mensagem de erro da última validação
     *
     * @return string Mensagem de erro ou string vazia se válido
     */
    public function getErrorMessage(): string;

    /**
     * Valida e retorna resultado detalhado
     *
     * @param mixed $value Valor a ser validado
     * @return array Array com resultado detalhado ['valid' => bool, 'message' => string]
     */
    public function validateDetailed($value): array;
}