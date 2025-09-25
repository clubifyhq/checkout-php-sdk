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
    protected string $baseEndpoint = 'domains';

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
     * Cria um novo domínio usando o formato correto do backend
     */
    public function create(array $data): array
    {
        $this->logger->debug('Creating domain', ['data' => $data]);

        try {
            // Transform data to match backend API format
            $payload = [
                'customDomain' => $data['domain'] ?? '',
                'verificationMethod' => $data['verification_method'] ?? 'dns',
                'tenantId' => $data['tenant_id'] ?? ''
            ];

            // Build headers with tenant ID
            $headers = [];
            if (!empty($payload['tenantId'])) {
                $headers['X-Tenant-Id'] = $payload['tenantId'];
            }

            $response = $this->makeHttpRequestWithHeaders('POST', $this->getEndpoint(), $payload, $headers);

            $this->logger->info('Domain created successfully', [
                'domain' => $payload['customDomain'],
                'tenant_id' => $payload['tenantId']
            ]);

            // Transform response back to match expected format
            return [
                'id' => $response['domain'] ?? $payload['customDomain'], // Use domain as ID since backend doesn't return proper ID
                'domain' => $response['domain'] ?? $payload['customDomain'],
                'tenant_id' => $payload['tenantId'],
                'status' => $response['verificationStatus'] ?? 'pending_verification',
                'verification_token' => $response['verificationToken'] ?? '',
                'created_at' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create domain', [
                'domain' => $data['domain'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Busca domínios por tenant (não implementado no backend)
     */
    public function findByTenantId(string $tenantId): array
    {
        $this->logger->debug('Finding domains by tenant ID - not implemented', ['tenant_id' => $tenantId]);
        $this->logger->warning('findByTenantId not implemented in backend API');
        return [];
    }

    /**
     * Busca domínio por nome usando o endpoint de status
     */
    public function findByDomain(string $domain, ?string $tenantId = null): ?array
    {
        $this->logger->debug('Finding domain by name', ['domain' => $domain, 'tenant_id' => $tenantId]);

        try {
            // Build headers with tenant ID if provided
            $headers = [];
            if ($tenantId) {
                $headers['X-Tenant-Id'] = $tenantId;
            }

            // Use the status endpoint to check if domain exists
            $response = $this->makeHttpRequestWithHeaders('GET', "{$this->baseEndpoint}/{$domain}/status", [], $headers);

            $this->logger->info('Domain found', [
                'domain' => $domain,
                'status' => $response['verificationStatus'] ?? 'unknown'
            ]);

            return $response;

        } catch (HttpException $e) {
            if ($e->getCode() === 404) {
                $this->logger->info('Domain not found', ['domain' => $domain]);
                return null;
            }

            $this->logger->error('Failed to find domain by name', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find domain by name', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Busca domínio por token de verificação (não implementado no backend)
     */
    public function findByVerificationToken(string $token): ?array
    {
        $this->logger->debug('Finding domain by verification token - not implemented', ['token' => substr($token, 0, 10) . '...']);

        // This endpoint doesn't exist in the backend, return null
        $this->logger->warning('findByVerificationToken not implemented in backend API');
        return null;
    }

    /**
     * Verifica um domínio usando o endpoint de verificação
     */
    public function updateVerificationStatus(string $domainId, string $status, ?array $metadata = null): bool
    {
        $this->logger->debug('Verifying domain', [
            'domain_id' => $domainId,
            'status' => $status
        ]);

        try {
            // Build headers with tenant ID if available in metadata
            $headers = [];
            if ($metadata && !empty($metadata['tenant_id'])) {
                $headers['X-Tenant-Id'] = $metadata['tenant_id'];
            }

            // Use POST /domains/:domain/verify endpoint
            $response = $this->makeHttpRequestWithHeaders('POST', "{$this->baseEndpoint}/{$domainId}/verify", [], $headers);

            // Response handled by makeHttpRequest
            $this->logger->info('Domain verification completed', [
                'domain_id' => $domainId,
                'response' => $response
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to verify domain', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Lista domínios verificados de um tenant (não implementado no backend)
     */
    public function getVerifiedDomains(string $tenantId): array
    {
        $this->logger->debug('Getting verified domains - not implemented', ['tenant_id' => $tenantId]);
        $this->logger->warning('getVerifiedDomains not implemented in backend API');
        return [];
    }

    /**
     * Lista domínios pendentes de verificação (não implementado no backend)
     */
    public function getPendingVerification(): array
    {
        $this->logger->debug('Getting pending verification domains - not implemented');
        $this->logger->warning('getPendingVerification not implemented in backend API');
        return [];
    }

    /**
     * Verifica se um domínio já existe
     */
    public function domainExists(string $domain, ?string $tenantId = null): bool
    {
        try {
            $result = $this->findByDomain($domain, $tenantId);
            return $result !== null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check if domain exists', [
                'domain' => $domain,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza configurações DNS (não implementado no backend)
     */
    public function updateDnsRecords(string $domainId, array $records): bool
    {
        $this->logger->debug('Updating DNS records - not implemented', [
            'domain_id' => $domainId,
            'records_count' => count($records)
        ]);
        $this->logger->warning('updateDnsRecords not implemented in backend API');
        return true; // Return success for compatibility
    }

    /**
     * Atualiza configurações SSL usando endpoint de SSL
     */
    public function updateSslConfig(string $domainId, array $config): bool
    {
        $this->logger->debug('Updating SSL config', [
            'domain_id' => $domainId
        ]);

        try {
            // Build headers with tenant ID if available in config
            $headers = [];
            if (!empty($config['tenant_id'])) {
                $headers['X-Tenant-Id'] = $config['tenant_id'];
            }

            // Use POST /domains/:domain/ssl/provision endpoint
            $response = $this->makeHttpRequestWithHeaders('POST', "{$this->baseEndpoint}/{$domainId}/ssl/provision", [], $headers);

            $this->logger->info('SSL provisioning initiated', [
                'domain_id' => $domainId,
                'response' => $response
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to provision SSL', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remove domínios expirados/inativos (não implementado no backend)
     */
    public function cleanupInactiveDomains(int $daysInactive = 30): int
    {
        $this->logger->debug('Cleaning up inactive domains - not implemented', ['days_inactive' => $daysInactive]);
        $this->logger->warning('cleanupInactiveDomains not implemented in backend API');
        return 0;
    }

    /**
     * Obtém estatísticas de domínios (não implementado no backend)
     */
    public function getStats(string $tenantId): array
    {
        $this->logger->debug('Getting domain stats - not implemented', ['tenant_id' => $tenantId]);
        $this->logger->warning('getStats not implemented in backend API');
        return [];
    }

    /**
     * Faz uma requisição HTTP
     */
    protected function makeHttpRequest(string $method, string $uri, array $data = []): array
    {
        return $this->makeHttpRequestWithHeaders($method, $uri, $data, []);
    }

    /**
     * Faz uma requisição HTTP com headers customizados
     */
    protected function makeHttpRequestWithHeaders(string $method, string $uri, array $data = [], array $customHeaders = []): array
    {
        try {
            $options = [];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            if (!empty($customHeaders)) {
                $options['headers'] = $customHeaders;
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