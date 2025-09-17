<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para Repository de Sessões de Checkout
 *
 * Especializa o RepositoryInterface para operações
 * específicas de sessões de checkout.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de sessão
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Interface específica para sessões
 * - D: Dependency Inversion - Abstração para implementações
 */
interface SessionRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca sessões por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca sessão por token
     */
    public function findByToken(string $token): ?array;

    /**
     * Busca sessões ativas
     */
    public function findActive(array $filters = []): array;

    /**
     * Busca sessões expiradas
     */
    public function findExpired(): array;

    /**
     * Busca sessões por status
     */
    public function findByStatus(string $status, array $filters = []): array;

    /**
     * Busca sessões por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array;

    /**
     * Busca sessões por produto
     */
    public function findByProduct(string $productId, array $filters = []): array;

    /**
     * Busca sessões por oferta
     */
    public function findByOffer(string $offerId, array $filters = []): array;

    /**
     * Atualiza status da sessão
     */
    public function updateStatus(string $id, string $status): array;

    /**
     * Atualiza dados do cliente na sessão
     */
    public function updateCustomerData(string $id, array $customerData): array;

    /**
     * Atualiza dados de pagamento na sessão
     */
    public function updatePaymentData(string $id, array $paymentData): array;

    /**
     * Atualiza dados de envio na sessão
     */
    public function updateShippingData(string $id, array $shippingData): array;

    /**
     * Adiciona evento à sessão
     */
    public function addEvent(string $id, array $event): array;

    /**
     * Obtém eventos da sessão
     */
    public function getEvents(string $id): array;

    /**
     * Marca sessão como abandonada
     */
    public function markAsAbandoned(string $id): array;

    /**
     * Marca sessão como completa
     */
    public function markAsCompleted(string $id): array;

    /**
     * Expira sessão
     */
    public function expire(string $id): array;

    /**
     * Renova sessão (estende TTL)
     */
    public function renew(string $id, int $ttlSeconds = 3600): array;

    /**
     * Limpa sessões expiradas
     */
    public function cleanupExpired(): int;

    /**
     * Obtém estatísticas de sessões
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Conta sessões por status
     */
    public function countByStatus(): array;

    /**
     * Conta sessões por período
     */
    public function countByPeriod(string $period = 'day'): array;
}
