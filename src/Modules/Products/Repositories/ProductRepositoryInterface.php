<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Repositories;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para repositório de produtos
 *
 * Define operações específicas para gestão de produtos:
 * - CRUD básico de produtos
 * - Busca por categoria e tipo
 * - Gestão de status e disponibilidade
 * - Operações de estoque
 * - Busca textual e filtros
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de produto
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Métodos específicos de produto
 * - D: Dependency Inversion - Abstração para implementações
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca produtos por categoria
     */
    public function findByCategory(string $categoryId, array $filters = []): array;

    /**
     * Busca produtos por tipo
     */
    public function findByType(string $type, array $filters = []): array;

    /**
     * Busca produtos por status
     */
    public function findByStatus(string $status): array;

    /**
     * Busca produtos ativos
     */
    public function findActive(): array;

    /**
     * Busca produtos por organização
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * Busca produtos por slug
     */
    public function findBySlug(string $slug): ?array;

    /**
     * Busca produtos por SKU
     */
    public function findBySku(string $sku): ?array;

    /**
     * Busca textual em produtos
     */
    public function search(string $query, array $filters = []): array;

    /**
     * Obtém produtos em destaque
     */
    public function getFeatured(int $limit = 10): array;

    /**
     * Obtém produtos mais vendidos
     */
    public function getBestSellers(int $limit = 10): array;

    /**
     * Obtém produtos relacionados
     */
    public function getRelated(string $productId, int $limit = 5): array;

    /**
     * Verifica disponibilidade de estoque
     */
    public function checkStock(string $productId, int $quantity = 1): bool;

    /**
     * Atualiza estoque do produto
     */
    public function updateStock(string $productId, int $quantity, string $operation = 'set'): bool;

    /**
     * Obtém produtos com estoque baixo
     */
    public function getLowStock(int $threshold = 10): array;

    /**
     * Ativa produto
     */
    public function activate(string $id): bool;

    /**
     * Desativa produto
     */
    public function deactivate(string $id): bool;

    /**
     * Obtém variações de um produto
     */
    public function getVariations(string $productId): array;

    /**
     * Obtém preços históricos
     */
    public function getPriceHistory(string $productId): array;

    /**
     * Obtém estatísticas de vendas
     */
    public function getSalesStats(string $productId): array;

    /**
     * Duplica produto
     */
    public function duplicate(string $id, array $overrideData = []): array;
}