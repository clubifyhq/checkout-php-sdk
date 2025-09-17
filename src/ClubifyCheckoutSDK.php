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

        // Inicializar core components
        $this->initializeCoreComponents();
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
            // Autenticação automática
            $authResult = $this->authManager->authenticate();

            // Validação de conectividade
            $connectivityCheck = $this->httpClient->healthCheck();

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
        return $this->authManager?->isAuthenticated() ?? false;
    }

    /**
     * Fazer logout (limpar autenticação)
     */
    public function logout(): void
    {
        $this->authManager?->logout();
        $this->cache?->clear();
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
     * Acesso ao módulo Organization
     */
    public function organization(): OrganizationModule
    {
        if (!$this->organization) {
            $this->organization = new OrganizationModule($this);
            $this->organization->initialize($this->config, $this->logger);
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
            $this->products->initialize($this->config, $this->logger);
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
            $this->checkout->initialize($this->config, $this->logger);
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
            $this->payments->initialize($this->config, $this->logger);
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
            $this->customers->initialize($this->config, $this->logger);
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
            $this->webhooks->initialize($this->config, $this->logger);
        }

        return $this->webhooks;
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
    private function initializeCoreComponents(): void
    {
        $this->httpClient = new Client($this->config);
        $this->authManager = new AuthManager($this->httpClient, $this->config);
        $this->eventDispatcher = new EventDispatcher();
        $this->logger = new Logger($this->config);
        $this->cache = new CacheManager($this->config);
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
     * Versão do SDK
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }
}