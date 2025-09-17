<?php

declare(strict_types=1);

namespace Clubify\Checkout\Contracts;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\LoggerInterface;

/**
 * Interface base para Module Pattern
 *
 * Define operações básicas que todos os módulos funcionais devem implementar.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de módulo
 * - I: Interface Segregation - Interface específica para módulos
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface ModuleInterface
{
    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, LoggerInterface $logger): void;

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool;

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string;

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string;

    /**
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array;

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool;

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array;

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void;
}