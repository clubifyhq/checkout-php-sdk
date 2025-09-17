<?php

declare(strict_types=1);

namespace Clubify\Checkout\Utils\Formatters;

/**
 * Interface para formatadores
 *
 * Define o contrato que todos os formatadores devem seguir,
 * garantindo consistência na API e permitindo extensibilidade.
 *
 * Implementações devem:
 * - Formatar dados para exibição
 * - Retornar strings formatadas
 * - Ser thread-safe e stateless
 * - Ter performance otimizada
 * - Suporte a múltiplos formatos/locales
 *
 * Padrão Strategy Pattern:
 * - Permite diferentes algoritmos de formatação
 * - Facilita testes unitários
 * - Extensível sem modificar código existente
 */
interface FormatterInterface
{
    /**
     * Formata o valor fornecido
     *
     * @param mixed $value Valor a ser formatado
     * @param array $options Opções de formatação específicas
     * @return string Valor formatado
     */
    public function format($value, array $options = []): string;

    /**
     * Verifica se pode formatar o valor
     *
     * @param mixed $value Valor a ser verificado
     * @return bool True se pode formatar, false caso contrário
     */
    public function canFormat($value): bool;

    /**
     * Obtém opções padrão de formatação
     *
     * @return array Opções padrão
     */
    public function getDefaultOptions(): array;
}
