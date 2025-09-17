<?php

declare(strict_types=1);

namespace Clubify\Checkout\Contracts;

/**
 * Interface base para Repository Pattern
 *
 * Define operações básicas CRUD que todos os repositories devem implementar.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de persistência
 * - I: Interface Segregation - Interface específica para repositórios
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface RepositoryInterface
{
    /**
     * Busca um registro por ID
     */
    public function findById(string $id): ?array;

    /**
     * Busca vários registros por um array de IDs
     */
    public function findByIds(array $ids): array;

    /**
     * Busca todos os registros com paginação opcional
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * Busca registros por critérios específicos
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array;

    /**
     * Busca um único registro por critérios
     */
    public function findOneBy(array $criteria): ?array;

    /**
     * Cria um novo registro
     */
    public function create(array $data): array;

    /**
     * Atualiza um registro existente
     */
    public function update(string $id, array $data): array;

    /**
     * Remove um registro por ID
     */
    public function delete(string $id): bool;

    /**
     * Verifica se um registro existe
     */
    public function exists(string $id): bool;

    /**
     * Conta o total de registros
     */
    public function count(array $criteria = []): int;

    /**
     * Busca com filtros avançados
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array;
}
