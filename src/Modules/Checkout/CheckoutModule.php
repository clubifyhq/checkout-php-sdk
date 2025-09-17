<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout;

use ClubifyCheckout\Contracts\ModuleInterface;
use ClubifyCheckout\Modules\Checkout\Contracts\SessionRepositoryInterface;
use ClubifyCheckout\Modules\Checkout\Contracts\CartRepositoryInterface;
use ClubifyCheckout\Modules\Checkout\Services\SessionService;
use ClubifyCheckout\Modules\Checkout\Services\CartService;
use ClubifyCheckout\Modules\Checkout\Services\OneClickService;
use ClubifyCheckout\Modules\Checkout\Services\FlowService;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Módulo de Checkout
 *
 * Gerencia o processo completo de checkout incluindo sessões,
 * carrinho, one-click e navegação de flows.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Orquestra apenas operações de checkout
 * - O: Open/Closed - Extensível via novos serviços
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Interfaces específicas por funcionalidade
 * - D: Dependency Inversion - Depende de abstrações
 */
class CheckoutModule implements ModuleInterface
{
    private ?SessionService $sessionService = null;
    private ?CartService $cartService = null;
    private ?OneClickService $oneClickService = null;
    private ?FlowService $flowService = null;

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private CartRepositoryInterface $cartRepository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private array $config = []
    ) {}

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'checkout';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se o módulo está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Obtém dependências do módulo
     */
    public function getDependencies(): array
    {
        return ['products', 'customers'];
    }

    /**
     * Inicializa o módulo
     */
    public function initialize(): void
    {
        $this->logger->info('Inicializando CheckoutModule', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
        ]);

        // Configurações específicas do módulo
        $this->configureServices();

        $this->logger->info('CheckoutModule inicializado com sucesso');
    }

    /**
     * Configura os serviços do módulo
     */
    private function configureServices(): void
    {
        // Configurações específicas para cada serviço
        $sessionConfig = $this->config['session'] ?? [];
        $cartConfig = $this->config['cart'] ?? [];
        $oneClickConfig = $this->config['one_click'] ?? [];
        $flowConfig = $this->config['flow'] ?? [];

        $this->logger->debug('Serviços do CheckoutModule configurados', [
            'session_config' => !empty($sessionConfig),
            'cart_config' => !empty($cartConfig),
            'one_click_config' => !empty($oneClickConfig),
            'flow_config' => !empty($flowConfig)
        ]);
    }

    /**
     * Obtém o serviço de sessões (lazy loading)
     */
    public function sessions(): SessionService
    {
        if ($this->sessionService === null) {
            $this->sessionService = new SessionService(
                $this->sessionRepository,
                $this->logger,
                $this->cache,
                $this->config['session'] ?? []
            );
        }

        return $this->sessionService;
    }

    /**
     * Obtém o serviço de carrinho (lazy loading)
     */
    public function cart(): CartService
    {
        if ($this->cartService === null) {
            $this->cartService = new CartService(
                $this->cartRepository,
                $this->logger,
                $this->cache,
                $this->config['cart'] ?? []
            );
        }

        return $this->cartService;
    }

    /**
     * Obtém o serviço de one-click (lazy loading)
     */
    public function oneClick(): OneClickService
    {
        if ($this->oneClickService === null) {
            $this->oneClickService = new OneClickService(
                $this->sessionRepository,
                $this->cartRepository,
                $this->logger,
                $this->cache,
                $this->config['one_click'] ?? []
            );
        }

        return $this->oneClickService;
    }

    /**
     * Obtém o serviço de flows (lazy loading)
     */
    public function flows(): FlowService
    {
        if ($this->flowService === null) {
            $this->flowService = new FlowService(
                $this->sessionRepository,
                $this->logger,
                $this->cache,
                $this->config['flow'] ?? []
            );
        }

        return $this->flowService;
    }

    /**
     * Cria uma nova sessão de checkout
     */
    public function createSession(string $organizationId, array $data = []): array
    {
        $this->logger->info('Criando nova sessão de checkout', [
            'organization_id' => $organizationId,
            'data_keys' => array_keys($data)
        ]);

        $session = $this->sessions()->create($organizationId, $data);

        $this->logger->info('Sessão de checkout criada', [
            'session_id' => $session['id'],
            'organization_id' => $organizationId
        ]);

        return $session;
    }

    /**
     * Obtém sessão de checkout
     */
    public function getSession(string $sessionId): ?array
    {
        return $this->sessions()->find($sessionId);
    }

    /**
     * Atualiza sessão de checkout
     */
    public function updateSession(string $sessionId, array $data): array
    {
        $this->logger->info('Atualizando sessão de checkout', [
            'session_id' => $sessionId,
            'data_keys' => array_keys($data)
        ]);

        return $this->sessions()->update($sessionId, $data);
    }

    /**
     * Finaliza sessão de checkout
     */
    public function completeSession(string $sessionId): array
    {
        $this->logger->info('Finalizando sessão de checkout', [
            'session_id' => $sessionId
        ]);

        return $this->sessions()->complete($sessionId);
    }

    /**
     * Cria um novo carrinho
     */
    public function createCart(string $sessionId, array $data = []): array
    {
        $this->logger->info('Criando novo carrinho', [
            'session_id' => $sessionId,
            'data_keys' => array_keys($data)
        ]);

        return $this->cart()->create($sessionId, $data);
    }

    /**
     * Adiciona item ao carrinho
     */
    public function addToCart(string $cartId, array $item): array
    {
        $this->logger->info('Adicionando item ao carrinho', [
            'cart_id' => $cartId,
            'product_id' => $item['product_id'] ?? null,
            'quantity' => $item['quantity'] ?? 1
        ]);

        return $this->cart()->addItem($cartId, $item);
    }

    /**
     * Remove item do carrinho
     */
    public function removeFromCart(string $cartId, string $itemId): array
    {
        $this->logger->info('Removendo item do carrinho', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        return $this->cart()->removeItem($cartId, $itemId);
    }

    /**
     * Atualiza quantidade de item no carrinho
     */
    public function updateCartItem(string $cartId, string $itemId, int $quantity): array
    {
        $this->logger->info('Atualizando item do carrinho', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'quantity' => $quantity
        ]);

        return $this->cart()->updateItem($cartId, $itemId, $quantity);
    }

    /**
     * Aplica cupom de desconto
     */
    public function applyCoupon(string $cartId, string $couponCode): array
    {
        $this->logger->info('Aplicando cupom de desconto', [
            'cart_id' => $cartId,
            'coupon_code' => $couponCode
        ]);

        return $this->cart()->applyCoupon($cartId, $couponCode);
    }

    /**
     * Remove cupom de desconto
     */
    public function removeCoupon(string $cartId): array
    {
        $this->logger->info('Removendo cupom de desconto', [
            'cart_id' => $cartId
        ]);

        return $this->cart()->removeCoupon($cartId);
    }

    /**
     * Calcula totais do carrinho
     */
    public function calculateCartTotals(string $cartId): array
    {
        return $this->cart()->calculateTotals($cartId);
    }

    /**
     * Inicia processo de one-click
     */
    public function initiateOneClick(string $organizationId, array $productData, array $customerData): array
    {
        $this->logger->info('Iniciando processo one-click', [
            'organization_id' => $organizationId,
            'product_id' => $productData['id'] ?? null,
            'customer_email' => $customerData['email'] ?? null
        ]);

        return $this->oneClick()->initiate($organizationId, $productData, $customerData);
    }

    /**
     * Completa processo de one-click
     */
    public function completeOneClick(string $oneClickId, array $paymentData): array
    {
        $this->logger->info('Completando processo one-click', [
            'one_click_id' => $oneClickId
        ]);

        return $this->oneClick()->complete($oneClickId, $paymentData);
    }

    /**
     * Cria um novo flow de checkout
     */
    public function createFlow(string $organizationId, array $flowConfig): array
    {
        $this->logger->info('Criando flow de checkout', [
            'organization_id' => $organizationId,
            'flow_type' => $flowConfig['type'] ?? 'standard'
        ]);

        return $this->flows()->create($organizationId, $flowConfig);
    }

    /**
     * Navega para próximo passo do flow
     */
    public function navigateFlow(string $sessionId, string $currentStep, array $data = []): array
    {
        $this->logger->info('Navegando flow de checkout', [
            'session_id' => $sessionId,
            'current_step' => $currentStep,
            'data_keys' => array_keys($data)
        ]);

        return $this->flows()->navigate($sessionId, $currentStep, $data);
    }

    /**
     * Obtém configuração do flow atual
     */
    public function getFlowConfig(string $sessionId): ?array
    {
        return $this->flows()->getConfig($sessionId);
    }

    /**
     * Valida dados do flow
     */
    public function validateFlowData(string $sessionId, string $step, array $data): array
    {
        return $this->flows()->validate($sessionId, $step, $data);
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'enabled' => $this->isEnabled(),
            'services' => [
                'session_service' => $this->sessionService !== null,
                'cart_service' => $this->cartService !== null,
                'one_click_service' => $this->oneClickService !== null,
                'flow_service' => $this->flowService !== null
            ],
            'dependencies' => $this->getDependencies()
        ];
    }

    /**
     * Limpa cache do módulo
     */
    public function clearCache(): bool
    {
        try {
            $this->cache->clear();

            $this->logger->info('Cache do CheckoutModule limpo com sucesso');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erro ao limpar cache do CheckoutModule', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtém configuração do módulo
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Atualiza configuração do módulo
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);

        $this->logger->info('Configuração do CheckoutModule atualizada', [
            'config_keys' => array_keys($config)
        ]);

        // Reconfigura serviços com nova configuração
        $this->configureServices();
    }
}