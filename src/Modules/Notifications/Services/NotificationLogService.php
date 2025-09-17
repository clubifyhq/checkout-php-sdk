<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Services;

use Clubify\Checkout\Core\Services\BaseService;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Serviço de logs de notificações
 *
 * Responsável pela gestão completa de logs de notificações:
 * - Consulta e filtro de logs
 * - Análise de falhas e problemas
 * - Histórico de entregas
 * - Estatísticas de logs
 * - Limpeza e manutenção
 * - Exportação de dados
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas gestão de logs
 * - O: Open/Closed - Extensível via novos filtros
 * - L: Liskov Substitution - Estende BaseService
 * - I: Interface Segregation - Métodos específicos
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationLogService extends BaseService
{
    private const CACHE_PREFIX = 'notification_logs:';
    private const STATS_CACHE_TTL = 300; // 5 minutos

    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        Configuration $config,
        Logger $logger
    ) {
        parent::__construct($config, $logger);
    }

    /**
     * Obtém logs de notificações com filtros
     */
    public function getLogs(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $this->validateInitialization();

        try {
            $params = array_merge($filters, [
                'page' => $page,
                'limit' => $limit,
                'sort' => $filters['sort'] ?? 'created_at:desc'
            ]);

            $response = $this->httpClient->get('/notifications/logs', [
                'query' => $params
            ]);

            $data = $response->toArray();

            $this->logger->info('Logs de notificações obtidos', [
                'total' => $data['total'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'filters' => $filters
            ]);

            // Processa logs para adicionar informações úteis
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data'] = array_map([$this, 'enrichLogEntry'], $data['data']);
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter logs de notificações', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém um log específico
     */
    public function getLog(string $logId): ?array
    {
        $this->validateInitialization();

        // Verifica cache primeiro
        $cached = $this->getCachedLog($logId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->httpClient->get("/notifications/logs/{$logId}");
            $data = $response->toArray();

            // Enriquece os dados
            $data = $this->enrichLogEntry($data);

            // Cache o resultado
            $this->cacheLog($logId, $data);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter log de notificação', [
                'log_id' => $logId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Obtém logs de uma notificação específica
     */
    public function getLogsByNotification(string $notificationId): array
    {
        $this->validateInitialization();

        return $this->getLogs([
            'notification_id' => $notificationId
        ]);
    }

    /**
     * Obtém logs de um webhook específico
     */
    public function getLogsByWebhook(string $webhookId): array
    {
        $this->validateInitialization();

        return $this->getLogs([
            'webhook_id' => $webhookId
        ]);
    }

    /**
     * Obtém notificações falhadas
     */
    public function getFailedNotifications(array $filters = []): array
    {
        $this->validateInitialization();

        $failureFilters = array_merge($filters, [
            'status' => 'failed',
            'sort' => 'created_at:desc'
        ]);

        return $this->getLogs($failureFilters);
    }

    /**
     * Obtém logs por período
     */
    public function getLogsByPeriod(string $startDate, string $endDate, array $filters = []): array
    {
        $this->validateInitialization();

        $periodFilters = array_merge($filters, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        return $this->getLogs($periodFilters);
    }

    /**
     * Obtém logs por tipo de notificação
     */
    public function getLogsByType(string $notificationType, array $filters = []): array
    {
        $this->validateInitialization();

        $typeFilters = array_merge($filters, [
            'notification_type' => $notificationType
        ]);

        return $this->getLogs($typeFilters);
    }

    /**
     * Obtém logs por método de entrega
     */
    public function getLogsByDeliveryMethod(string $deliveryMethod, array $filters = []): array
    {
        $this->validateInitialization();

        $methodFilters = array_merge($filters, [
            'delivery_method' => $deliveryMethod
        ]);

        return $this->getLogs($methodFilters);
    }

    /**
     * Obtém estatísticas de logs
     */
    public function getLogStatistics(array $filters = []): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'stats:' . md5(serialize($filters));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->httpClient->get('/notifications/logs/statistics', [
                'query' => $filters
            ]);

            $stats = $response->toArray();

            // Adiciona estatísticas calculadas
            $stats = $this->enhanceStatistics($stats);

            // Cache o resultado
            $this->cache->set($cacheKey, $stats, self::STATS_CACHE_TTL);

            $this->logger->info('Estatísticas de logs obtidas', [
                'filters' => $filters,
                'total_logs' => $stats['total_logs'] ?? 0
            ]);

            return $stats;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter estatísticas de logs', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'total_logs' => 0,
                'successful_logs' => 0,
                'failed_logs' => 0,
                'success_rate' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém contagem de logs por status
     */
    public function getLogCountByStatus(array $filters = []): array
    {
        $this->validateInitialization();

        try {
            $response = $this->httpClient->get('/notifications/logs/count-by-status', [
                'query' => $filters
            ]);

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter contagem por status', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'pending' => 0,
                'sent' => 0,
                'delivered' => 0,
                'failed' => 0,
                'cancelled' => 0
            ];
        }
    }

    /**
     * Obtém principais erros
     */
    public function getTopErrors(int $limit = 10, array $filters = []): array
    {
        $this->validateInitialization();

        try {
            $params = array_merge($filters, [
                'limit' => $limit,
                'status' => 'failed'
            ]);

            $response = $this->httpClient->get('/notifications/logs/top-errors', [
                'query' => $params
            ]);

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter principais erros', [
                'limit' => $limit,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Obtém tendências de logs
     */
    public function getLogTrends(string $period = '7 days', array $filters = []): array
    {
        $this->validateInitialization();

        try {
            $params = array_merge($filters, [
                'period' => $period,
                'group_by' => 'day'
            ]);

            $response = $this->httpClient->get('/notifications/logs/trends', [
                'query' => $params
            ]);

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter tendências de logs', [
                'period' => $period,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Exporta logs para CSV
     */
    public function exportLogs(array $filters = [], string $format = 'csv'): array
    {
        $this->validateInitialization();

        try {
            $params = array_merge($filters, [
                'format' => $format,
                'export' => true
            ]);

            $response = $this->httpClient->get('/notifications/logs/export', [
                'query' => $params
            ]);

            $result = $response->toArray();

            $this->logger->info('Logs exportados', [
                'format' => $format,
                'filters' => $filters,
                'export_url' => $result['download_url'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao exportar logs', [
                'format' => $format,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Limpa logs antigos
     */
    public function cleanupOldLogs(int $daysToKeep = 30, array $options = []): array
    {
        $this->validateInitialization();

        try {
            $params = [
                'days_to_keep' => $daysToKeep,
                'dry_run' => $options['dry_run'] ?? false,
                'only_successful' => $options['only_successful'] ?? false
            ];

            $response = $this->httpClient->post('/notifications/logs/cleanup', [
                'json' => $params
            ]);

            $result = $response->toArray();

            $this->logger->info('Limpeza de logs executada', [
                'days_to_keep' => $daysToKeep,
                'dry_run' => $params['dry_run'],
                'deleted_count' => $result['deleted_count'] ?? 0
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro na limpeza de logs', [
                'days_to_keep' => $daysToKeep,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'deleted_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém logs em tempo real (stream)
     */
    public function streamLogs(array $filters = [], callable $callback = null): array
    {
        $this->validateInitialization();

        try {
            // Implementação básica - em uma versão real, usaria WebSockets ou Server-Sent Events
            $logs = $this->getLogs($filters, 1, 100);

            if ($callback !== null && isset($logs['data'])) {
                foreach ($logs['data'] as $log) {
                    $callback($log);
                }
            }

            return $logs;

        } catch (\Exception $e) {
            $this->logger->error('Erro no stream de logs', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém resumo de logs por webhook
     */
    public function getWebhookLogSummary(string $webhookId): array
    {
        $this->validateInitialization();

        try {
            $response = $this->httpClient->get("/notifications/logs/webhook/{$webhookId}/summary");
            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter resumo de logs do webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage()
            ]);

            return [
                'total_logs' => 0,
                'success_rate' => 0.0,
                'avg_response_time' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enriquece entrada de log com informações adicionais
     */
    private function enrichLogEntry(array $log): array
    {
        // Adiciona informações calculadas
        $log['duration_human'] = $this->formatDuration($log['response_time'] ?? 0);
        $log['is_recent'] = $this->isRecentLog($log['created_at'] ?? '');
        $log['retry_attempts'] = $log['retry_count'] ?? 0;
        $log['has_error'] = !empty($log['error_message']);
        $log['is_successful'] = ($log['status'] ?? '') === 'delivered';

        // Adiciona categoria do erro
        if ($log['has_error']) {
            $log['error_category'] = $this->categorizeError($log['error_message'] ?? '');
        }

        // Adiciona severidade
        $log['severity'] = $this->calculateSeverity($log);

        return $log;
    }

    /**
     * Melhora estatísticas com cálculos adicionais
     */
    private function enhanceStatistics(array $stats): array
    {
        $total = $stats['total_logs'] ?? 0;
        $successful = $stats['successful_logs'] ?? 0;
        $failed = $stats['failed_logs'] ?? 0;

        $stats['success_rate'] = $total > 0 ? round(($successful / $total) * 100, 2) : 0.0;
        $stats['failure_rate'] = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;
        $stats['avg_response_time_formatted'] = $this->formatDuration($stats['avg_response_time'] ?? 0);

        // Adiciona status de saúde
        $stats['health_status'] = $this->calculateHealthStatus($stats['success_rate']);

        return $stats;
    }

    /**
     * Formata duração em formato legível
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000) . 'ms';
        } elseif ($seconds < 60) {
            return round($seconds, 2) . 's';
        } else {
            return round($seconds / 60, 1) . 'min';
        }
    }

    /**
     * Verifica se o log é recente (últimas 24 horas)
     */
    private function isRecentLog(string $createdAt): bool
    {
        if (empty($createdAt)) {
            return false;
        }

        try {
            $logTime = new \DateTime($createdAt);
            $dayAgo = new \DateTime('-1 day');
            return $logTime > $dayAgo;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Categoriza erro baseado na mensagem
     */
    private function categorizeError(string $errorMessage): string
    {
        $errorMessage = strtolower($errorMessage);

        if (strpos($errorMessage, 'timeout') !== false) {
            return 'timeout';
        } elseif (strpos($errorMessage, 'connection') !== false) {
            return 'connection';
        } elseif (strpos($errorMessage, 'dns') !== false) {
            return 'dns';
        } elseif (strpos($errorMessage, 'ssl') !== false || strpos($errorMessage, 'certificate') !== false) {
            return 'ssl';
        } elseif (strpos($errorMessage, '401') !== false || strpos($errorMessage, 'unauthorized') !== false) {
            return 'authentication';
        } elseif (strpos($errorMessage, '403') !== false || strpos($errorMessage, 'forbidden') !== false) {
            return 'authorization';
        } elseif (strpos($errorMessage, '404') !== false || strpos($errorMessage, 'not found') !== false) {
            return 'not_found';
        } elseif (strpos($errorMessage, '5') === 0) { // Códigos 5xx
            return 'server_error';
        } else {
            return 'unknown';
        }
    }

    /**
     * Calcula severidade do log
     */
    private function calculateSeverity(array $log): string
    {
        if (($log['status'] ?? '') === 'delivered') {
            return 'success';
        }

        if (($log['status'] ?? '') === 'failed') {
            $retryCount = $log['retry_count'] ?? 0;
            if ($retryCount >= 3) {
                return 'critical';
            } elseif ($retryCount >= 1) {
                return 'warning';
            } else {
                return 'error';
            }
        }

        return 'info';
    }

    /**
     * Calcula status de saúde baseado na taxa de sucesso
     */
    private function calculateHealthStatus(float $successRate): string
    {
        if ($successRate >= 95) {
            return 'excellent';
        } elseif ($successRate >= 90) {
            return 'good';
        } elseif ($successRate >= 80) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Cache de log
     */
    private function cacheLog(string $logId, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . $logId;
        $this->cache->set($cacheKey, $data, self::STATS_CACHE_TTL);
    }

    /**
     * Obtém log do cache
     */
    private function getCachedLog(string $logId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $logId;
        return $this->cache->get($cacheKey);
    }
}