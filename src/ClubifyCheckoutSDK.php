<?php

declare(strict_types=1);

namespace Clubify\Checkout;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Auth\AuthManager;
use Clubify\Checkout\Core\Events\EventDispatcher;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Modules\Organization\OrganizationModule;
use Clubify\Checkout\Modules\Products\ProductsModule;
use Clubify\Checkout\Modules\Checkout\CheckoutModule;
use Clubify\Checkout\Modules\Payments\PaymentsModule;
use Clubify\Checkout\Modules\Customers\CustomersModule;
use Clubify\Checkout\Modules\Webhooks\WebhooksModule;
use Clubify\Checkout\Modules\Tracking\TrackingModule;
use Clubify\Checkout\Modules\UserManagement\UserManagementModule;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\Subscriptions\SubscriptionsModule;
use Clubify\Checkout\Enums\Environment;
use Clubify\Checkout\Exceptions\ConfigurationException;
use Clubify\Checkout\Exceptions\SDKException;

/**
 * Classe principal do Clubify Checkout SDK para PHP
 *
 * Ponto de entrada único que combina todas as funcionalidades em uma API limpa e intuitiva,
 * seguindo os princípios de Clean Code e SOLID.
 */
class ClubifyCheckoutSDK
{
    private ConfigurationInterface $config;
    private bool $initialized = false;
    private bool $initializing = false;

    // Core components
    private ?Client $httpClient = null;
    private ?AuthManager $authManager = null;
    private ?EventDispatcher $eventDispatcher = null;
    private ?Logger $logger = null;
    private ?CacheManager $cache = null;

    // Module instances (created on demand)
    private ?OrganizationModule $organization = null;
    private ?ProductsModule $products = null;
    private ?CheckoutModule $checkout = null;
    private ?PaymentsModule $payments = null;
    private ?CustomersModule $customers = null;
    private ?WebhooksModule $webhooks = null;
    private ?TrackingModule $tracking = null;
    private ?UserManagementModule $userManagement = null;
    private ?SubscriptionsModule $subscriptions = null;

    /**
     * Cria nova instância do SDK
     *
     * @param array $config Configuração inicial
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        // Validar configuração mínima
        $this->validateMinimalConfig($config);

        // Criar configuração centralizada
        $this->config = new Configuration($config);

        // Core components serão inicializados sob demanda (Lazy Loading)
        // Isso evita travamentos durante a criação da instância
    }

    /**
     * Inicializar SDK
     *
     * Executa autenticação automática e configurações iniciais
     *
     * @return array Resultado da inicialização
     * @throws SDKException
     */
    public function initialize(): array
    {
        if ($this->initialized) {
            return [
                'success' => true,
                'already_initialized' => true,
            ];
        }

        if ($this->initializing) {
            throw new SDKException('SDK initialization already in progress');
        }

        $this->initializing = true;

        try {
            // Autenticação automática (Lazy Loading)
            $authResult = $this->getAuthManager()->authenticate();

            // Validação de conectividade (Lazy Loading)
            $connectivityCheck = $this->getHttpClient()->healthCheck();

            if (!$connectivityCheck) {
                throw new SDKException('API connectivity check failed');
            }

            $this->initialized = true;
            $this->initializing = false;

            return [
                'success' => true,
                'authenticated' => $this->isAuthenticated(),
                'tenant_id' => $this->config->getTenantId(),
                'environment' => $this->config->getEnvironment(),
                'auth_result' => $authResult,
                'connectivity' => $connectivityCheck,
                'timestamp' => date('c'),
            ];
        } catch (\Throwable $e) {
            $this->initializing = false;

            throw new SDKException(
                'SDK initialization failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                ['environment' => $this->config->getEnvironment()]
            );
        }
    }

    /**
     * Verificar se SDK está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Verificar se SDK está autenticado
     */
    public function isAuthenticated(): bool
    {
        // Usar lazy loading apenas se necessário
        if ($this->authManager === null) {
            return false; // Não há auth manager ainda, não está autenticado
        }
        return $this->authManager->isAuthenticated();
    }

    /**
     * Fazer logout (limpar autenticação)
     */
    public function logout(): void
    {
        // Usar lazy loading apenas se necessário
        if ($this->authManager !== null) {
            $this->authManager->logout();
        }
        if ($this->cache !== null) {
            $this->cache->clear();
        }
        $this->initialized = false;
    }

    /**
     * Obter configuração
     */
    public function getConfig(): ConfigurationInterface
    {
        return $this->config;
    }

    /**
     * Setup completo de organização (método de conveniência)
     *
     * @param array $organizationData Dados da organização
     * @return array Resultado do setup
     * @throws SDKException
     */
    public function setupOrganization(array $organizationData): array
    {
        return $this->organization()->setupComplete($organizationData);
    }

    /**
     * Criar produto completo (método de conveniência)
     *
     * @param array $productData Dados do produto
     * @return array Produto criado
     * @throws SDKException
     */
    public function createCompleteProduct(array $productData): array
    {
        return $this->products()->createComplete($productData);
    }

    /**
     * Criar sessão de checkout (método de conveniência)
     *
     * @param array $sessionData Dados da sessão
     * @return array Sessão criada
     * @throws SDKException
     */
    public function createCheckoutSession(array $sessionData): array
    {
        return $this->checkout()->createSession($sessionData);
    }

    /**
     * Processar pagamento one-click (método de conveniência)
     *
     * @param array $paymentData Dados do pagamento
     * @return array Resultado do pagamento
     * @throws SDKException
     */
    public function processOneClick(array $paymentData): array
    {
        return $this->checkout()->oneClick($paymentData);
    }

    /**
     * Criar assinatura completa (método de conveniência)
     *
     * @param array $subscriptionData Dados da assinatura
     * @return array Assinatura criada
     * @throws SDKException
     */
    public function createSubscription(array $subscriptionData): array
    {
        return $this->subscriptions()->createSubscription($subscriptionData);
    }

    /**
     * Obter métricas de assinaturas (método de conveniência)
     *
     * @param array $filters Filtros para métricas
     * @return array Métricas de assinaturas
     * @throws SDKException
     */
    public function getSubscriptionMetrics(array $filters = []): array
    {
        return $this->subscriptions()->getSubscriptionMetrics($filters);
    }

    /**
     * Acesso ao módulo Organization
     */
    public function organization(): OrganizationModule
    {
        if (!$this->organization) {
            $this->organization = new OrganizationModule($this);
            $this->organization->initialize($this->config, $this->getLogger());
        }

        return $this->organization;
    }

    /**
     * Acesso ao módulo Products
     */
    public function products(): ProductsModule
    {
        if (!$this->products) {
            $this->products = new ProductsModule($this);
            $this->products->initialize($this->config, $this->getLogger());
        }

        return $this->products;
    }

    /**
     * Acesso ao módulo Checkout
     */
    public function checkout(): CheckoutModule
    {
        if (!$this->checkout) {
            $this->checkout = new CheckoutModule($this);
            $this->checkout->initialize($this->config, $this->getLogger());
        }

        return $this->checkout;
    }

    /**
     * Acesso ao módulo Payments
     */
    public function payments(): PaymentsModule
    {
        if (!$this->payments) {
            $this->payments = new PaymentsModule($this);
            $this->payments->initialize($this->config, $this->getLogger());
        }

        return $this->payments;
    }

    /**
     * Acesso ao módulo Customers
     */
    public function customers(): CustomersModule
    {
        if (!$this->customers) {
            $this->customers = new CustomersModule($this);
            $this->customers->initialize($this->config, $this->getLogger());
        }

        return $this->customers;
    }

    /**
     * Acesso ao módulo Webhooks
     */
    public function webhooks(): WebhooksModule
    {
        if (!$this->webhooks) {
            $this->webhooks = new WebhooksModule($this);
            $this->webhooks->initialize($this->config, $this->getLogger());
        }

        return $this->webhooks;
    }

    /**
     * Acesso ao módulo Tracking
     */
    public function tracking(): TrackingModule
    {
        if (!$this->tracking) {
            $this->tracking = new TrackingModule($this);
            $this->tracking->initialize($this->config, $this->getLogger());
        }

        return $this->tracking;
    }

    /**
     * Acesso ao módulo User Management
     */
    public function userManagement(): UserManagementModule
    {
        if (!$this->userManagement) {
            $this->userManagement = new UserManagementModule($this);
            $this->userManagement->initialize($this->config, $this->getLogger());
        }

        return $this->userManagement;
    }

    /**
     * Acesso ao módulo Subscriptions
     */
    public function subscriptions(): SubscriptionsModule
    {
        if (!$this->subscriptions) {
            $this->subscriptions = new SubscriptionsModule($this);
            $this->subscriptions->initialize($this->config, $this->getLogger());
        }

        return $this->subscriptions;
    }

    /**
     * Validar configuração mínima
     */
    private function validateMinimalConfig(array $config): void
    {
        $requiredFields = ['credentials'];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                throw new ConfigurationException(
                    "Missing required configuration field: {$field}",
                    0,
                    null,
                    ['field' => $field, 'config' => array_keys($config)]
                );
            }
        }

        $credentials = $config['credentials'];
        $requiredCredentials = ['tenant_id', 'api_key'];

        foreach ($requiredCredentials as $field) {
            if (!isset($credentials[$field]) || empty($credentials[$field])) {
                throw new ConfigurationException(
                    "Missing required credential: {$field}",
                    0,
                    null,
                    ['field' => $field, 'credentials' => array_keys($credentials)]
                );
            }
        }
    }

    /**
     * Verificar se SDK está inicializado antes de executar operação
     */
    private function requireInitialized(string $operation): void
    {
        if (!$this->initialized) {
            throw new SDKException(
                "SDK must be initialized before calling {$operation}(). Call initialize() first.",
                0,
                null,
                ['operation' => $operation, 'initialized' => false]
            );
        }
    }

    /**
     * Inicializar componentes core
     */
    /**
     * Inicializar core components sob demanda (Lazy Loading)
     */
    private function initializeCoreComponents(): void
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client($this->config);
        }

        if ($this->authManager === null) {
            $this->authManager = new AuthManager($this->getHttpClient(), $this->config);
        }

        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new EventDispatcher();
        }

        if ($this->logger === null) {
            $this->logger = new Logger($this->config);
        }

        if ($this->cache === null) {
            $this->cache = new CacheManager($this->config);
        }
    }

    /**
     * Obter HTTP Client (Lazy Loading)
     */
    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client($this->config);
        }
        return $this->httpClient;
    }

    /**
     * Obter Auth Manager (Lazy Loading)
     */
    private function getAuthManager(): AuthManager
    {
        if ($this->authManager === null) {
            $this->authManager = new AuthManager($this->getHttpClient(), $this->config);
        }
        return $this->authManager;
    }

    /**
     * Obter Event Dispatcher (Lazy Loading)
     */
    private function getEventDispatcher(): EventDispatcher
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new EventDispatcher();
        }
        return $this->eventDispatcher;
    }

    /**
     * Obter Logger (Lazy Loading)
     */
    private function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = new Logger($this->config);
        }
        return $this->logger;
    }

    /**
     * Obter Cache Manager (Lazy Loading)
     */
    private function getCache(): CacheManager
    {
        if ($this->cache === null) {
            $this->cache = new CacheManager($this->config);
        }
        return $this->cache;
    }

    /**
     * Obter estatísticas do SDK
     */
    public function getStats(): array
    {
        return [
            'initialized' => $this->initialized,
            'authenticated' => $this->isAuthenticated(),
            'environment' => $this->config->getEnvironment(),
            'tenant_id' => $this->config->getTenantId(),
            'modules_loaded' => [
                'organization' => $this->organization !== null,
                'products' => $this->products !== null,
                'checkout' => $this->checkout !== null,
                'payments' => $this->payments !== null,
                'customers' => $this->customers !== null,
                'webhooks' => $this->webhooks !== null,
                'tracking' => $this->tracking !== null,
                'user_management' => $this->userManagement !== null,
                'subscriptions' => $this->subscriptions !== null,
            ],
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Habilitar modo debug
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->config->set('debug', $enabled);
        $this->logger?->setDebugMode($enabled);
    }

    /**
     * Criar User Service Factory
     *
     * Cria uma factory para gerenciar services do UserManagement
     * com todas as dependências necessárias injetadas.
     *
     * @return UserServiceFactory Factory configurada
     */
    public function createUserServiceFactory(): UserServiceFactory
    {
        $this->requireInitialized('createUserServiceFactory');

        return new UserServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Versão do SDK
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }
}
