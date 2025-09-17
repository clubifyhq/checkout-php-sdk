<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Repositories;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para repositório de webhooks
 *
 * Define operações específicas para gerenciamento de webhooks
 * incluindo busca por eventos, entrega, retry e auditoria.
 *
 * Operações específicas:
 * - Busca por tipo de evento
 * - Busca por status de entrega
 * - Gerenciamento de retries
 * - Logs de auditoria
 * - Estatísticas de performance
 * - Cleanup de dados antigos
 */
interface WebhookRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca webhooks por evento
     *
     * @param string $eventType Tipo do evento
     * @param bool $activeOnly Apenas webhooks ativos
     * @return array Lista de webhooks
     */
    public function findByEvent(string $eventType, bool $activeOnly = true): array;

    /**
     * Busca webhooks por URL
     *
     * @param string $url URL do webhook
     * @return array|null Webhook encontrado ou null
     */
    public function findByUrl(string $url): ?array;

    /**
     * Busca webhooks por organização
     *
     * @param string $organizationId ID da organização
     * @param array $filters Filtros adicionais
     * @return array Lista de webhooks
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca webhooks ativos
     *
     * @param array $filters Filtros adicionais
     * @return array Lista de webhooks ativos
     */
    public function findActive(array $filters = []): array;

    /**
     * Busca webhooks inativos
     *
     * @param array $filters Filtros adicionais
     * @return array Lista de webhooks inativos
     */
    public function findInactive(array $filters = []): array;

    /**
     * Ativa webhook
     *
     * @param string $id ID do webhook
     * @return bool True se ativado com sucesso
     */
    public function activate(string $id): bool;

    /**
     * Desativa webhook
     *
     * @param string $id ID do webhook
     * @return bool True se desativado com sucesso
     */
    public function deactivate(string $id): bool;

    /**
     * Atualiza última entrega
     *
     * @param string $id ID do webhook
     * @param array $deliveryData Dados da entrega
     * @return bool True se atualizado com sucesso
     */
    public function updateLastDelivery(string $id, array $deliveryData): bool;

    /**
     * Incrementa contador de falhas
     *
     * @param string $id ID do webhook
     * @return bool True se incrementado com sucesso
     */
    public function incrementFailureCount(string $id): bool;

    /**
     * Reseta contador de falhas
     *
     * @param string $id ID do webhook
     * @return bool True se resetado com sucesso
     */
    public function resetFailureCount(string $id): bool;

    /**
     * Cria log de entrega
     *
     * @param string $webhookId ID do webhook
     * @param array $deliveryData Dados da entrega
     * @return string ID do log criado
     */
    public function createDeliveryLog(string $webhookId, array $deliveryData): string;

    /**
     * Busca logs de entrega
     *
     * @param string $webhookId ID do webhook
     * @param array $filters Filtros adicionais
     * @return array Lista de logs
     */
    public function findDeliveryLogs(string $webhookId, array $filters = []): array;

    /**
     * Busca entregas falhadas
     *
     * @param string $since Período (ex: '24 hours', '1 week')
     * @param array $filters Filtros adicionais
     * @return array Lista de entregas falhadas
     */
    public function findFailedDeliveries(string $since, array $filters = []): array;

    /**
     * Agenda retry
     *
     * @param string $webhookId ID do webhook
     * @param string $deliveryId ID da entrega
     * @param \DateTime $scheduledAt Quando executar retry
     * @param int $attempt Número da tentativa
     * @return string ID do retry agendado
     */
    public function scheduleRetry(string $webhookId, string $deliveryId, \DateTime $scheduledAt, int $attempt): string;

    /**
     * Busca retries pendentes
     *
     * @param int $limit Limite de resultados
     * @return array Lista de retries pendentes
     */
    public function findPendingRetries(int $limit = 100): array;

    /**
     * Marca retry como processado
     *
     * @param string $retryId ID do retry
     * @param bool $success Se foi bem-sucedido
     * @param array $result Resultado do processamento
     * @return bool True se marcado com sucesso
     */
    public function markRetryProcessed(string $retryId, bool $success, array $result = []): bool;

    /**
     * Busca estatísticas de webhook
     *
     * @param string $webhookId ID do webhook
     * @param string $period Período (ex: '24 hours', '1 week')
     * @return array Estatísticas
     */
    public function getWebhookStats(string $webhookId, string $period = '24 hours'): array;

    /**
     * Busca estatísticas globais
     *
     * @param string $period Período
     * @param array $filters Filtros adicionais
     * @return array Estatísticas globais
     */
    public function getGlobalStats(string $period = '24 hours', array $filters = []): array;

    /**
     * Remove webhooks inativos antigos
     *
     * @param int $daysToKeep Dias para manter
     * @return int Número de webhooks removidos
     */
    public function deleteOldInactive(int $daysToKeep = 30): int;

    /**
     * Remove logs de entrega antigos
     *
     * @param int $daysToKeep Dias para manter
     * @return int Número de logs removidos
     */
    public function deleteOldDeliveryLogs(int $daysToKeep = 30): int;

    /**
     * Remove retries antigos
     *
     * @param int $daysToKeep Dias para manter
     * @return int Número de retries removidos
     */
    public function deleteOldRetries(int $daysToKeep = 30): int;

    /**
     * Verifica se URL é válida e acessível
     *
     * @param string $url URL para verificar
     * @return array Resultado da verificação
     */
    public function validateUrl(string $url): array;

    /**
     * Busca configuração global
     *
     * @return array Configuração global
     */
    public function getGlobalConfig(): array;

    /**
     * Atualiza configuração global
     *
     * @param array $config Nova configuração
     * @return bool True se atualizada com sucesso
     */
    public function updateGlobalConfig(array $config): bool;

    /**
     * Busca webhooks por filtros avançados
     *
     * @param array $filters Filtros complexos
     * @return array Lista de webhooks
     */
    public function findWithAdvancedFilters(array $filters): array;

    /**
     * Conta entregas por status
     *
     * @param string $webhookId ID do webhook
     * @param string $period Período
     * @return array Contagem por status
     */
    public function countDeliveriesByStatus(string $webhookId, string $period = '24 hours'): array;

    /**
     * Obtém taxa de falhas
     *
     * @param string $webhookId ID do webhook
     * @param string $period Período
     * @return float Taxa de falhas (0.0 a 1.0)
     */
    public function getFailureRate(string $webhookId, string $period = '1 hour'): float;

    /**
     * Busca webhook mais ativo
     *
     * @param string $period Período
     * @return array|null Webhook mais ativo
     */
    public function findMostActive(string $period = '24 hours'): ?array;

    /**
     * Busca eventos mais entregues
     *
     * @param string $period Período
     * @param int $limit Limite de resultados
     * @return array Lista de eventos
     */
    public function findMostDeliveredEvents(string $period = '24 hours', int $limit = 10): array;

    /**
     * Exporta dados de webhook
     *
     * @param string $webhookId ID do webhook
     * @param array $options Opções de exportação
     * @return array Dados exportados
     */
    public function exportWebhookData(string $webhookId, array $options = []): array;

    /**
     * Importa dados de webhook
     *
     * @param array $data Dados para importar
     * @param array $options Opções de importação
     * @return array Resultado da importação
     */
    public function importWebhookData(array $data, array $options = []): array;
}