<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Repositories;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Security\SecurityValidator;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\ValidationException;

/**
 * Repositório de ofertas via API
 *
 * Implementa a interface OfferRepositoryInterface fazendo
 * chamadas para a API do Clubify Checkout
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de ofertas
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa OfferRepositoryInterface
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class ApiOfferRepository implements OfferRepositoryInterface
{
    private Configuration $config;
    private Logger $logger;
    private Client $httpClient;

    public function __construct(
        Configuration $config,
        Logger $logger,
        Client $httpClient
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * Criar nova oferta
     */
    public function create(array $data): array
    {
        try {
            // Security: Sanitize input data to prevent XSS and injection attacks
            $data = SecurityValidator::sanitizeInput($data);

            $this->logger->info('Creating offer', ['data_keys' => array_keys($data)]);

            $response = $this->httpClient->post('/offers', $data);
            $offer = $response->getData();

            $this->logger->info('Offer created successfully', [
                'offer_id' => $offer['id'] ?? null
            ]);

            return $offer;
        } catch (HttpException $e) {
            $this->logger->error('Failed to create offer', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
            throw $e;
        }
    }

    /**
     * Buscar oferta por ID
     */
    public function findById(string $id): ?array
    {
        try {
            // Security: Validate and sanitize offer ID
            $id = SecurityValidator::sanitizeInput($id);
            if (!SecurityValidator::validateUuid($id)) {
                throw new \InvalidArgumentException('Invalid offer ID format');
            }

            $response = $this->httpClient->get("/offers/{$id}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Buscar oferta por slug
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            // Security: Sanitize slug input
            $slug = SecurityValidator::sanitizeInput($slug);
            // Validate slug format (alphanumeric with hyphens)
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
                throw new \InvalidArgumentException('Invalid slug format');
            }

            $response = $this->httpClient->get("/offers/slug/{$slug}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualizar oferta
     */
    public function update(string $id, array $data): array
    {
        try {
            // Security: Validate and sanitize inputs
            $id = SecurityValidator::sanitizeInput($id);
            if (!SecurityValidator::validateUuid($id)) {
                throw new \InvalidArgumentException('Invalid offer ID format');
            }
            $data = SecurityValidator::sanitizeInput($data);

            $this->logger->info('Updating offer', [
                'offer_id' => $id,
                'data_keys' => array_keys($data)
            ]);

            $response = $this->httpClient->put("/offers/{$id}", $data);
            $offer = $response->getData();

            $this->logger->info('Offer updated successfully', [
                'offer_id' => $id
            ]);

            return $offer;
        } catch (HttpException $e) {
            $this->logger->error('Failed to update offer', [
                'offer_id' => $id,
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
            throw $e;
        }
    }

    /**
     * Excluir oferta
     */
    public function delete(string $id): bool
    {
        try {
            $this->logger->info('Deleting offer', ['offer_id' => $id]);

            $response = $this->httpClient->delete("/offers/{$id}");
            $success = $response->getStatusCode() === 204;

            if ($success) {
                $this->logger->info('Offer deleted successfully', [
                    'offer_id' => $id
                ]);
            }

            return $success;
        } catch (HttpException $e) {
            $this->logger->error('Failed to delete offer', [
                'offer_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Listar ofertas com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        try {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/offers', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to list offers', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Contar ofertas com filtros
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->httpClient->get('/offers/count', [
                'query' => $filters
            ]);
            $data = $response->getData();
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count offers', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Buscar ofertas por organização
     */
    public function findByOrganization(string $organizationId): array
    {
        try {
            $response = $this->httpClient->get('/offers', [
                'query' => ['organization_id' => $organizationId]
            ]);
            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find offers by organization', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Buscar ofertas ativas
     */
    public function findActive(): array
    {
        try {
            $response = $this->httpClient->get('/offers', [
                'query' => ['status' => 'active']
            ]);
            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find active offers', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Configurar tema da oferta
     */
    public function updateTheme(string $id, array $themeConfig): array
    {
        try {
            $this->logger->info('Updating offer theme', [
                'offer_id' => $id,
                'theme_config' => $themeConfig
            ]);

            $response = $this->httpClient->put("/offers/{$id}/config/theme", [
                'theme' => $themeConfig
            ]);

            $result = $response->getData();

            $this->logger->info('Offer theme updated successfully', [
                'offer_id' => $id
            ]);

            return $result;
        } catch (HttpException $e) {
            $this->logger->error('Failed to update offer theme', [
                'offer_id' => $id,
                'error' => $e->getMessage(),
                'theme_config' => $themeConfig
            ]);
            throw $e;
        }
    }

    /**
     * Configurar layout da oferta
     */
    public function updateLayout(string $id, array $layoutConfig): array
    {
        try {
            $this->logger->info('Updating offer layout', [
                'offer_id' => $id,
                'layout_config' => $layoutConfig
            ]);

            $response = $this->httpClient->put("/offers/{$id}/config/layout", [
                'layout' => $layoutConfig
            ]);

            $result = $response->getData();

            $this->logger->info('Offer layout updated successfully', [
                'offer_id' => $id
            ]);

            return $result;
        } catch (HttpException $e) {
            $this->logger->error('Failed to update offer layout', [
                'offer_id' => $id,
                'error' => $e->getMessage(),
                'layout_config' => $layoutConfig
            ]);
            throw $e;
        }
    }

    /**
     * Obter upsells da oferta
     */
    public function getUpsells(string $offerId): array
    {
        try {
            $response = $this->httpClient->get("/offers/{$offerId}/upsells");
            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get offer upsells', [
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Adicionar upsell à oferta
     */
    public function addUpsell(string $offerId, array $upsellData): array
    {
        try {
            $this->logger->info('Adding upsell to offer', [
                'offer_id' => $offerId,
                'upsell_data' => $upsellData
            ]);

            $response = $this->httpClient->post("/offers/{$offerId}/upsells", $upsellData);
            $upsell = $response->getData();

            $this->logger->info('Upsell added to offer successfully', [
                'offer_id' => $offerId,
                'upsell_id' => $upsell['id'] ?? null
            ]);

            return $upsell;
        } catch (HttpException $e) {
            $this->logger->error('Failed to add upsell to offer', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
                'upsell_data' => $upsellData
            ]);
            throw $e;
        }
    }

    /**
     * Remover upsell da oferta
     */
    public function removeUpsell(string $offerId, string $upsellId): bool
    {
        try {
            $this->logger->info('Removing upsell from offer', [
                'offer_id' => $offerId,
                'upsell_id' => $upsellId
            ]);

            $response = $this->httpClient->delete("/offers/{$offerId}/upsells/{$upsellId}");
            $success = $response->getStatusCode() === 204;

            if ($success) {
                $this->logger->info('Upsell removed from offer successfully', [
                    'offer_id' => $offerId,
                    'upsell_id' => $upsellId
                ]);
            }

            return $success;
        } catch (HttpException $e) {
            $this->logger->error('Failed to remove upsell from offer', [
                'offer_id' => $offerId,
                'upsell_id' => $upsellId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obter planos de assinatura da oferta
     */
    public function getSubscriptionPlans(string $offerId): array
    {
        try {
            $response = $this->httpClient->get("/offers/{$offerId}/subscription/plans");
            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get offer subscription plans', [
                'offer_id' => $offerId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Adicionar plano de assinatura à oferta
     */
    public function addSubscriptionPlan(string $offerId, array $planData): array
    {
        try {
            $this->logger->info('Adding subscription plan to offer', [
                'offer_id' => $offerId,
                'plan_data' => $planData
            ]);

            $response = $this->httpClient->post("/offers/{$offerId}/subscription/plans", $planData);
            $plan = $response->getData();

            $this->logger->info('Subscription plan added to offer successfully', [
                'offer_id' => $offerId,
                'plan_id' => $plan['id'] ?? null
            ]);

            return $plan;
        } catch (HttpException $e) {
            $this->logger->error('Failed to add subscription plan to offer', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
                'plan_data' => $planData
            ]);
            throw $e;
        }
    }

    /**
     * Obter estatísticas da oferta
     */
    public function getStats(string $id): array
    {
        try {
            $response = $this->httpClient->get("/offers/{$id}/stats");
            return $response->getData() ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get offer stats', [
                'offer_id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obter dados públicos da oferta
     */
    public function getPublicData(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/offers/public/{$slug}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            $this->logger->error('Failed to get public offer data', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}