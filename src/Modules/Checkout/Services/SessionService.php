<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\Services;

use ClubifyCheckout\Services\BaseService;
use Clubify\Checkout\Modules\Checkout\Contracts\SessionRepositoryInterface;
use Clubify\Checkout\Modules\Checkout\DTOs\SessionData;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de Sessões de Checkout
 *
 * Gerencia sessões de checkout incluindo criação, navegação,
 * tracking de eventos e lifecycle management.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de sessão
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseService
 * - I: Interface Segregation - Usa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class SessionService extends BaseService
{
    private const CACHE_TTL = 3600; // 1 hora
    private const SESSION_TTL = 7200; // 2 horas
    private const EVENTS_LIMIT = 100;

    public function __construct(
        private SessionRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
    }

    /**
     * Cria nova sessão de checkout
     */
    public function create(string $organizationId, array $data = []): array
    {
        return $this->executeWithMetrics('session_create', function () use ($organizationId, $data) {
            $sessionData = SessionData::forCreation($organizationId, $data);
            $sessionData->validate();

            // Gera token único
            $token = $this->generateSessionToken();
            $sessionData->token = $token;

            // Define TTL
            $sessionData->expires_at = date('Y-m-d H:i:s', time() + self::SESSION_TTL);

            $session = $this->repository->create($sessionData->toArray());

            // Cache da sessão
            $this->cacheSession($session['id'], $session);

            // Registra evento de criação
            $this->addEvent($session['id'], [
                'type' => 'session_created',
                'data' => ['organization_id' => $organizationId],
                'timestamp' => time()
            ]);

            $this->logger->info('Sessão de checkout criada', [
                'session_id' => $session['id'],
                'organization_id' => $organizationId,
                'token' => $token
            ]);

            return $session;
        });
    }

    /**
     * Busca sessão por ID
     */
    public function find(string $id): ?array
    {
        return $this->getCachedOrExecute("session_{$id}", function () use ($id) {
            return $this->repository->find($id);
        }, self::CACHE_TTL);
    }

    /**
     * Busca sessão por token
     */
    public function findByToken(string $token): ?array
    {
        return $this->getCachedOrExecute("session_token_{$token}", function () use ($token) {
            return $this->repository->findByToken($token);
        }, self::CACHE_TTL);
    }

    /**
     * Atualiza sessão
     */
    public function update(string $id, array $data): array
    {
        return $this->executeWithMetrics('session_update', function () use ($id, $data) {
            $sessionData = new SessionData($data);
            $sessionData->validate();

            $session = $this->repository->update($id, $sessionData->toArray());

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de atualização
            $this->addEvent($id, [
                'type' => 'session_updated',
                'data' => array_keys($data),
                'timestamp' => time()
            ]);

            $this->logger->info('Sessão atualizada', [
                'session_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $session;
        });
    }

    /**
     * Atualiza status da sessão
     */
    public function updateStatus(string $id, string $status): array
    {
        return $this->executeWithMetrics('session_update_status', function () use ($id, $status) {
            $session = $this->repository->updateStatus($id, $status);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de mudança de status
            $this->addEvent($id, [
                'type' => 'status_changed',
                'data' => ['new_status' => $status],
                'timestamp' => time()
            ]);

            $this->logger->info('Status da sessão atualizado', [
                'session_id' => $id,
                'new_status' => $status
            ]);

            return $session;
        });
    }

    /**
     * Atualiza dados do cliente
     */
    public function updateCustomerData(string $id, array $customerData): array
    {
        return $this->executeWithMetrics('session_update_customer', function () use ($id, $customerData) {
            $session = $this->repository->updateCustomerData($id, $customerData);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de atualização de cliente
            $this->addEvent($id, [
                'type' => 'customer_updated',
                'data' => ['has_email' => !empty($customerData['email'])],
                'timestamp' => time()
            ]);

            $this->logger->info('Dados do cliente atualizados na sessão', [
                'session_id' => $id,
                'customer_email' => $customerData['email'] ?? null
            ]);

            return $session;
        });
    }

    /**
     * Atualiza dados de pagamento
     */
    public function updatePaymentData(string $id, array $paymentData): array
    {
        return $this->executeWithMetrics('session_update_payment', function () use ($id, $paymentData) {
            $session = $this->repository->updatePaymentData($id, $paymentData);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de atualização de pagamento
            $this->addEvent($id, [
                'type' => 'payment_updated',
                'data' => ['method' => $paymentData['method'] ?? null],
                'timestamp' => time()
            ]);

            $this->logger->info('Dados de pagamento atualizados na sessão', [
                'session_id' => $id,
                'payment_method' => $paymentData['method'] ?? null
            ]);

            return $session;
        });
    }

    /**
     * Atualiza dados de envio
     */
    public function updateShippingData(string $id, array $shippingData): array
    {
        return $this->executeWithMetrics('session_update_shipping', function () use ($id, $shippingData) {
            $session = $this->repository->updateShippingData($id, $shippingData);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de atualização de envio
            $this->addEvent($id, [
                'type' => 'shipping_updated',
                'data' => ['method' => $shippingData['method'] ?? null],
                'timestamp' => time()
            ]);

            $this->logger->info('Dados de envio atualizados na sessão', [
                'session_id' => $id,
                'shipping_method' => $shippingData['method'] ?? null
            ]);

            return $session;
        });
    }

    /**
     * Adiciona evento à sessão
     */
    public function addEvent(string $id, array $event): array
    {
        return $this->executeWithMetrics('session_add_event', function () use ($id, $event) {
            $event['id'] = uniqid('evt_');
            $event['timestamp'] = $event['timestamp'] ?? time();

            $session = $this->repository->addEvent($id, $event);

            // Limita número de eventos para evitar crescimento excessivo
            $this->limitSessionEvents($id);

            // Atualiza cache
            $this->cacheSession($id, $session);

            return $session;
        });
    }

    /**
     * Obtém eventos da sessão
     */
    public function getEvents(string $id): array
    {
        return $this->getCachedOrExecute("session_events_{$id}", function () use ($id) {
            return $this->repository->getEvents($id);
        }, self::CACHE_TTL);
    }

    /**
     * Marca sessão como abandonada
     */
    public function markAsAbandoned(string $id): array
    {
        return $this->executeWithMetrics('session_abandon', function () use ($id) {
            $session = $this->repository->markAsAbandoned($id);

            // Remove do cache (sessão abandonada)
            $this->clearCacheByPattern("session_{$id}*");

            // Registra evento de abandono
            $this->addEvent($id, [
                'type' => 'session_abandoned',
                'data' => ['abandoned_at' => time()],
                'timestamp' => time()
            ]);

            $this->logger->info('Sessão marcada como abandonada', [
                'session_id' => $id
            ]);

            return $session;
        });
    }

    /**
     * Completa sessão (finaliza checkout)
     */
    public function complete(string $id): array
    {
        return $this->executeWithMetrics('session_complete', function () use ($id) {
            $session = $this->repository->markAsCompleted($id);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de conclusão
            $this->addEvent($id, [
                'type' => 'session_completed',
                'data' => ['completed_at' => time()],
                'timestamp' => time()
            ]);

            $this->logger->info('Sessão completada', [
                'session_id' => $id
            ]);

            return $session;
        });
    }

    /**
     * Renova sessão (estende TTL)
     */
    public function renew(string $id, int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? self::SESSION_TTL;

        return $this->executeWithMetrics('session_renew', function () use ($id, $ttlSeconds) {
            $session = $this->repository->renew($id, $ttlSeconds);

            // Atualiza cache
            $this->cacheSession($id, $session);

            // Registra evento de renovação
            $this->addEvent($id, [
                'type' => 'session_renewed',
                'data' => ['ttl_seconds' => $ttlSeconds],
                'timestamp' => time()
            ]);

            return $session;
        });
    }

    /**
     * Expira sessão
     */
    public function expire(string $id): array
    {
        return $this->executeWithMetrics('session_expire', function () use ($id) {
            $session = $this->repository->expire($id);

            // Remove do cache
            $this->clearCacheByPattern("session_{$id}*");

            $this->logger->info('Sessão expirada', [
                'session_id' => $id
            ]);

            return $session;
        });
    }

    /**
     * Busca sessões por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        return $this->repository->findByOrganization($organizationId, $filters);
    }

    /**
     * Busca sessões ativas
     */
    public function findActive(array $filters = []): array
    {
        return $this->repository->findActive($filters);
    }

    /**
     * Busca sessões por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array
    {
        return $this->repository->findByCustomer($customerId, $filters);
    }

    /**
     * Limpa sessões expiradas
     */
    public function cleanupExpired(): int
    {
        return $this->executeWithMetrics('session_cleanup', function () {
            $count = $this->repository->cleanupExpired();

            $this->logger->info('Sessões expiradas limpas', [
                'cleaned_count' => $count
            ]);

            return $count;
        });
    }

    /**
     * Obtém estatísticas de sessões
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->getCachedOrExecute('session_statistics', function () use ($filters) {
            return $this->repository->getStatistics($filters);
        }, 300); // Cache por 5 minutos
    }

    /**
     * Obtém contagem por status
     */
    public function getCountByStatus(): array
    {
        return $this->getCachedOrExecute('session_count_by_status', function () {
            return $this->repository->countByStatus();
        }, 300);
    }

    /**
     * Verifica se sessão é válida
     */
    public function isValid(string $id): bool
    {
        $session = $this->find($id);

        if (!$session) {
            return false;
        }

        if ($session['status'] === 'expired' || $session['status'] === 'abandoned') {
            return false;
        }

        if (!empty($session['expires_at']) && strtotime($session['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Gera token único para sessão
     */
    private function generateSessionToken(): string
    {
        return 'cs_' . bin2hex(random_bytes(32));
    }

    /**
     * Cache da sessão
     */
    private function cacheSession(string $id, array $session): void
    {
        $cacheKey = "session_{$id}";
        $this->setCacheItem($cacheKey, $session, self::CACHE_TTL);

        // Cache também por token se existir
        if (!empty($session['token'])) {
            $tokenCacheKey = "session_token_{$session['token']}";
            $this->setCacheItem($tokenCacheKey, $session, self::CACHE_TTL);
        }
    }

    /**
     * Limita número de eventos na sessão
     */
    private function limitSessionEvents(string $id): void
    {
        $events = $this->getEvents($id);

        if (count($events) > self::EVENTS_LIMIT) {
            // Mantém apenas os últimos eventos
            $eventsToKeep = array_slice($events, -self::EVENTS_LIMIT);

            $this->logger->info('Eventos da sessão limitados', [
                'session_id' => $id,
                'total_events' => count($events),
                'events_kept' => count($eventsToKeep)
            ]);
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge(parent::getMetrics(), [
            'session_ttl' => self::SESSION_TTL,
            'events_limit' => self::EVENTS_LIMIT,
            'cache_ttl' => self::CACHE_TTL
        ]);
    }
}