<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Exceptions\ValidationException;
use ClubifyCheckout\Exceptions\HttpException;

/**
 * Serviço de configuração de domínios customizados
 *
 * Responsável por gerenciar domínios customizados para organizações:
 * - Configuração de domínios personalizados
 * - Verificação DNS e SSL
 * - Certificados SSL automáticos
 * - Redirects e proxy configuration
 * - Health checking de domínios
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de domínio
 * - O: Open/Closed - Extensível via tipos de configuração
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de domínio
 * - D: Dependency Inversion - Depende de abstrações
 */
class DomainService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'domain';
    }

    /**
     * Configura um domínio customizado para uma organização
     */
    public function configure(string $organizationId, string $domain): array
    {
        return $this->executeWithMetrics('configure_domain', function () use ($organizationId, $domain) {
            $this->validateDomain($domain);

            // Verificar se domínio já está em uso
            if ($this->isDomainInUse($domain)) {
                throw new ValidationException("Domain '{$domain}' is already in use");
            }

            // Preparar dados do domínio
            $data = [
                'organization_id' => $organizationId,
                'domain' => $domain,
                'status' => 'pending_verification',
                'ssl_enabled' => false,
                'auto_ssl' => true,
                'redirect_www' => true,
                'force_https' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'verification_token' => $this->generateVerificationToken(),
                'dns_records' => $this->generateDnsRecords($domain),
                'settings' => $this->getDefaultDomainSettings()
            ];

            // Criar configuração de domínio via API
            $response = $this->httpClient->post('/domains', $data);
            $domainConfig = $response->getData();

            // Cache da configuração
            $this->cache->set($this->getCacheKey("domain:{$domainConfig['id']}"), $domainConfig, 3600);
            $this->cache->set($this->getCacheKey("domain_by_name:{$domain}"), $domainConfig, 3600);

            // Dispatch evento
            $this->dispatch('domain.configured', [
                'domain_id' => $domainConfig['id'],
                'organization_id' => $organizationId,
                'domain' => $domain,
                'verification_token' => $domainConfig['verification_token']
            ]);

            $this->logger->info('Domain configured successfully', [
                'domain_id' => $domainConfig['id'],
                'organization_id' => $organizationId,
                'domain' => $domain
            ]);

            return $domainConfig;
        });
    }

    /**
     * Obtém configuração de domínio por ID
     */
    public function getDomain(string $domainId): ?array
    {
        return $this->getCachedOrExecute(
            "domain:{$domainId}",
            fn() => $this->fetchDomainById($domainId),
            3600
        );
    }

    /**
     * Obtém domínio por nome
     */
    public function getDomainByName(string $domain): ?array
    {
        return $this->getCachedOrExecute(
            "domain_by_name:{$domain}",
            fn() => $this->fetchDomainByName($domain),
            1800
        );
    }

    /**
     * Lista domínios de uma organização
     */
    public function getDomainsByOrganization(string $organizationId): array
    {
        return $this->executeWithMetrics('get_domains_by_organization', function () use ($organizationId) {
            $response = $this->httpClient->get("/organizations/{$organizationId}/domains");
            return $response->getData() ?? [];
        });
    }

    /**
     * Verifica DNS de um domínio
     */
    public function verifyDns(string $domainId): array
    {
        return $this->executeWithMetrics('verify_domain_dns', function () use ($domainId) {
            $response = $this->httpClient->post("/domains/{$domainId}/verify-dns", []);
            $result = $response->getData();

            // Invalidar cache se verificação foi bem-sucedida
            if ($result['verified'] ?? false) {
                $this->invalidateDomainCache($domainId);
            }

            // Dispatch evento
            $this->dispatch('domain.dns_verified', [
                'domain_id' => $domainId,
                'verified' => $result['verified'] ?? false,
                'records' => $result['records'] ?? []
            ]);

            return $result;
        });
    }

    /**
     * Provisiona certificado SSL para um domínio
     */
    public function provisionSsl(string $domainId): array
    {
        return $this->executeWithMetrics('provision_ssl_certificate', function () use ($domainId) {
            $response = $this->httpClient->post("/domains/{$domainId}/provision-ssl", []);
            $result = $response->getData();

            // Invalidar cache se SSL foi provisionado
            if ($result['success'] ?? false) {
                $this->invalidateDomainCache($domainId);
            }

            // Dispatch evento
            $this->dispatch('domain.ssl_provisioned', [
                'domain_id' => $domainId,
                'success' => $result['success'] ?? false,
                'certificate_expires_at' => $result['expires_at'] ?? null
            ]);

            return $result;
        });
    }

    /**
     * Renova certificado SSL de um domínio
     */
    public function renewSsl(string $domainId): array
    {
        return $this->executeWithMetrics('renew_ssl_certificate', function () use ($domainId) {
            $response = $this->httpClient->post("/domains/{$domainId}/renew-ssl", []);
            $result = $response->getData();

            // Invalidar cache se SSL foi renovado
            if ($result['success'] ?? false) {
                $this->invalidateDomainCache($domainId);
            }

            // Dispatch evento
            $this->dispatch('domain.ssl_renewed', [
                'domain_id' => $domainId,
                'success' => $result['success'] ?? false,
                'new_expires_at' => $result['expires_at'] ?? null
            ]);

            return $result;
        });
    }

    /**
     * Atualiza configurações do domínio
     */
    public function updateSettings(string $domainId, array $settings): array
    {
        return $this->executeWithMetrics('update_domain_settings', function () use ($domainId, $settings) {
            $this->validateDomainSettings($settings);

            $response = $this->httpClient->put("/domains/{$domainId}/settings", [
                'settings' => $settings
            ]);

            $domain = $response->getData();

            // Invalidar cache
            $this->invalidateDomainCache($domainId);

            // Dispatch evento
            $this->dispatch('domain.settings_updated', [
                'domain_id' => $domainId,
                'settings' => $settings
            ]);

            return $domain;
        });
    }

    /**
     * Verifica saúde do domínio
     */
    public function checkHealth(string $domainId): array
    {
        return $this->executeWithMetrics('check_domain_health', function () use ($domainId) {
            $response = $this->httpClient->get("/domains/{$domainId}/health");
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém status do certificado SSL
     */
    public function getSslStatus(string $domainId): array
    {
        return $this->executeWithMetrics('get_ssl_status', function () use ($domainId) {
            $response = $this->httpClient->get("/domains/{$domainId}/ssl-status");
            return $response->getData() ?? [];
        });
    }

    /**
     * Ativa um domínio
     */
    public function activateDomain(string $domainId): bool
    {
        return $this->updateDomainStatus($domainId, 'active');
    }

    /**
     * Desativa um domínio
     */
    public function deactivateDomain(string $domainId): bool
    {
        return $this->updateDomainStatus($domainId, 'inactive');
    }

    /**
     * Suspende um domínio
     */
    public function suspendDomain(string $domainId): bool
    {
        return $this->updateDomainStatus($domainId, 'suspended');
    }

    /**
     * Remove configuração de domínio
     */
    public function removeDomain(string $domainId): bool
    {
        return $this->executeWithMetrics('remove_domain', function () use ($domainId) {
            try {
                $response = $this->httpClient->delete("/domains/{$domainId}");

                // Invalidar cache
                $this->invalidateDomainCache($domainId);

                // Dispatch evento
                $this->dispatch('domain.removed', [
                    'domain_id' => $domainId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to remove domain', [
                    'domain_id' => $domainId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Verifica se domínio está em uso
     */
    public function isDomainInUse(string $domain): bool
    {
        try {
            $response = $this->httpClient->get("/domains/check-availability/{$domain}");
            $data = $response->getData();
            return !($data['available'] ?? false);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return false; // Se não existe, não está em uso
            }
            throw $e;
        }
    }

    /**
     * Obtém registros DNS necessários
     */
    public function getDnsRecords(string $domainId): array
    {
        return $this->executeWithMetrics('get_dns_records', function () use ($domainId) {
            $response = $this->httpClient->get("/domains/{$domainId}/dns-records");
            return $response->getData() ?? [];
        });
    }

    /**
     * Testa conectividade do domínio
     */
    public function testConnectivity(string $domain): array
    {
        return $this->executeWithMetrics('test_domain_connectivity', function () use ($domain) {
            $response = $this->httpClient->post('/domains/test-connectivity', [
                'domain' => $domain
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém certificados expirados
     */
    public function getExpiringCertificates(int $days = 30): array
    {
        return $this->executeWithMetrics('get_expiring_certificates', function () use ($days) {
            $response = $this->httpClient->get('/domains/expiring-certificates', [
                'days' => $days
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Renova certificados expirados automaticamente
     */
    public function autoRenewCertificates(): array
    {
        return $this->executeWithMetrics('auto_renew_certificates', function () {
            $expiringCerts = $this->getExpiringCertificates();
            $renewed = [];

            foreach ($expiringCerts as $cert) {
                if ($cert['auto_renew'] ?? false) {
                    try {
                        $result = $this->renewSsl($cert['domain_id']);
                        if ($result['success'] ?? false) {
                            $renewed[] = $cert;
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to auto-renew certificate', [
                            'domain_id' => $cert['domain_id'],
                            'domain' => $cert['domain'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Dispatch evento
            $this->dispatch('domains.certificates_auto_renewed', [
                'renewed_count' => count($renewed),
                'domains' => array_column($renewed, 'domain')
            ]);

            return $renewed;
        });
    }

    /**
     * Busca domínio por ID via API
     */
    private function fetchDomainById(string $domainId): ?array
    {
        try {
            $response = $this->httpClient->get("/domains/{$domainId}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca domínio por nome via API
     */
    private function fetchDomainByName(string $domain): ?array
    {
        try {
            $response = $this->httpClient->get("/domains/by-name/{$domain}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do domínio
     */
    private function updateDomainStatus(string $domainId, string $status): bool
    {
        return $this->executeWithMetrics("update_domain_status_{$status}", function () use ($domainId, $status) {
            try {
                $response = $this->httpClient->put("/domains/{$domainId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateDomainCache($domainId);

                // Dispatch evento
                $this->dispatch('domain.status_changed', [
                    'domain_id' => $domainId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update domain status to {$status}", [
                    'domain_id' => $domainId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do domínio
     */
    private function invalidateDomainCache(string $domainId): void
    {
        $domain = $this->getDomain($domainId);

        $this->cache->delete($this->getCacheKey("domain:{$domainId}"));

        if ($domain && isset($domain['domain'])) {
            $this->cache->delete($this->getCacheKey("domain_by_name:{$domain['domain']}"));
        }
    }

    /**
     * Valida domínio
     */
    private function validateDomain(string $domain): void
    {
        if (!$this->isValidDomain($domain)) {
            throw new ValidationException("Invalid domain format: {$domain}");
        }

        if (strlen($domain) > 253) {
            throw new ValidationException("Domain name too long: {$domain}");
        }

        // Verificar se não é um domínio reservado
        $reservedDomains = ['localhost', 'example.com', 'test.com', 'invalid'];
        if (in_array($domain, $reservedDomains)) {
            throw new ValidationException("Reserved domain: {$domain}");
        }
    }

    /**
     * Valida configurações do domínio
     */
    private function validateDomainSettings(array $settings): void
    {
        $allowedSettings = [
            'redirect_www', 'force_https', 'auto_ssl', 'cache_enabled',
            'gzip_enabled', 'security_headers', 'hsts_enabled'
        ];

        foreach ($settings as $key => $value) {
            if (!in_array($key, $allowedSettings)) {
                throw new ValidationException("Invalid domain setting: {$key}");
            }
        }
    }

    /**
     * Verifica se domínio é válido
     */
    private function isValidDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * Gera token de verificação
     */
    private function generateVerificationToken(): string
    {
        return 'clubify_verify_' . bin2hex(random_bytes(16));
    }

    /**
     * Gera registros DNS necessários
     */
    private function generateDnsRecords(string $domain): array
    {
        return [
            [
                'type' => 'CNAME',
                'name' => $domain,
                'value' => 'checkout.clubify.com',
                'ttl' => 3600
            ],
            [
                'type' => 'TXT',
                'name' => '_clubify_verification.' . $domain,
                'value' => $this->generateVerificationToken(),
                'ttl' => 300
            ]
        ];
    }

    /**
     * Obtém configurações padrão do domínio
     */
    private function getDefaultDomainSettings(): array
    {
        return [
            'redirect_www' => true,
            'force_https' => true,
            'auto_ssl' => true,
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'gzip_enabled' => true,
            'security_headers' => true,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000
        ];
    }
}