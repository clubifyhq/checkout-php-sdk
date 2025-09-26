<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Factories;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiTenantRepository;
use Clubify\Checkout\Modules\UserManagement\Facades\TenantFacade;
use Clubify\Checkout\Modules\UserManagement\Contracts\TenantRepositoryInterface;

/**
 * Factory para criação de TenantService e componentes relacionados
 *
 * Centraliza a lógica de criação e configuração dos componentes
 * do tenant management, seguindo o padrão Factory.
 */
class TenantServiceFactory
{
    /**
     * Cria uma instância do TenantService com todas as dependências
     */
    public static function create(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): TenantService {
        $repository = self::createRepository($config, $logger, $httpClient, $cache, $eventDispatcher);

        return new TenantService(
            $repository,
            $config,
            $logger
        );
    }

    /**
     * Cria uma instância do TenantRepository
     */
    public static function createRepository(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): TenantRepositoryInterface {
        return new ApiTenantRepository(
            $config,
            $logger,
            $httpClient,
            $cache,
            $eventDispatcher
        );
    }

    /**
     * Cria uma instância do TenantFacade
     */
    public static function createFacade(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): TenantFacade {
        return new TenantFacade(
            $config,
            $logger,
            $httpClient,
            $cache,
            $eventDispatcher
        );
    }

    /**
     * Cria instância de TenantService com configuração mínima
     * para casos onde as dependências não estão completamente disponíveis
     */
    public static function createMinimal(
        Configuration $config,
        Logger $logger
    ): TenantService {
        // Criar dependências básicas se não fornecidas
        $httpClient = new Client($config, $logger);

        // Cache manager básico (implementação padrão)
        $cache = new \Clubify\Checkout\Core\Cache\CacheManager($config);

        // Event dispatcher básico
        $eventDispatcher = new \Clubify\Checkout\Core\Events\EventDispatcher();

        return self::create($config, $logger, $httpClient, $cache, $eventDispatcher);
    }

    /**
     * Cria instância customizada do TenantService com repository específico
     */
    public static function createWithRepository(
        TenantRepositoryInterface $repository,
        Configuration $config,
        Logger $logger
    ): TenantService {
        return new TenantService(
            $repository,
            $config,
            $logger
        );
    }

    /**
     * Cria instância para testes com mock repository
     */
    public static function createForTesting(
        Configuration $config,
        Logger $logger,
        ?TenantRepositoryInterface $mockRepository = null
    ): TenantService {
        if ($mockRepository === null) {
            // Criar um mock repository básico se não fornecido
            $mockRepository = new class implements TenantRepositoryInterface {
                public function findById(string $id): ?array { return ['id' => $id]; }
                public function findByIds(array $ids): array { return []; }
                public function findAll(int $limit = 100, int $offset = 0): array { return []; }
                public function findBy(array $criteria, int $limit = 100, int $offset = 0): array { return []; }
                public function findOneBy(array $criteria): ?array { return null; }
                public function create(array $data): array { return array_merge($data, ['id' => 'test-id']); }
                public function update(string $id, array $data): array { return array_merge($data, ['id' => $id]); }
                public function delete(string $id): bool { return true; }
                public function count(array $criteria = []): int { return 0; }
                public function exists(string $id): bool { return true; }
                public function healthCheck(): bool { return true; }
                public function findBySlug(string $slug): ?array { return null; }
                public function findByDomain(string $domain): ?array { return null; }
                public function findByStatus(string $status): array { return []; }
                public function findByPlan(string $plan): array { return []; }
                public function updateSettings(string $tenantId, array $settings): array { return []; }
                public function addDomain(string $tenantId, array $domainData): array { return []; }
                public function removeDomain(string $tenantId, string $domain): bool { return true; }
                public function suspend(string $tenantId, string $reason = ''): bool { return true; }
                public function reactivate(string $tenantId): bool { return true; }
                public function isSlugAvailable(string $slug, ?string $excludeTenantId = null): bool { return true; }
                public function isDomainAvailable(string $domain, ?string $excludeTenantId = null): bool { return true; }
                public function getTenantStats(): array { return []; }
                public function findExpiringPlans(int $daysThreshold = 30): array { return []; }
            };
        }

        return new TenantService(
            $mockRepository,
            $config,
            $logger
        );
    }

    /**
     * Valida se todas as dependências estão disponíveis
     */
    public static function validateDependencies(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): bool {
        return $config !== null &&
               $logger !== null &&
               $httpClient !== null &&
               $cache !== null &&
               $eventDispatcher !== null;
    }

    /**
     * Obtém informações sobre o factory
     */
    public static function getFactoryInfo(): array
    {
        return [
            'factory' => self::class,
            'version' => '1.0.0',
            'supported_services' => [
                'TenantService',
                'ApiTenantRepository',
                'TenantFacade'
            ],
            'creation_methods' => [
                'create',
                'createRepository',
                'createFacade',
                'createMinimal',
                'createWithRepository',
                'createForTesting'
            ],
            'timestamp' => time()
        ];
    }
}