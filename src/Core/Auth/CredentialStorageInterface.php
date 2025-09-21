<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

/**
 * Interface para armazenamento seguro de credenciais
 *
 * Permite diferentes implementações de storage:
 * - File-based com criptografia
 * - Database com criptografia
 * - Cache seguro (Redis/Memcached)
 */
interface CredentialStorageInterface
{
    /**
     * Armazenar credenciais de um contexto
     */
    public function store(string $context, array $credentials): void;

    /**
     * Recuperar credenciais de um contexto
     */
    public function retrieve(string $context): ?array;

    /**
     * Remover credenciais de um contexto
     */
    public function remove(string $context): void;

    /**
     * Verificar se contexto existe
     */
    public function exists(string $context): bool;

    /**
     * Listar todos os contextos disponíveis
     */
    public function listContexts(): array;

    /**
     * Limpar todas as credenciais armazenadas
     */
    public function clear(): void;

    /**
     * Verificar se o storage está funcionando corretamente
     */
    public function isHealthy(): bool;
}