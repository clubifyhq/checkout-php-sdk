<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Modules\UserManagement\Contracts\DomainRepositoryInterface;
use Clubify\Checkout\Modules\UserManagement\DTOs\DomainData;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Repository para domínios via API
 *
 * Implementa DomainRepositoryInterface estendendo BaseRepository
 * para operações de domínios via chamadas HTTP para a API.
 * Segue os princípios SOLID e Repository Pattern.
 */
class ApiDomainRepository extends BaseRepository implements DomainRepositoryInterface
{
    /**
     * Endpoint base para domínios
     */
    protected string $baseEndpoint = '/api/v1/domains';

    /**
     * DTO class para mapeamento de dados
     */
    protected string $dtoClass = DomainData::class;

    /**
     * Obtém o endpoint base para o recurso
     */
    protected function getEndpoint(): string
    {
        return $this->baseEndpoint;
    }

    /**
     * Obtém o nome do recurso
     */
    protected function getResourceName(): string
    {
        return 'domains';
    }

    /**
     * Busca domínios por tenant
     */
    public function findByTenantId(string $tenantId): array
    {
        $this->logger->debug('Finding domains by tenant ID', ['tenant_id' => $tenantId]);

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/tenant/{$tenantId}");

            $data = $response;
            $this->logger->info('Domains found by tenant ID', [
                'tenant_id' => $tenantId,
                'count' => count($data['data'] ?? [])
            ]);

            return $data['data'] ?? [];

        } catch (\Exception $e) {
            $this->logger->error('Failed to find domains by tenant ID', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Busca domínio por nome
     */
    public function findByDomain(string $domain): ?array
    {
        $this->logger->debug('Finding domain by name', ['domain' => $domain]);

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/search?" . http_build_query(['domain' => $domain]));

            $data = $response;
            $domain = $data['data'] ?? null;

            $this->logger->info('Domain search completed', [
                'domain' => $domain,
                'found' => $domain !== null
            ]);

            return $domain;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find domain by name', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Busca domínio por token de verificação
     */
    public function findByVerificationToken(string $token): ?array
    {
        $this->logger->debug('Finding domain by verification token', ['token' => substr($token, 0, 10) . '...']);

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/verify?" . http_build_query(['token' => $token]));

            $data = $response;
            $domain = $data['data'] ?? null;

            $this->logger->info('Domain verification token search completed', [
                'token' => substr($token, 0, 10) . '...',
                'found' => $domain !== null
            ]);

            return $domain;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find domain by verification token', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza status de verificação
     */
    public function updateVerificationStatus(string $domainId, string $status, ?array $metadata = null): bool
    {
        $this->logger->debug('Updating domain verification status', [
            'domain_id' => $domainId,
            'status' => $status
        ]);

        try {
            $payload = [
                'status' => $status,
                'verified' => $status === 'verified',
                'verified_at' => $status === 'verified' ? date('c') : null,
            ];

            if ($metadata !== null) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->makeHttpRequest('PATCH', "{$this->baseEndpoint}/{$domainId}/verification", $payload);

            // Response handled by makeHttpRequest
            $this->logger->info('Domain verification status updated', [
                'domain_id' => $domainId,
                'status' => $status
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update domain verification status', [
                'domain_id' => $domainId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Lista domínios verificados de um tenant
     */
    public function getVerifiedDomains(string $tenantId): array
    {
        $this->logger->debug('Getting verified domains', ['tenant_id' => $tenantId]);

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/tenant/{$tenantId}/verified");

            $data = $response;
            $domains = $data['data'] ?? [];

            $this->logger->info('Verified domains retrieved', [
                'tenant_id' => $tenantId,
                'count' => count($domains)
            ]);

            return $domains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get verified domains', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lista domínios pendentes de verificação
     */
    public function getPendingVerification(): array
    {
        $this->logger->debug('Getting pending verification domains');

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/pending");

            $data = $response;
            $domains = $data['data'] ?? [];

            $this->logger->info('Pending verification domains retrieved', [
                'count' => count($domains)
            ]);

            return $domains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get pending verification domains', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verifica se um domínio já existe
     */
    public function domainExists(string $domain): bool
    {
        try {
            $result = $this->findByDomain($domain);
            return $result !== null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check if domain exists', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza configurações DNS
     */
    public function updateDnsRecords(string $domainId, array $records): bool
    {
        $this->logger->debug('Updating DNS records', [
            'domain_id' => $domainId,
            'records_count' => count($records)
        ]);

        try {
            $response = $this->makeHttpRequest('PATCH', "{$this->baseEndpoint}/{$domainId}/dns", ['dns_records' => $records]);

            // Response handled by makeHttpRequest
            $this->logger->info('DNS records updated', [
                'domain_id' => $domainId,
                'records_count' => count($records)
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update DNS records', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza configurações SSL
     */
    public function updateSslConfig(string $domainId, array $config): bool
    {
        $this->logger->debug('Updating SSL config', [
            'domain_id' => $domainId
        ]);

        try {
            $response = $this->makeHttpRequest('PATCH', "{$this->baseEndpoint}/{$domainId}/ssl", ['ssl_config' => $config]);

            // Response handled by makeHttpRequest
            $this->logger->info('SSL config updated', [
                'domain_id' => $domainId
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update SSL config', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remove domínios expirados/inativos
     */
    public function cleanupInactiveDomains(int $daysInactive = 30): int
    {
        $this->logger->debug('Cleaning up inactive domains', ['days_inactive' => $daysInactive]);

        try {
            $response = $this->makeHttpRequest('DELETE', "{$this->baseEndpoint}/cleanup", ['days_inactive' => $daysInactive]);

            $data = $response;
            $count = $data['deleted_count'] ?? 0;

            $this->logger->info('Inactive domains cleaned up', [
                'days_inactive' => $daysInactive,
                'deleted_count' => $count
            ]);

            return $count;

        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup inactive domains', [
                'days_inactive' => $daysInactive,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Obtém estatísticas de domínios
     */
    public function getStats(string $tenantId): array
    {
        $this->logger->debug('Getting domain stats', ['tenant_id' => $tenantId]);

        try {
            $response = $this->makeHttpRequest('GET', "{$this->baseEndpoint}/tenant/{$tenantId}/stats");

            $data = $response;
            $stats = $data['data'] ?? [];

            $this->logger->info('Domain stats retrieved', [
                'tenant_id' => $tenantId
            ]);

            return $stats;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get domain stats', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Faz uma requisição HTTP
     */
    protected function makeHttpRequest(string $method, string $uri, array $data = []): array
    {
        try {
            $options = [];
            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $responseData = ResponseHelper::getData($response);
            if ($responseData === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $responseData;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }
}