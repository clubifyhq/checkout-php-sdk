<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Modules\Checkout\Contracts\SessionRepositoryInterface;
use ClubifyCheckout\Modules\Checkout\Contracts\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de One-Click
 *
 * Gerencia compras one-click permitindo checkout
 * rápido com dados pré-salvos do cliente.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de one-click
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseService
 * - I: Interface Segregation - Usa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OneClickService extends BaseService
{
    private const CACHE_TTL = 300; // 5 minutos
    private const ONE_CLICK_TTL = 900; // 15 minutos

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
    }

    /**
     * Inicia processo de one-click
     */
    public function initiate(string $organizationId, array $productData, array $customerData): array
    {
        return $this->executeWithMetrics('one_click_initiate', function () use ($organizationId, $productData, $customerData) {
            // Valida dados do produto
            $this->validateProductData($productData);

            // Valida dados do cliente
            $this->validateCustomerData($customerData);

            // Busca dados salvos do cliente
            $savedCustomerData = $this->getSavedCustomerData($customerData['email']);

            if (!$savedCustomerData) {
                throw new \InvalidArgumentException('Cliente não possui dados salvos para one-click');
            }

            // Cria sessão one-click
            $sessionData = [
                'organization_id' => $organizationId,
                'type' => 'one_click',
                'customer_data' => array_merge($savedCustomerData, $customerData),
                'product_data' => $productData,
                'status' => 'initiated',
                'expires_at' => date('Y-m-d H:i:s', time() + self::ONE_CLICK_TTL)
            ];

            $session = $this->sessionRepository->create($sessionData);

            // Cria carrinho automaticamente
            $cart = $this->createOneClickCart($session['id'], $productData);

            // Calcula totais
            $cart = $this->cartRepository->calculateTotal($cart['id']);

            // Atualiza sessão com dados do carrinho
            $session = $this->sessionRepository->update($session['id'], [
                'cart_id' => $cart['id'],
                'cart_data' => $cart
            ]);

            // Cache da sessão one-click
            $this->setCacheItem("one_click_{$session['id']}", $session, self::CACHE_TTL);

            $this->logger->info('One-click iniciado', [
                'session_id' => $session['id'],
                'organization_id' => $organizationId,
                'customer_email' => $customerData['email'],
                'product_id' => $productData['id'] ?? null,
                'total_amount' => $cart['totals']['total'] ?? 0
            ]);

            return [
                'one_click_id' => $session['id'],
                'session' => $session,
                'cart' => $cart,
                'customer' => $this->sanitizeCustomerData($savedCustomerData),
                'expires_at' => $session['expires_at']
            ];
        });
    }

    /**
     * Completa processo de one-click
     */
    public function complete(string $oneClickId, array $paymentData): array
    {
        return $this->executeWithMetrics('one_click_complete', function () use ($oneClickId, $paymentData) {
            // Busca sessão one-click
            $session = $this->getOneClickSession($oneClickId);

            if (!$session) {
                throw new \InvalidArgumentException('Sessão one-click não encontrada');
            }

            if ($session['status'] !== 'initiated') {
                throw new \InvalidArgumentException('Sessão one-click já processada');
            }

            if (strtotime($session['expires_at']) < time()) {
                throw new \InvalidArgumentException('Sessão one-click expirada');
            }

            // Valida dados de pagamento
            $this->validatePaymentData($paymentData);

            // Atualiza sessão com dados de pagamento
            $session = $this->sessionRepository->updatePaymentData($oneClickId, $paymentData);

            // Marca sessão como em processamento
            $session = $this->sessionRepository->updateStatus($oneClickId, 'processing');

            try {
                // Processa pagamento (integração com payment service)
                $paymentResult = $this->processPayment($session, $paymentData);

                if ($paymentResult['success']) {
                    // Marca como completo
                    $session = $this->sessionRepository->markAsCompleted($oneClickId);

                    // Converte carrinho em pedido
                    $order = $this->cartRepository->convertToOrder($session['cart_id']);

                    // Remove do cache
                    $this->clearCacheByPattern("one_click_{$oneClickId}*");

                    $this->logger->info('One-click completado com sucesso', [
                        'one_click_id' => $oneClickId,
                        'order_id' => $order['order_id'] ?? null,
                        'payment_id' => $paymentResult['payment_id'] ?? null,
                        'amount' => $paymentResult['amount'] ?? 0
                    ]);

                    return [
                        'success' => true,
                        'one_click_id' => $oneClickId,
                        'order' => $order,
                        'payment' => $paymentResult,
                        'session' => $session
                    ];
                } else {
                    // Marca como falhou
                    $session = $this->sessionRepository->updateStatus($oneClickId, 'failed');

                    $this->logger->warning('One-click falhou no pagamento', [
                        'one_click_id' => $oneClickId,
                        'error' => $paymentResult['error'] ?? 'Erro desconhecido'
                    ]);

                    return [
                        'success' => false,
                        'one_click_id' => $oneClickId,
                        'error' => $paymentResult['error'] ?? 'Falha no pagamento',
                        'session' => $session
                    ];
                }
            } catch (\Exception $e) {
                // Marca como erro
                $session = $this->sessionRepository->updateStatus($oneClickId, 'error');

                $this->logger->error('Erro ao processar one-click', [
                    'one_click_id' => $oneClickId,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Obtém dados salvos do cliente
     */
    public function getSavedCustomerData(string $email): ?array
    {
        return $this->getCachedOrExecute("customer_data_{$email}", function () use ($email) {
            // Em produção seria consulta ao customer service
            return $this->mockGetSavedCustomerData($email);
        }, self::CACHE_TTL);
    }

    /**
     * Salva dados do cliente para futuros one-clicks
     */
    public function saveCustomerData(string $email, array $customerData, array $paymentData): array
    {
        return $this->executeWithMetrics('one_click_save_data', function () use ($email, $customerData, $paymentData) {
            // Valida dados
            $this->validateCustomerData($customerData);
            $this->validatePaymentData($paymentData);

            // Sanitiza dados sensíveis (remove dados do cartão, mantém apenas token)
            $sanitizedPaymentData = $this->sanitizePaymentData($paymentData);

            $savedData = [
                'email' => $email,
                'customer_data' => $customerData,
                'payment_data' => $sanitizedPaymentData,
                'saved_at' => date('Y-m-d H:i:s'),
                'enabled' => true
            ];

            // Em produção seria persistido no customer service
            $result = $this->mockSaveCustomerData($email, $savedData);

            // Remove do cache para forçar atualização
            $this->clearCacheByPattern("customer_data_{$email}*");

            $this->logger->info('Dados do cliente salvos para one-click', [
                'customer_email' => $email,
                'has_payment_token' => !empty($sanitizedPaymentData['token'])
            ]);

            return $result;
        });
    }

    /**
     * Verifica se cliente tem dados salvos
     */
    public function hasCustomerData(string $email): bool
    {
        $customerData = $this->getSavedCustomerData($email);
        return $customerData !== null && ($customerData['enabled'] ?? false);
    }

    /**
     * Remove dados salvos do cliente
     */
    public function removeCustomerData(string $email): bool
    {
        return $this->executeWithMetrics('one_click_remove_data', function () use ($email) {
            // Em produção seria remoção do customer service
            $result = $this->mockRemoveCustomerData($email);

            // Remove do cache
            $this->clearCacheByPattern("customer_data_{$email}*");

            $this->logger->info('Dados do cliente removidos', [
                'customer_email' => $email
            ]);

            return $result;
        });
    }

    /**
     * Obtém estatísticas de one-click
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->getCachedOrExecute('one_click_statistics', function () use ($filters) {
            return $this->sessionRepository->getStatistics(array_merge($filters, ['type' => 'one_click']));
        }, 300);
    }

    /**
     * Obtém taxa de conversão one-click
     */
    public function getConversionRate(): float
    {
        return $this->getCachedOrExecute('one_click_conversion_rate', function () {
            $stats = $this->getStatistics();
            $initiated = $stats['initiated'] ?? 0;
            $completed = $stats['completed'] ?? 0;

            return $initiated > 0 ? ($completed / $initiated) * 100 : 0.0;
        }, 300);
    }

    /**
     * Obtém sessão one-click
     */
    private function getOneClickSession(string $oneClickId): ?array
    {
        return $this->getCachedOrExecute("one_click_{$oneClickId}", function () use ($oneClickId) {
            return $this->sessionRepository->find($oneClickId);
        }, self::CACHE_TTL);
    }

    /**
     * Cria carrinho para one-click
     */
    private function createOneClickCart(string $sessionId, array $productData): array
    {
        $cartData = [
            'session_id' => $sessionId,
            'type' => 'one_click',
            'status' => 'active'
        ];

        $cart = $this->cartRepository->create($cartData);

        // Adiciona produto ao carrinho
        $itemData = [
            'product_id' => $productData['id'],
            'name' => $productData['name'],
            'price' => $productData['price'],
            'quantity' => $productData['quantity'] ?? 1,
            'metadata' => $productData['metadata'] ?? []
        ];

        $this->cartRepository->addItem($cart['id'], $itemData);

        return $this->cartRepository->find($cart['id']);
    }

    /**
     * Processa pagamento
     */
    private function processPayment(array $session, array $paymentData): array
    {
        // Mock do processamento de pagamento
        // Em produção seria integração com payment service
        $amount = $session['cart_data']['totals']['total'] ?? 0;

        // Simula processamento
        $success = !empty($paymentData['token']) && $amount > 0;

        if ($success) {
            return [
                'success' => true,
                'payment_id' => 'pay_' . uniqid(),
                'amount' => $amount,
                'currency' => 'BRL',
                'status' => 'completed',
                'processed_at' => date('Y-m-d H:i:s')
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Falha no processamento do pagamento',
                'status' => 'failed'
            ];
        }
    }

    /**
     * Valida dados do produto
     */
    private function validateProductData(array $productData): void
    {
        if (empty($productData['id'])) {
            throw new \InvalidArgumentException('ID do produto é obrigatório');
        }

        if (empty($productData['name'])) {
            throw new \InvalidArgumentException('Nome do produto é obrigatório');
        }

        if (!isset($productData['price']) || $productData['price'] < 0) {
            throw new \InvalidArgumentException('Preço do produto é obrigatório e deve ser >= 0');
        }

        $quantity = $productData['quantity'] ?? 1;
        if ($quantity <= 0 || $quantity > 10) {
            throw new \InvalidArgumentException('Quantidade deve ser entre 1 e 10');
        }
    }

    /**
     * Valida dados do cliente
     */
    private function validateCustomerData(array $customerData): void
    {
        if (empty($customerData['email']) || !filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email válido é obrigatório');
        }

        if (empty($customerData['name'])) {
            throw new \InvalidArgumentException('Nome do cliente é obrigatório');
        }
    }

    /**
     * Valida dados de pagamento
     */
    private function validatePaymentData(array $paymentData): void
    {
        if (empty($paymentData['method'])) {
            throw new \InvalidArgumentException('Método de pagamento é obrigatório');
        }

        if ($paymentData['method'] === 'credit_card' && empty($paymentData['token'])) {
            throw new \InvalidArgumentException('Token de pagamento é obrigatório para cartão');
        }
    }

    /**
     * Sanitiza dados do cliente
     */
    private function sanitizeCustomerData(array $customerData): array
    {
        // Remove dados sensíveis para resposta
        $sanitized = $customerData;
        unset($sanitized['password'], $sanitized['ssn'], $sanitized['tax_id']);

        return $sanitized;
    }

    /**
     * Sanitiza dados de pagamento
     */
    private function sanitizePaymentData(array $paymentData): array
    {
        // Remove dados sensíveis, mantém apenas referências
        return [
            'method' => $paymentData['method'],
            'token' => $paymentData['token'] ?? null,
            'last_four' => $paymentData['last_four'] ?? null,
            'brand' => $paymentData['brand'] ?? null,
            'exp_month' => $paymentData['exp_month'] ?? null,
            'exp_year' => $paymentData['exp_year'] ?? null
        ];
    }

    /**
     * Mock para obter dados salvos do cliente
     */
    private function mockGetSavedCustomerData(string $email): ?array
    {
        // Mock - em produção seria consulta real
        $mockCustomers = [
            'cliente@exemplo.com' => [
                'email' => 'cliente@exemplo.com',
                'name' => 'Cliente Exemplo',
                'phone' => '(11) 99999-9999',
                'address' => [
                    'street' => 'Rua Exemplo, 123',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'zip_code' => '01234-567'
                ],
                'payment_data' => [
                    'method' => 'credit_card',
                    'token' => 'card_token_123',
                    'last_four' => '1234',
                    'brand' => 'visa'
                ],
                'enabled' => true
            ]
        ];

        return $mockCustomers[$email] ?? null;
    }

    /**
     * Mock para salvar dados do cliente
     */
    private function mockSaveCustomerData(string $email, array $data): array
    {
        // Mock - em produção seria persistência real
        return array_merge($data, ['id' => uniqid('customer_')]);
    }

    /**
     * Mock para remover dados do cliente
     */
    private function mockRemoveCustomerData(string $email): bool
    {
        // Mock - em produção seria remoção real
        return true;
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge(parent::getMetrics(), [
            'one_click_ttl' => self::ONE_CLICK_TTL,
            'cache_ttl' => self::CACHE_TTL
        ]);
    }
}