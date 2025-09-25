<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\UserManagement\Contracts\DomainRepositoryInterface;
use Clubify\Checkout\Modules\UserManagement\DTOs\DomainData;
use Clubify\Checkout\Modules\UserManagement\Exceptions\DomainValidationException;
use Clubify\Checkout\Modules\UserManagement\Exceptions\DomainNotFoundException;

/**
 * Service para gerenciamento de domínios
 *
 * Implementa a lógica de negócio para operações com domínios customizados
 * no sistema multi-tenant. Inclui configuração, verificação e gerenciamento
 * de domínios.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas domínios
 * - O: Open/Closed - Extensível via injeção de dependência
 * - L: Liskov Substitution - Pode ser substituído
 * - I: Interface Segregation - Métodos específicos de domínio
 * - D: Dependency Inversion - Depende de abstrações (repository interface)
 */
class DomainService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger,
        private DomainRepositoryInterface $repository
    ) {
    }

    /**
     * Configura um novo domínio para um tenant
     */
    public function configureDomain(string $tenantId, array $domainData): array
    {
        $this->logger->info('Configuring domain', [
            'tenant_id' => $tenantId,
            'domain' => $domainData['domain'] ?? 'unknown'
        ]);

        try {
            // Validação dos dados de entrada
            $this->validateDomainData($domainData);

            // Verifica se o domínio já existe
            if ($this->repository->domainExists($domainData['domain'])) {
                throw new DomainValidationException("Domain '{$domainData['domain']}' already exists");
            }

            // Cria DTO para validação
            $domainDto = new DomainData();
            $domainDto->tenant_id = $tenantId;
            $domainDto->domain = $domainData['domain'];
            $domainDto->verification_method = $domainData['verification_method'] ?? 'dns';
            $domainDto->settings = $domainData['settings'] ?? [];
            $domainDto->metadata = $domainData['metadata'] ?? [];
            $domainDto->verification_token = $this->generateVerificationToken();
            $domainDto->status = 'pending_verification';
            $domainDto->verified = false;
            $domainDto->created_at = new \DateTime();

            // Valida DTO
            $domainDto->validate();

            // Salva no repository
            $result = $this->repository->create($domainDto->toArray());

            // Gera registros DNS necessários
            $dnsRecords = $this->generateDnsRecords($result['id'], $domainDto->verification_token);
            $this->repository->updateDnsRecords($result['id'], $dnsRecords);

            $this->logger->info('Domain configured successfully', [
                'tenant_id' => $tenantId,
                'domain_id' => $result['id'],
                'domain' => $domainData['domain']
            ]);

            return [
                'success' => true,
                'domain_id' => $result['id'],
                'domain' => $domainData['domain'],
                'status' => 'pending_verification',
                'verification_token' => $domainDto->verification_token,
                'dns_records' => $dnsRecords,
                'created_at' => $result['created_at']
            ];

        } catch (DomainValidationException $e) {
            $this->logger->warning('Domain configuration validation failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to configure domain', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verifica um domínio usando token ou método DNS
     */
    public function verifyDomain(string $domainId): array
    {
        $this->logger->info('Verifying domain', ['domain_id' => $domainId]);

        try {
            // Busca o domínio
            $domain = $this->repository->findById($domainId);
            if (!$domain) {
                throw new DomainNotFoundException("Domain with ID '{$domainId}' not found");
            }

            $domainDto = new DomainData();
            $domainDto->fromArray($domain);

            // Verifica se pode ser verificado
            if (!$domainDto->canBeVerified()) {
                throw new DomainValidationException("Domain cannot be verified in current status: {$domainDto->status}");
            }

            // Executa verificação baseada no método
            $verificationResult = match ($domainDto->verification_method) {
                'dns' => $this->verifyDnsDomain($domainDto),
                'file' => $this->verifyFileDomain($domainDto),
                'email' => $this->verifyEmailDomain($domainDto),
                default => throw new DomainValidationException("Unsupported verification method: {$domainDto->verification_method}")
            };

            // Atualiza status baseado no resultado
            $status = $verificationResult['success'] ? 'verified' : 'failed';
            $metadata = [
                'verification_result' => $verificationResult,
                'verified_at' => date('c'),
                'verification_attempts' => ($domainDto->metadata['verification_attempts'] ?? 0) + 1
            ];

            $this->repository->updateVerificationStatus($domainId, $status, $metadata);

            $this->logger->info('Domain verification completed', [
                'domain_id' => $domainId,
                'success' => $verificationResult['success'],
                'method' => $domainDto->verification_method
            ]);

            return [
                'success' => $verificationResult['success'],
                'domain_id' => $domainId,
                'domain' => $domainDto->domain,
                'verified' => $verificationResult['success'],
                'status' => $status,
                'verification_method' => $domainDto->verification_method,
                'verification_details' => $verificationResult,
                'verified_at' => $verificationResult['success'] ? date('c') : null
            ];

        } catch (DomainNotFoundException $e) {
            $this->logger->warning('Domain verification failed - not found', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify domain', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lista domínios de um tenant
     */
    public function getTenantDomains(string $tenantId, array $filters = []): array
    {
        $this->logger->debug('Getting tenant domains', [
            'tenant_id' => $tenantId,
            'filters' => $filters
        ]);

        try {
            $domains = $this->repository->findByTenantId($tenantId);

            // Aplica filtros se especificados
            if (!empty($filters['verified'])) {
                $domains = array_filter($domains, fn($domain) => $domain['verified'] === $filters['verified']);
            }

            if (!empty($filters['status'])) {
                $domains = array_filter($domains, fn($domain) => $domain['status'] === $filters['status']);
            }

            $this->logger->info('Tenant domains retrieved', [
                'tenant_id' => $tenantId,
                'count' => count($domains)
            ]);

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'domains' => $domains,
                'count' => count($domains)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant domains', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove um domínio
     */
    public function removeDomain(string $domainId): array
    {
        $this->logger->info('Removing domain', ['domain_id' => $domainId]);

        try {
            $domain = $this->repository->findById($domainId);
            if (!$domain) {
                throw new DomainNotFoundException("Domain with ID '{$domainId}' not found");
            }

            $this->repository->delete($domainId);

            $this->logger->info('Domain removed successfully', [
                'domain_id' => $domainId,
                'domain' => $domain['domain']
            ]);

            return [
                'success' => true,
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'removed_at' => date('c')
            ];

        } catch (DomainNotFoundException $e) {
            $this->logger->warning('Domain removal failed - not found', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove domain', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza configurações SSL de um domínio
     */
    public function updateSslConfig(string $domainId, array $sslConfig): array
    {
        $this->logger->info('Updating SSL config', ['domain_id' => $domainId]);

        try {
            $domain = $this->repository->findById($domainId);
            if (!$domain) {
                throw new DomainNotFoundException("Domain with ID '{$domainId}' not found");
            }

            $this->repository->updateSslConfig($domainId, $sslConfig);

            $this->logger->info('SSL config updated successfully', [
                'domain_id' => $domainId,
                'domain' => $domain['domain']
            ]);

            return [
                'success' => true,
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'ssl_config' => $sslConfig,
                'updated_at' => date('c')
            ];

        } catch (DomainNotFoundException $e) {
            $this->logger->warning('SSL config update failed - domain not found', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update SSL config', [
                'domain_id' => $domainId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtém estatísticas de domínios de um tenant
     */
    public function getDomainStats(string $tenantId): array
    {
        $this->logger->debug('Getting domain stats', ['tenant_id' => $tenantId]);

        try {
            $stats = $this->repository->getStats($tenantId);

            $this->logger->info('Domain stats retrieved', [
                'tenant_id' => $tenantId
            ]);

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'stats' => $stats,
                'generated_at' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get domain stats', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verifica se o service está saudável
     */
    public function isHealthy(): bool
    {
        try {
            // Testa conexão com repository
            $this->repository->getPendingVerification();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('DomainService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém métricas do service
     */
    public function getMetrics(): array
    {
        try {
            return [
                'service' => 'DomainService',
                'status' => 'active',
                'healthy' => $this->isHealthy(),
                'repository_type' => get_class($this->repository),
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            return [
                'service' => 'DomainService',
                'status' => 'error',
                'healthy' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    /**
     * Valida dados de domínio
     */
    private function validateDomainData(array $domainData): void
    {
        if (empty($domainData['domain'])) {
            throw new DomainValidationException('Domain is required');
        }

        if (!filter_var($domainData['domain'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new DomainValidationException('Invalid domain format');
        }

        if (strlen($domainData['domain']) > 253) {
            throw new DomainValidationException('Domain name too long (max 253 characters)');
        }

        $allowedMethods = ['dns', 'file', 'email'];
        if (isset($domainData['verification_method']) &&
            !in_array($domainData['verification_method'], $allowedMethods)) {
            throw new DomainValidationException('Invalid verification method');
        }
    }

    /**
     * Gera token de verificação único
     */
    private function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Gera registros DNS necessários para verificação
     */
    private function generateDnsRecords(string $domainId, string $verificationToken): array
    {
        return [
            [
                'type' => 'TXT',
                'name' => '_clubify-verification',
                'value' => "clubify-domain-verification={$verificationToken}",
                'ttl' => 300
            ],
            [
                'type' => 'CNAME',
                'name' => 'checkout',
                'value' => $this->config->get('app.domain', 'checkout.clubify.com'),
                'ttl' => 3600
            ]
        ];
    }

    /**
     * Verifica domínio via DNS
     */
    private function verifyDnsDomain(DomainData $domain): array
    {
        try {
            $txtRecords = dns_get_record("_clubify-verification.{$domain->domain}", DNS_TXT);

            foreach ($txtRecords as $record) {
                if (str_contains($record['txt'] ?? '', "clubify-domain-verification={$domain->verification_token}")) {
                    return ['success' => true, 'method' => 'dns', 'found_record' => $record['txt']];
                }
            }

            return ['success' => false, 'method' => 'dns', 'error' => 'Verification TXT record not found'];

        } catch (\Exception $e) {
            return ['success' => false, 'method' => 'dns', 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica domínio via arquivo
     */
    private function verifyFileDomain(DomainData $domain): array
    {
        try {
            $verificationUrl = "http://{$domain->domain}/.well-known/clubify-verification.txt";
            $content = @file_get_contents($verificationUrl);

            if ($content && trim($content) === $domain->verification_token) {
                return ['success' => true, 'method' => 'file', 'verification_url' => $verificationUrl];
            }

            return ['success' => false, 'method' => 'file', 'error' => 'Verification file not found or invalid'];

        } catch (\Exception $e) {
            return ['success' => false, 'method' => 'file', 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica domínio via email (placeholder)
     */
    private function verifyEmailDomain(DomainData $domain): array
    {
        // Implementação placeholder - seria necessário sistema de email real
        return ['success' => false, 'method' => 'email', 'error' => 'Email verification not implemented'];
    }
}
