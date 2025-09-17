<?php

declare(strict_types=1);

namespace Clubify\Checkout\Contracts;

/**
 * Interface base para Service Pattern
 *
 * Define operações básicas que todos os services devem implementar.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de serviço
 * - I: Interface Segregation - Interface específica para services
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface ServiceInterface
{
    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array;

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool;

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array;

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array;
}
