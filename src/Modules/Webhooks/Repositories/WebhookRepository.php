<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Repositories;

use ClubifyCheckout\Repositories\BaseRepository;
use Clubify\Checkout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;
use DateTime;

/**
 * Repositório de webhooks
 *
 * Implementação completa do repositório de webhooks com
 * todas as operações específicas para gerenciamento de
 * webhooks, entregas, retries e auditoria.
 */
class WebhookRepository extends BaseRepository implements WebhookRepositoryInterface
{
    protected string $endpoint = '/webhooks';

    /**
     * Busca webhooks por evento
     */
    public function findByEvent(string $eventType, bool $activeOnly = true): array
    {
        $filters = ['events' => $eventType];
        if ($activeOnly) {
            $filters['active'] = true;
        }

        return $this->findByFilters($filters);
    }

    /**
     * Busca webhooks por URL
     */
    public function findByUrl(string $url): ?array
    {
        $results = $this->findByFilters(['url' => $url]);
        return $results[0] ?? null;
    }

    /**
     * Busca webhooks por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        $filters['organization_id'] = $organizationId;
        return $this->findByFilters($filters);
    }

    /**
     * Busca webhooks ativos
     */
    public function findActive(array $filters = []): array
    {
        $filters['active'] = true;
        return $this->findByFilters($filters);
    }

    /**
     * Busca webhooks inativos
     */
    public function findInactive(array $filters = []): array
    {
        $filters['active'] = false;
        return $this->findByFilters($filters);
    }

    /**
     * Ativa webhook
     */
    public function activate(string $id): bool
    {
        $response = $this->httpClient->patch(
            $this->buildUrl("{$this->endpoint}/{$id}/activate")
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Desativa webhook
     */
    public function deactivate(string $id): bool
    {
        $response = $this->httpClient->patch(
            $this->buildUrl("{$this->endpoint}/{$id}/deactivate")
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Atualiza última entrega
     */
    public function updateLastDelivery(string $id, array $deliveryData): bool
    {
        $response = $this->httpClient->patch(
            $this->buildUrl("{$this->endpoint}/{$id}/last-delivery"),
            ['json' => $deliveryData]
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Incrementa contador de falhas
     */
    public function incrementFailureCount(string $id): bool
    {
        $response = $this->httpClient->patch(
            $this->buildUrl("{$this->endpoint}/{$id}/increment-failures")
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Reseta contador de falhas
     */
    public function resetFailureCount(string $id): bool
    {
        $response = $this->httpClient->patch(
            $this->buildUrl("{$this->endpoint}/{$id}/reset-failures")
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Cria log de entrega
     */
    public function createDeliveryLog(string $webhookId, array $deliveryData): string
    {
        $response = $this->httpClient->post(
            $this->buildUrl("/webhook-deliveries"),
            ['json' => array_merge($deliveryData, ['webhook_id' => $webhookId])]
        );

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['id'];
    }

    /**
     * Busca logs de entrega
     */
    public function findDeliveryLogs(string $webhookId, array $filters = []): array
    {
        $filters['webhook_id'] = $webhookId;

        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-deliveries", $filters)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Busca entregas falhadas
     */
    public function findFailedDeliveries(string $since, array $filters = []): array
    {
        $filters['status'] = 'failed';
        $filters['since'] = $since;

        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-deliveries", $filters)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Agenda retry
     */
    public function scheduleRetry(string $webhookId, string $deliveryId, DateTime $scheduledAt, int $attempt): string
    {
        $data = [
            'webhook_id' => $webhookId,
            'delivery_id' => $deliveryId,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'attempt' => $attempt,
            'status' => 'pending',
        ];

        $response = $this->httpClient->post(
            $this->buildUrl("/webhook-retries"),
            ['json' => $data]
        );

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['id'];
    }

    /**
     * Busca retries pendentes
     */
    public function findPendingRetries(int $limit = 100): array
    {
        $filters = [
            'status' => 'pending',
            'scheduled_at_lte' => (new DateTime())->format('Y-m-d H:i:s'),
            'limit' => $limit,
            'sort' => 'scheduled_at:asc',
        ];

        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-retries", $filters)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Marca retry como processado
     */
    public function markRetryProcessed(string $retryId, bool $success, array $result = []): bool
    {
        $data = [
            'status' => $success ? 'completed' : 'failed',
            'processed_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'result' => $result,
        ];

        $response = $this->httpClient->patch(
            $this->buildUrl("/webhook-retries/{$retryId}"),
            ['json' => $data]
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Busca estatísticas de webhook
     */
    public function getWebhookStats(string $webhookId, string $period = '24 hours'): array
    {
        $response = $this->httpClient->get(
            $this->buildUrl("{$this->endpoint}/{$webhookId}/stats", ['period' => $period])
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Busca estatísticas globais
     */
    public function getGlobalStats(string $period = '24 hours', array $filters = []): array
    {
        $filters['period'] = $period;

        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-stats", $filters)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Remove webhooks inativos antigos
     */
    public function deleteOldInactive(int $daysToKeep = 30): int
    {
        $cutoffDate = (new DateTime())->modify("-{$daysToKeep} days")->format('Y-m-d');

        $response = $this->httpClient->delete(
            $this->buildUrl("{$this->endpoint}/cleanup/inactive", [
                'cutoff_date' => $cutoffDate,
            ])
        );

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['deleted_count'] ?? 0;
    }

    /**
     * Remove logs de entrega antigos
     */
    public function deleteOldDeliveryLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = (new DateTime())->modify("-{$daysToKeep} days")->format('Y-m-d');

        $response = $this->httpClient->delete(
            $this->buildUrl("/webhook-deliveries/cleanup", [
                'cutoff_date' => $cutoffDate,
            ])
        );

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['deleted_count'] ?? 0;
    }

    /**
     * Remove retries antigos
     */
    public function deleteOldRetries(int $daysToKeep = 30): int
    {
        $cutoffDate = (new DateTime())->modify("-{$daysToKeep} days")->format('Y-m-d');

        $response = $this->httpClient->delete(
            $this->buildUrl("/webhook-retries/cleanup", [
                'cutoff_date' => $cutoffDate,
            ])
        );

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['deleted_count'] ?? 0;
    }

    /**
     * Verifica se URL é válida e acessível
     */
    public function validateUrl(string $url): array
    {
        $response = $this->httpClient->post(
            $this->buildUrl("/webhook-validation"),
            ['json' => ['url' => $url]]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Busca configuração global
     */
    public function getGlobalConfig(): array
    {
        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-config")
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Atualiza configuração global
     */
    public function updateGlobalConfig(array $config): bool
    {
        $response = $this->httpClient->put(
            $this->buildUrl("/webhook-config"),
            ['json' => $config]
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Busca webhooks por filtros avançados
     */
    public function findWithAdvancedFilters(array $filters): array
    {
        $response = $this->httpClient->post(
            $this->buildUrl("{$this->endpoint}/search"),
            ['json' => ['filters' => $filters]]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Conta entregas por status
     */
    public function countDeliveriesByStatus(string $webhookId, string $period = '24 hours'): array
    {
        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-deliveries/count-by-status", [
                'webhook_id' => $webhookId,
                'period' => $period,
            ])
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Obtém taxa de falhas
     */
    public function getFailureRate(string $webhookId, string $period = '1 hour'): float
    {
        $counts = $this->countDeliveriesByStatus($webhookId, $period);

        $total = array_sum($counts);
        $failed = $counts['failed'] ?? 0;

        return $total > 0 ? $failed / $total : 0.0;
    }

    /**
     * Busca webhook mais ativo
     */
    public function findMostActive(string $period = '24 hours'): ?array
    {
        $response = $this->httpClient->get(
            $this->buildUrl("{$this->endpoint}/most-active", ['period' => $period])
        );

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['webhook'] ?? null;
    }

    /**
     * Busca eventos mais entregues
     */
    public function findMostDeliveredEvents(string $period = '24 hours', int $limit = 10): array
    {
        $response = $this->httpClient->get(
            $this->buildUrl("/webhook-events/most-delivered", [
                'period' => $period,
                'limit' => $limit,
            ])
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Exporta dados de webhook
     */
    public function exportWebhookData(string $webhookId, array $options = []): array
    {
        $response = $this->httpClient->post(
            $this->buildUrl("{$this->endpoint}/{$webhookId}/export"),
            ['json' => $options]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Importa dados de webhook
     */
    public function importWebhookData(array $data, array $options = []): array
    {
        $payload = [
            'data' => $data,
            'options' => $options,
        ];

        $response = $this->httpClient->post(
            $this->buildUrl("{$this->endpoint}/import"),
            ['json' => $payload]
        );

        return json_decode($response->getBody()->getContents(), true);
    }
}