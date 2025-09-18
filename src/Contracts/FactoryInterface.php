<?php

declare(strict_types=1);

namespace Clubify\Checkout\Contracts;

/**
 * Interface base para Factory Pattern
 *
 * Define operações básicas que todas as factories devem implementar.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de criação
 * - I: Interface Segregation - Interface específica para factories
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface FactoryInterface
{
    /**
     * Cria um objeto do tipo especificado
     *
     * @param string $type Tipo do objeto a ser criado
     * @param array $config Configurações opcionais para criação
     * @return object Objeto criado
     * @throws \InvalidArgumentException Se o tipo não for suportado
     */
    public function create(string $type, array $config = []): object;

    /**
     * Obtém lista de tipos suportados pela factory
     *
     * @return array Lista de tipos suportados
     */
    public function getSupportedTypes(): array;
}