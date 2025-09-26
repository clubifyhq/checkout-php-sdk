<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\UserManagement\DTOs\TenantData;
use Clubify\Checkout\Modules\UserManagement\Contracts\TenantRepositoryInterface;
use Clubify\Checkout\Exceptions\SDKException;
use Clubify\Checkout\Exceptions\ValidationException;
use DateTime;

/**
 * Service para operações de tenants/organizações
 *
 * Responsável pela lógica de negócio relacionada a tenants,
 * incluindo validações, transformações de dados e orchestração
 * de operações complexas entre múltiplos repositórios.
 */
class TenantService
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    /**
     * Cria um novo tenant
     */
    public function createTenant(array $tenantData): array
    {
        try {
            // Validar dados de entrada
            $validatedData = $this->validateTenantData($tenantData);

            // Criar DTO para validações adicionais
            $tenantDto = new TenantData($validatedData);

            // Verificar se slug está disponível
            if (!$this->tenantRepository->isSlugAvailable($tenantDto->slug)) {
                throw new ValidationException('Slug already in use');
            }

            // Verificar domínios únicos se fornecidos
            if (!empty($tenantDto->domains)) {
                foreach ($tenantDto->domains as $domainConfig) {
                    $domain = $domainConfig['domain'] ?? null;
                    if ($domain && !$this->tenantRepository->isDomainAvailable($domain)) {
                        throw new ValidationException("Domain '{$domain}' already in use");
                    }
                }
            }

            // Criar o tenant
            $createdTenant = $this->tenantRepository->create($validatedData);

            $this->logger->info('Tenant created successfully', [
                'tenant_id' => $createdTenant['id'] ?? 'unknown',
                'name' => $createdTenant['name'] ?? 'unknown',
                'slug' => $createdTenant['slug'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'tenant' => $createdTenant,
                'tenant_id' => $createdTenant['id'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create tenant', [
                'error' => $e->getMessage(),
                'data' => $tenantData
            ]);

            throw new SDKException('Failed to create tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cria uma organização (alias para createTenant)
     */
    public function createOrganization(array $organizationData): array
    {
        try {
            // Mapear dados de organização para formato de tenant se necessário
            $tenantData = $this->mapOrganizationToTenant($organizationData);

            // Usar método específico do repository se disponível
            if (method_exists($this->tenantRepository, 'createOrganization')) {
                $createdOrganization = $this->tenantRepository->createOrganization($tenantData);
            } else {
                $createdOrganization = $this->tenantRepository->create($tenantData);
            }

            $this->logger->info('Organization created successfully', [
                'organization_id' => $createdOrganization['tenant_id'] ?? $createdOrganization['id'] ?? 'unknown',
                'name' => $createdOrganization['name'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'organization' => $createdOrganization,
                'tenant_id' => $createdOrganization['tenant_id'] ?? $createdOrganization['id'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create organization', [
                'error' => $e->getMessage(),
                'data' => $organizationData
            ]);

            throw new SDKException('Failed to create organization: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtém um tenant por ID
     */
    public function getTenant(string $tenantId): array
    {
        try {
            $tenant = $this->tenantRepository->findById($tenantId);

            if (!$tenant) {
                return [
                    'success' => false,
                    'message' => 'Tenant not found'
                ];
            }

            return [
                'success' => true,
                'tenant' => $tenant
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to get tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtém tenant por slug
     */
    public function getTenantBySlug(string $slug): array
    {
        try {
            $tenant = $this->tenantRepository->findBySlug($slug);

            if (!$tenant) {
                return [
                    'success' => false,
                    'message' => 'Tenant not found'
                ];
            }

            return [
                'success' => true,
                'tenant' => $tenant
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant by slug', [
                'error' => $e->getMessage(),
                'slug' => $slug
            ]);

            throw new SDKException('Failed to get tenant by slug: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtém tenant por domínio
     */
    public function getTenantByDomain(string $domain): array
    {
        try {
            $this->logger->debug('TenantService: Searching for tenant by domain', [
                'domain' => $domain,
                'repository' => get_class($this->tenantRepository)
            ]);

            $tenant = $this->tenantRepository->findByDomain($domain);

            $this->logger->debug('TenantService: Repository result', [
                'domain' => $domain,
                'tenant_is_null' => $tenant === null,
                'tenant_keys' => $tenant ? array_keys($tenant) : 'null'
            ]);

            if (!$tenant) {
                $this->logger->info('Tenant not found by domain', [
                    'domain' => $domain
                ]);

                return [
                    'success' => false,
                    'message' => "Tenant not found for domain: {$domain}",
                    'domain' => $domain
                ];
            }

            $this->logger->info('Tenant found by domain', [
                'domain' => $domain,
                'tenant_id' => $tenant['_id'] ?? $tenant['id'] ?? 'unknown',
                'tenant_name' => $tenant['name'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'tenant' => $tenant,
                'message' => 'Tenant found successfully',
                'domain' => $domain
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant by domain', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'exception_class' => get_class($e)
            ]);

            // Para debug: não lançar exceção, retornar erro estruturado
            return [
                'success' => false,
                'message' => "Error searching for tenant: " . $e->getMessage(),
                'domain' => $domain,
                'error' => 'Exception'
            ];
        }
    }

    /**
     * Atualiza um tenant
     */
    public function updateTenant(string $tenantId, array $tenantData): array
    {
        try {
            // Verificar se tenant existe
            $existingTenant = $this->tenantRepository->findById($tenantId);
            if (!$existingTenant) {
                throw new ValidationException('Tenant not found');
            }

            // Validar dados de atualização
            $validatedData = $this->validateTenantData($tenantData, false);

            // Verificar slug único se foi alterado
            if (isset($validatedData['slug']) && $validatedData['slug'] !== $existingTenant['slug']) {
                if (!$this->tenantRepository->isSlugAvailable($validatedData['slug'], $tenantId)) {
                    throw new ValidationException('Slug already in use');
                }
            }

            // Atualizar o tenant
            $updatedTenant = $this->tenantRepository->update($tenantId, $validatedData);

            $this->logger->info('Tenant updated successfully', [
                'tenant_id' => $tenantId,
                'updated_fields' => array_keys($validatedData)
            ]);

            return [
                'success' => true,
                'tenant' => $updatedTenant,
                'tenant_id' => $tenantId
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'data' => $tenantData
            ]);

            throw new SDKException('Failed to update tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Lista tenants com filtros
     */
    public function listTenants(array $filters = []): array
    {
        try {
            $tenants = $this->tenantRepository->findBy($filters);

            return [
                'success' => true,
                'tenants' => $tenants,
                'count' => count($tenants)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to list tenants', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            throw new SDKException('Failed to list tenants: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Suspende um tenant
     */
    public function suspendTenant(string $tenantId, string $reason = ''): array
    {
        try {
            $success = $this->tenantRepository->suspend($tenantId, $reason);

            if (!$success) {
                throw new SDKException('Failed to suspend tenant');
            }

            $this->logger->info('Tenant suspended successfully', [
                'tenant_id' => $tenantId,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'Tenant suspended successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to suspend tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to suspend tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Reativa um tenant
     */
    public function reactivateTenant(string $tenantId): array
    {
        try {
            $success = $this->tenantRepository->reactivate($tenantId);

            if (!$success) {
                throw new SDKException('Failed to reactivate tenant');
            }

            $this->logger->info('Tenant reactivated successfully', [
                'tenant_id' => $tenantId
            ]);

            return [
                'success' => true,
                'message' => 'Tenant reactivated successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to reactivate tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to reactivate tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtém estatísticas dos tenants
     */
    public function getTenantStats(): array
    {
        try {
            $stats = $this->tenantRepository->getTenantStats();

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant stats', [
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to get tenant stats: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Valida dados do tenant
     */
    private function validateTenantData(array $data, bool $requireAll = true): array
    {
        $required = ['name'];

        if ($requireAll) {
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new ValidationException("Field '{$field}' is required");
                }
            }
        }


        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'suspended', 'pending'])) {
            throw new ValidationException('Invalid status');
        }

        if (isset($data['plan']) && !in_array($data['plan'], ['basic', 'pro', 'enterprise'])) {
            throw new ValidationException('Invalid plan');
        }

        // Adicionar timestamps se necessário
        if ($requireAll) {
            $data['created_at'] = $data['created_at'] ?? new DateTime();
        }
        $data['updated_at'] = new DateTime();

        return $data;
    }

    /**
     * Mapeia dados de organização para formato de tenant
     */
    private function mapOrganizationToTenant(array $organizationData): array
    {
        $tenantData = [];

        // Mapeamento direto de campos comuns
        $tenantData['name'] = $organizationData['name'] ?? '';
        $tenantData['description'] = $organizationData['description'] ?? '';

        // Mapear domínio principal (custom_domain -> domain)
        if (isset($organizationData['custom_domain'])) {
            // Usar o domínio completo incluindo subdominios
            $tenantData['domain'] = $organizationData['custom_domain'];
        }

        // Mapear subdomínio
        if (isset($organizationData['subdomain'])) {
            $tenantData['subdomain'] = $organizationData['subdomain'];
        }

        // Mapear plano
        $tenantData['plan'] = $organizationData['plan'] ?? 'starter';

        // Criar estrutura de contato necessária para a API
        $tenantData['contact'] = [
            'email' => $organizationData['admin_email'] ?? $organizationData['support_email'] ?? '',
        ];

        // Mapear configurações para a estrutura esperada pela API
        if (isset($organizationData['settings']) && is_array($organizationData['settings'])) {
            $settings = $organizationData['settings'];
            $tenantData['settings'] = [];

            // Mapear campos específicos da API
            if (isset($settings['timezone'])) {
                $tenantData['settings']['timezone'] = $settings['timezone'];
            }
            if (isset($settings['currency'])) {
                $tenantData['settings']['defaultCurrency'] = strtoupper($settings['currency']);
            }
            if (isset($settings['language'])) {
                $tenantData['settings']['defaultLanguage'] = $settings['language'];
            }
        }

        // Não incluir campos proibidos pela API (slug, status)
        // A API não permite estes campos na criação

        return $tenantData;
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlugFromName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
    }
}
