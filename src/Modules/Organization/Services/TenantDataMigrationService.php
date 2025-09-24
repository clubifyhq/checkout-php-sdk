<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Products\Contracts\ProductRepositoryInterface;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de Migração de Dados entre Tenants
 *
 * Responsável por migrar dados de um usuário quando ele é transferido
 * de um tenant para outro. Inclui produtos, configurações e outros dados
 * associados ao usuário.
 *
 * @package Clubify\Checkout\Modules\Organization\Services
 * @version 1.0.0
 */
class TenantDataMigrationService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private UserRepositoryInterface $userRepository,
        private Logger $logger
    ) {
    }

    /**
     * Migra todos os dados de um usuário de um tenant para outro
     *
     * @param string $userId ID do usuário
     * @param string $sourceTenantId Tenant de origem
     * @param string $targetTenantId Tenant de destino
     * @param array $options Opções de migração
     * @return array Resultado da migração
     */
    public function migrateUserData(
        string $userId,
        string $sourceTenantId,
        string $targetTenantId,
        array $options = []
    ): array {
        $this->logger->info('Iniciando migração de dados do usuário', [
            'user_id' => $userId,
            'source_tenant' => $sourceTenantId,
            'target_tenant' => $targetTenantId,
            'options' => $options
        ]);

        $migrationResult = [
            'success' => false,
            'user_id' => $userId,
            'source_tenant' => $sourceTenantId,
            'target_tenant' => $targetTenantId,
            'migrated_data' => [],
            'errors' => [],
            'started_at' => date('c'),
            'completed_at' => null
        ];

        try {
            // 1. Verificar se usuário existe no tenant de origem
            $user = $this->verifyUserInTenant($userId, $sourceTenantId);
            if (!$user) {
                throw new \Exception("Usuário não encontrado no tenant de origem");
            }

            // 2. Migrar produtos
            if (!isset($options['skip_products']) || !$options['skip_products']) {
                $productsMigration = $this->migrateUserProducts($userId, $sourceTenantId, $targetTenantId);
                $migrationResult['migrated_data']['products'] = $productsMigration;
            }

            // 3. Migrar outras entidades conforme necessário
            // TODO: Adicionar migração de outras entidades (orders, customers, etc.)

            $migrationResult['success'] = true;
            $migrationResult['completed_at'] = date('c');

            $this->logger->info('Migração de dados concluída com sucesso', [
                'user_id' => $userId,
                'migrated_data' => $migrationResult['migrated_data']
            ]);

        } catch (\Exception $e) {
            $migrationResult['errors'][] = $e->getMessage();
            $migrationResult['completed_at'] = date('c');

            $this->logger->error('Falha na migração de dados', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Se configurado para rollback, tentar reverter mudanças
            if (isset($options['rollback_on_error']) && $options['rollback_on_error']) {
                $this->rollbackMigration($migrationResult);
            }
        }

        return $migrationResult;
    }

    /**
     * Verifica se usuário existe no tenant
     */
    private function verifyUserInTenant(string $userId, string $tenantId): ?array
    {
        try {
            return $this->userRepository->findById($userId);
        } catch (\Exception $e) {
            $this->logger->warning('Usuário não encontrado no tenant', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Migra produtos do usuário entre tenants
     */
    private function migrateUserProducts(
        string $userId,
        string $sourceTenantId,
        string $targetTenantId
    ): array {
        $result = [
            'total_found' => 0,
            'migrated' => 0,
            'errors' => [],
            'migrated_products' => []
        ];

        try {
            // Buscar produtos criados pelo usuário no tenant de origem
            $filters = [
                'created_by' => $userId,
                'tenant_id' => $sourceTenantId
            ];

            $products = $this->productRepository->findByTenant($sourceTenantId, $filters);
            $result['total_found'] = count($products);

            $this->logger->info('Produtos encontrados para migração', [
                'user_id' => $userId,
                'source_tenant' => $sourceTenantId,
                'products_count' => $result['total_found']
            ]);

            foreach ($products as $product) {
                try {
                    $migratedProduct = $this->migrateProduct($product, $targetTenantId);
                    if ($migratedProduct) {
                        $result['migrated']++;
                        $result['migrated_products'][] = [
                            'original_id' => $product['id'] ?? $product['_id'],
                            'new_id' => $migratedProduct['id'] ?? $migratedProduct['_id'],
                            'name' => $product['name']
                        ];
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'product_id' => $product['id'] ?? $product['_id'],
                        'error' => $e->getMessage()
                    ];

                    $this->logger->error('Falha ao migrar produto', [
                        'product_id' => $product['id'] ?? $product['_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Falha geral na migração de produtos: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Migra um produto específico para o novo tenant
     */
    private function migrateProduct(array $product, string $targetTenantId): ?array
    {
        try {
            // Remover IDs e campos específicos do sistema
            $productData = $product;
            unset($productData['id'], $productData['_id'], $productData['created_at'], $productData['updated_at']);

            // Atualizar tenant_id
            $productData['tenant_id'] = $targetTenantId;

            // Criar produto no novo tenant
            $newProduct = $this->productRepository->create($productData);

            $this->logger->info('Produto migrado com sucesso', [
                'original_id' => $product['id'] ?? $product['_id'],
                'new_id' => $newProduct['id'] ?? $newProduct['_id'],
                'target_tenant' => $targetTenantId
            ]);

            return $newProduct;

        } catch (\Exception $e) {
            $this->logger->error('Falha ao migrar produto individual', [
                'product' => $product['name'] ?? 'Unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Tenta reverter uma migração em caso de erro
     */
    private function rollbackMigration(array $migrationResult): void
    {
        $this->logger->info('Iniciando rollback da migração', [
            'user_id' => $migrationResult['user_id']
        ]);

        // Tentar remover produtos criados no tenant de destino
        if (isset($migrationResult['migrated_data']['products']['migrated_products'])) {
            foreach ($migrationResult['migrated_data']['products']['migrated_products'] as $migratedProduct) {
                try {
                    $this->productRepository->delete($migratedProduct['new_id']);
                    $this->logger->info('Produto removido durante rollback', [
                        'product_id' => $migratedProduct['new_id']
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Falha ao remover produto durante rollback', [
                        'product_id' => $migratedProduct['new_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Lista produtos órfãos (sem tenant válido)
     */
    public function findOrphanedProducts(string $userId, string $sourceTenantId): array
    {
        try {
            $filters = [
                'created_by' => $userId,
                'tenant_id' => $sourceTenantId
            ];

            $products = $this->productRepository->findByTenant($sourceTenantId, $filters);

            return [
                'success' => true,
                'products' => $products,
                'count' => count($products)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Falha ao buscar produtos órfãos', [
                'user_id' => $userId,
                'source_tenant' => $sourceTenantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'products' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Migra apenas produtos específicos
     */
    public function migrateSpecificProducts(
        array $productIds,
        string $targetTenantId
    ): array {
        $result = [
            'total_requested' => count($productIds),
            'migrated' => 0,
            'errors' => []
        ];

        foreach ($productIds as $productId) {
            try {
                $product = $this->productRepository->findById($productId);
                if ($product) {
                    $this->migrateProduct($product, $targetTenantId);
                    $result['migrated']++;
                }
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Obtém estatísticas de dados por tenant
     */
    public function getTenantDataStats(string $tenantId, ?string $userId = null): array
    {
        $stats = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'data_counts' => [],
            'generated_at' => date('c')
        ];

        try {
            // Contar produtos
            $filters = $userId ? ['created_by' => $userId] : [];
            $products = $this->productRepository->findByTenant($tenantId, $filters);
            $stats['data_counts']['products'] = count($products);

            // TODO: Adicionar contagem de outras entidades

        } catch (\Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }
}