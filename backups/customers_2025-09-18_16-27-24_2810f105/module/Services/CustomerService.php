<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\Services;

use Clubify\Checkout\Core\BaseService;
use Clubify\Checkout\Modules\Customers\Contracts\CustomerRepositoryInterface;
use Clubify\Checkout\Modules\Customers\Exceptions\CustomerNotFoundException;
use Clubify\Checkout\Modules\Customers\Exceptions\DuplicateCustomerException;
use ClubifyCheckout\Utils\Validators\EmailValidator;
use ClubifyCheckout\Utils\Validators\CPFValidator;
use ClubifyCheckout\Utils\Validators\CNPJValidator;
use ClubifyCheckout\Utils\Validators\PhoneValidator;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use InvalidArgumentException;

/**
 * Serviço de gestão de clientes
 *
 * Implementa operações CRUD completas para clientes,
 * incluindo validações, sanitização, cache e eventos.
 *
 * Funcionalidades principais:
 * - CRUD completo de clientes
 * - Validação robusta de dados
 * - Detecção de duplicatas
 * - Sistema de tags e metadados
 * - Compliance LGPD/GDPR
 * - Cache inteligente
 * - Métricas e analytics
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de cliente
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível por outras implementações
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class CustomerService extends BaseService
{
    private array $validationRules = [
        'name' => ['required', 'string', 'min:2', 'max:100'],
        'email' => ['required', 'email'],
        'phone' => ['nullable', 'string'],
        'document' => ['nullable', 'string'],
        'document_type' => ['nullable', 'in:cpf,cnpj'],
        'birth_date' => ['nullable', 'date'],
        'gender' => ['nullable', 'in:male,female,other'],
    ];

    public function __construct(
        private CustomerRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache = null,
        private ?EmailValidator $emailValidator = null,
        private ?CPFValidator $cpfValidator = null,
        private ?CNPJValidator $cnpjValidator = null,
        private ?PhoneValidator $phoneValidator = null
    ) {
        parent::__construct($logger, $cache);

        // Initialize validators if not injected
        $this->emailValidator ??= new EmailValidator();
        $this->cpfValidator ??= new CPFValidator();
        $this->cnpjValidator ??= new CNPJValidator();
        $this->phoneValidator ??= new PhoneValidator();
    }

    /**
     * Cria novo cliente
     */
    public function create(array $customerData): array
    {
        return $this->executeWithMetrics('create', function () use ($customerData) {
            $this->validateCustomerData($customerData);

            // Verifica duplicatas
            $this->checkForDuplicates($customerData);

            // Sanitiza dados
            $sanitizedData = $this->sanitizeCustomerData($customerData);

            // Adiciona metadados padrão
            $sanitizedData = $this->addDefaultMetadata($sanitizedData);

            // Cria cliente
            $customer = $this->repository->create($sanitizedData);

            // Dispara evento
            $this->dispatchEvent('customer.created', $customer);

            // Invalida cache relacionado
            $this->invalidateCustomerCache($customer['id']);

            $this->logger->info('Cliente criado com sucesso', [
                'customer_id' => $customer['id'],
                'organization_id' => $customer['organization_id'] ?? null,
            ]);

            return $customer;
        });
    }

    /**
     * Obtém cliente por ID
     */
    public function findById(string $customerId): ?array
    {
        return $this->getCachedOrExecute(
            "customer:{$customerId}",
            fn () => $this->repository->findById($customerId),
            300 // 5 minutos
        );
    }

    /**
     * Atualiza cliente
     */
    public function update(string $customerId, array $updateData): array
    {
        return $this->executeWithMetrics('update', function () use ($customerId, $updateData) {
            $existingCustomer = $this->repository->findById($customerId);
            if (!$existingCustomer) {
                throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
            }

            // Valida apenas campos presentes
            $this->validateUpdateData($updateData);

            // Verifica duplicatas para campos únicos
            $this->checkUpdateDuplicates($customerId, $updateData);

            // Sanitiza dados
            $sanitizedData = $this->sanitizeCustomerData($updateData, true);

            // Adiciona timestamp de atualização
            $sanitizedData['updated_at'] = date('Y-m-d H:i:s');

            // Atualiza cliente
            $customer = $this->repository->update($customerId, $sanitizedData);

            // Dispara evento
            $this->dispatchEvent('customer.updated', [
                'customer' => $customer,
                'changes' => $this->getChanges($existingCustomer, $customer),
            ]);

            // Invalida cache
            $this->invalidateCustomerCache($customerId);

            $this->logger->info('Cliente atualizado com sucesso', [
                'customer_id' => $customerId,
                'updated_fields' => array_keys($sanitizedData),
            ]);

            return $customer;
        });
    }

    /**
     * Remove cliente
     */
    public function delete(string $customerId): bool
    {
        return $this->executeWithMetrics('delete', function () use ($customerId) {
            $customer = $this->repository->findById($customerId);
            if (!$customer) {
                throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
            }

            // Verifica se pode ser removido
            $this->validateDeletion($customer);

            // Remove cliente
            $result = $this->repository->delete($customerId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('customer.deleted', $customer);

                // Invalida cache
                $this->invalidateCustomerCache($customerId);

                $this->logger->info('Cliente removido com sucesso', [
                    'customer_id' => $customerId,
                ]);
            }

            return $result;
        });
    }

    /**
     * Lista clientes com filtros
     */
    public function findByFilters(array $filters = []): array
    {
        $cacheKey = "customers:filtered:" . md5(serialize($filters));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByFilters($filters),
            180 // 3 minutos
        );
    }

    /**
     * Busca cliente por email
     */
    public function findByEmail(string $email, string $organizationId = null): ?array
    {
        if (!$this->emailValidator->validate($email)) {
            throw new InvalidArgumentException("Email inválido: {$email}");
        }

        $cacheKey = "customer:email:" . md5($email . ($organizationId ?? ''));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByEmail($email, $organizationId),
            300
        );
    }

    /**
     * Busca cliente por documento
     */
    public function findByDocument(string $document, string $organizationId = null): ?array
    {
        $sanitizedDocument = $this->sanitizeDocument($document);

        $cacheKey = "customer:document:" . md5($sanitizedDocument . ($organizationId ?? ''));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByDocument($sanitizedDocument, $organizationId),
            300
        );
    }

    /**
     * Atualiza cliente apenas se houver mudanças
     */
    public function updateIfChanged(string $customerId, array $data): ?array
    {
        return $this->executeWithMetrics('updateIfChanged', function () use ($customerId, $data) {
            return $this->repository->updateIfChanged($customerId, $data);
        });
    }

    /**
     * Adiciona tag ao cliente
     */
    public function addTag(string $customerId, string $tag): array
    {
        return $this->executeWithMetrics('addTag', function () use ($customerId, $tag) {
            $this->validateTag($tag);

            $customer = $this->repository->addTag($customerId, $tag);

            // Dispara evento
            $this->dispatchEvent('customer.tag.added', [
                'customer_id' => $customerId,
                'tag' => $tag,
            ]);

            // Invalida cache
            $this->invalidateCustomerCache($customerId);

            return $customer;
        });
    }

    /**
     * Remove tag do cliente
     */
    public function removeTag(string $customerId, string $tag): array
    {
        return $this->executeWithMetrics('removeTag', function () use ($customerId, $tag) {
            $customer = $this->repository->removeTag($customerId, $tag);

            // Dispara evento
            $this->dispatchEvent('customer.tag.removed', [
                'customer_id' => $customerId,
                'tag' => $tag,
            ]);

            // Invalida cache
            $this->invalidateCustomerCache($customerId);

            return $customer;
        });
    }

    /**
     * Busca clientes por tag
     */
    public function findByTag(string $tag): array
    {
        $cacheKey = "customers:tag:" . md5($tag);

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByTag($tag),
            300
        );
    }

    /**
     * Obtém estatísticas de clientes
     */
    public function getStatistics(array $filters = []): array
    {
        $cacheKey = "customers:stats:" . md5(serialize($filters));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->getStatistics($filters),
            600 // 10 minutos
        );
    }

    /**
     * Exporta dados do cliente (compliance LGPD)
     */
    public function exportData(string $customerId): array
    {
        return $this->executeWithMetrics('exportData', function () use ($customerId) {
            $customer = $this->repository->findById($customerId);
            if (!$customer) {
                throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
            }

            $exportData = $this->repository->exportData($customerId);

            // Registra evento de exportação
            $this->dispatchEvent('customer.data.exported', [
                'customer_id' => $customerId,
                'exported_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->info('Dados do cliente exportados', [
                'customer_id' => $customerId,
            ]);

            return $exportData;
        });
    }

    /**
     * Anonimiza dados do cliente (compliance LGPD)
     */
    public function anonymizeData(string $customerId): bool
    {
        return $this->executeWithMetrics('anonymizeData', function () use ($customerId) {
            $customer = $this->repository->findById($customerId);
            if (!$customer) {
                throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
            }

            $result = $this->repository->anonymize($customerId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('customer.data.anonymized', [
                    'customer_id' => $customerId,
                    'anonymized_at' => date('Y-m-d H:i:s'),
                ]);

                // Invalida cache
                $this->invalidateCustomerCache($customerId);

                $this->logger->info('Dados do cliente anonimizados', [
                    'customer_id' => $customerId,
                ]);
            }

            return $result;
        });
    }

    /**
     * Conta total de clientes
     */
    public function count(array $filters = []): int
    {
        $cacheKey = "customers:count:" . md5(serialize($filters));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->count($filters),
            300
        );
    }

    /**
     * Valida dados do cliente
     */
    private function validateCustomerData(array $data): void
    {
        // Validação de campos obrigatórios
        foreach ($this->validationRules as $field => $rules) {
            if (in_array('required', $rules) && (!isset($data[$field]) || empty($data[$field]))) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }

            if (isset($data[$field])) {
                $this->validateField($field, $data[$field], $rules);
            }
        }

        // Validações específicas
        if (isset($data['email'])) {
            if (!$this->emailValidator->validate($data['email'])) {
                throw new InvalidArgumentException("Email inválido: {$data['email']}");
            }
        }

        if (isset($data['document']) && isset($data['document_type'])) {
            $this->validateDocument($data['document'], $data['document_type']);
        }

        if (isset($data['phone'])) {
            if (!$this->phoneValidator->validate($data['phone'])) {
                throw new InvalidArgumentException("Telefone inválido: {$data['phone']}");
            }
        }
    }

    /**
     * Valida dados de atualização
     */
    private function validateUpdateData(array $data): void
    {
        foreach ($data as $field => $value) {
            if (isset($this->validationRules[$field])) {
                $rules = array_filter($this->validationRules[$field], fn ($rule) => $rule !== 'required');
                $this->validateField($field, $value, $rules);
            }
        }

        // Validações específicas para update
        if (isset($data['email']) && !$this->emailValidator->validate($data['email'])) {
            throw new InvalidArgumentException("Email inválido: {$data['email']}");
        }

        if (isset($data['document']) && isset($data['document_type'])) {
            $this->validateDocument($data['document'], $data['document_type']);
        }

        if (isset($data['phone']) && !$this->phoneValidator->validate($data['phone'])) {
            throw new InvalidArgumentException("Telefone inválido: {$data['phone']}");
        }
    }

    /**
     * Valida campo individual
     */
    private function validateField(string $field, $value, array $rules): void
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                switch ($rule) {
                    case 'string':
                        if (!is_string($value)) {
                            throw new InvalidArgumentException("Campo {$field} deve ser string");
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new InvalidArgumentException("Email inválido: {$value}");
                        }
                        break;
                    case 'date':
                        if (!strtotime($value)) {
                            throw new InvalidArgumentException("Data inválida: {$value}");
                        }
                        break;
                }
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (strlen($value) < $min) {
                    throw new InvalidArgumentException("Campo {$field} deve ter pelo menos {$min} caracteres");
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int) substr($rule, 4);
                if (strlen($value) > $max) {
                    throw new InvalidArgumentException("Campo {$field} deve ter no máximo {$max} caracteres");
                }
            }

            if (str_starts_with($rule, 'in:')) {
                $values = explode(',', substr($rule, 3));
                if (!in_array($value, $values)) {
                    throw new InvalidArgumentException("Campo {$field} deve ser um dos valores: " . implode(', ', $values));
                }
            }
        }
    }

    /**
     * Valida documento (CPF/CNPJ)
     */
    private function validateDocument(string $document, string $type): void
    {
        $sanitizedDocument = $this->sanitizeDocument($document);

        switch ($type) {
            case 'cpf':
                if (!$this->cpfValidator->validate($sanitizedDocument)) {
                    throw new InvalidArgumentException("CPF inválido: {$document}");
                }
                break;
            case 'cnpj':
                if (!$this->cnpjValidator->validate($sanitizedDocument)) {
                    throw new InvalidArgumentException("CNPJ inválido: {$document}");
                }
                break;
            default:
                throw new InvalidArgumentException("Tipo de documento inválido: {$type}");
        }
    }

    /**
     * Valida tag
     */
    private function validateTag(string $tag): void
    {
        if (empty($tag) || strlen($tag) > 50) {
            throw new InvalidArgumentException("Tag deve ter entre 1 e 50 caracteres");
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $tag)) {
            throw new InvalidArgumentException("Tag deve conter apenas letras, números, _ e -");
        }
    }

    /**
     * Verifica duplicatas
     */
    private function checkForDuplicates(array $customerData): void
    {
        if (isset($customerData['email'])) {
            $existing = $this->repository->findByEmail(
                $customerData['email'],
                $customerData['organization_id'] ?? null
            );

            if ($existing) {
                throw new DuplicateCustomerException("Cliente já existe com este email: {$customerData['email']}");
            }
        }

        if (isset($customerData['document'])) {
            $existing = $this->repository->findByDocument(
                $this->sanitizeDocument($customerData['document']),
                $customerData['organization_id'] ?? null
            );

            if ($existing) {
                throw new DuplicateCustomerException("Cliente já existe com este documento: {$customerData['document']}");
            }
        }
    }

    /**
     * Verifica duplicatas na atualização
     */
    private function checkUpdateDuplicates(string $customerId, array $updateData): void
    {
        if (isset($updateData['email'])) {
            $existing = $this->repository->findByEmail(
                $updateData['email'],
                $updateData['organization_id'] ?? null
            );

            if ($existing && $existing['id'] !== $customerId) {
                throw new DuplicateCustomerException("Outro cliente já existe com este email: {$updateData['email']}");
            }
        }

        if (isset($updateData['document'])) {
            $existing = $this->repository->findByDocument(
                $this->sanitizeDocument($updateData['document']),
                $updateData['organization_id'] ?? null
            );

            if ($existing && $existing['id'] !== $customerId) {
                throw new DuplicateCustomerException("Outro cliente já existe com este documento: {$updateData['document']}");
            }
        }
    }

    /**
     * Sanitiza dados do cliente
     */
    private function sanitizeCustomerData(array $data, bool $isUpdate = false): array
    {
        $sanitized = [];

        // Campos de texto
        $textFields = ['name', 'email', 'phone'];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = trim($data[$field]);
            }
        }

        // Email para lowercase
        if (isset($sanitized['email'])) {
            $sanitized['email'] = strtolower($sanitized['email']);
        }

        // Documento sanitizado
        if (isset($data['document'])) {
            $sanitized['document'] = $this->sanitizeDocument($data['document']);
        }

        // Outros campos diretos
        $directFields = ['document_type', 'birth_date', 'gender', 'organization_id'];
        foreach ($directFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        // Endereço (se presente)
        if (isset($data['address']) && is_array($data['address'])) {
            $sanitized['address'] = $this->sanitizeAddress($data['address']);
        }

        // Metadados (se presente)
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $sanitized['metadata'] = $data['metadata'];
        }

        return $sanitized;
    }

    /**
     * Sanitiza documento (remove caracteres especiais)
     */
    private function sanitizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document);
    }

    /**
     * Sanitiza endereço
     */
    private function sanitizeAddress(array $address): array
    {
        $sanitized = [];

        $textFields = ['street', 'number', 'complement', 'neighborhood', 'city', 'state', 'country'];
        foreach ($textFields as $field) {
            if (isset($address[$field])) {
                $sanitized[$field] = trim($address[$field]);
            }
        }

        // CEP sanitizado
        if (isset($address['zip_code'])) {
            $sanitized['zip_code'] = preg_replace('/\D/', '', $address['zip_code']);
        }

        return $sanitized;
    }

    /**
     * Adiciona metadados padrão
     */
    private function addDefaultMetadata(array $data): array
    {
        $data['id'] = $data['id'] ?? $this->generateCustomerId();
        $data['status'] = $data['status'] ?? 'active';
        $data['tags'] = $data['tags'] ?? [];
        $data['total_spent'] = $data['total_spent'] ?? 0.0;
        $data['total_orders'] = $data['total_orders'] ?? 0;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Valida se cliente pode ser removido
     */
    private function validateDeletion(array $customer): void
    {
        // Verifica se há pedidos ativos
        if (($customer['total_orders'] ?? 0) > 0) {
            // Em uma implementação real, verificaria pedidos ativos
            // Por ora, permite a remoção
        }
    }

    /**
     * Obtém diferenças entre dois clientes
     */
    private function getChanges(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Invalida cache relacionado ao cliente
     */
    private function invalidateCustomerCache(string $customerId): void
    {
        $patterns = [
            "customer:{$customerId}",
            "customers:*",
        ];

        foreach ($patterns as $pattern) {
            $this->clearCachePattern($pattern);
        }
    }

    /**
     * Gera ID único para cliente
     */
    private function generateCustomerId(): string
    {
        return 'cust_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
}
