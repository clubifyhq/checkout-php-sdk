<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Repositories;

/**
 * Interface para repositório de ofertas
 *
 * Define operações de persistência e consulta para ofertas
 * seguindo o padrão Repository Pattern
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de repositório
 * - I: Interface Segregation - Interface específica para ofertas
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface OfferRepositoryInterface
{
    /**
     * Criar nova oferta
     *
     * @param array $data Dados da oferta
     * @return array Oferta criada
     */
    public function create(array $data): array;

    /**
     * Buscar oferta por ID
     *
     * @param string $id ID da oferta
     * @return array|null Dados da oferta ou null se não encontrada
     */
    public function findById(string $id): ?array;

    /**
     * Buscar oferta por slug
     *
     * @param string $slug Slug da oferta
     * @return array|null Dados da oferta ou null se não encontrada
     */
    public function findBySlug(string $slug): ?array;

    /**
     * Atualizar oferta
     *
     * @param string $id ID da oferta
     * @param array $data Dados para atualização
     * @return array Oferta atualizada
     */
    public function update(string $id, array $data): array;

    /**
     * Excluir oferta
     *
     * @param string $id ID da oferta
     * @return bool True se excluída com sucesso
     */
    public function delete(string $id): bool;

    /**
     * Listar ofertas com filtros
     *
     * @param array $filters Filtros de busca
     * @param int $page Página atual
     * @param int $limit Limite por página
     * @return array Lista de ofertas
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array;

    /**
     * Contar ofertas com filtros
     *
     * @param array $filters Filtros de busca
     * @return int Total de ofertas
     */
    public function count(array $filters = []): int;

    /**
     * Buscar ofertas por organização
     *
     * @param string $organizationId ID da organização
     * @return array Lista de ofertas
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * Buscar ofertas ativas
     *
     * @return array Lista de ofertas ativas
     */
    public function findActive(): array;

    /**
     * Configurar tema da oferta
     *
     * @param string $id ID da oferta
     * @param array $themeConfig Configuração do tema
     * @return array Resultado da configuração
     */
    public function updateTheme(string $id, array $themeConfig): array;

    /**
     * Configurar layout da oferta
     *
     * @param string $id ID da oferta
     * @param array $layoutConfig Configuração do layout
     * @return array Resultado da configuração
     */
    public function updateLayout(string $id, array $layoutConfig): array;

    /**
     * Obter upsells da oferta
     *
     * @param string $offerId ID da oferta
     * @return array Lista de upsells
     */
    public function getUpsells(string $offerId): array;

    /**
     * Adicionar upsell à oferta
     *
     * @param string $offerId ID da oferta
     * @param array $upsellData Dados do upsell
     * @return array Upsell criado
     */
    public function addUpsell(string $offerId, array $upsellData): array;

    /**
     * Remover upsell da oferta
     *
     * @param string $offerId ID da oferta
     * @param string $upsellId ID do upsell
     * @return bool True se removido com sucesso
     */
    public function removeUpsell(string $offerId, string $upsellId): bool;

    /**
     * Obter planos de assinatura da oferta
     *
     * @param string $offerId ID da oferta
     * @return array Lista de planos
     */
    public function getSubscriptionPlans(string $offerId): array;

    /**
     * Adicionar plano de assinatura à oferta
     *
     * @param string $offerId ID da oferta
     * @param array $planData Dados do plano
     * @return array Plano criado
     */
    public function addSubscriptionPlan(string $offerId, array $planData): array;

    /**
     * Obter estatísticas da oferta
     *
     * @param string $id ID da oferta
     * @return array Estatísticas da oferta
     */
    public function getStats(string $id): array;

    /**
     * Obter dados públicos da oferta
     *
     * @param string $slug Slug da oferta
     * @return array|null Dados públicos da oferta
     */
    public function getPublicData(string $slug): ?array;
}