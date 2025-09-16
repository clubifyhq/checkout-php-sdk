<?php

declare(strict_types=1);

namespace Clubify\Checkout;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Config\ConfigurationInterface;
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

    // Core components (will be injected)
    private mixed $httpClient = null;
    private mixed $authManager = null;
    private mixed $eventDispatcher = null;
    private mixed $logger = null;
    private mixed $cache = null;

    // Module instances (will be created on demand)
    private mixed $organization = null;
    private mixed $products = null;
    private mixed $checkout = null;
    private mixed $payments = null;
    private mixed $customers = null;
    private mixed $webhooks = null;

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

        // TODO: Inicializar core components quando estiverem implementados
        // $this->initializeCoreComponents();
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
            // TODO: Implementar lógica de inicialização completa
            // - Autenticação automática
            // - Validação de conectividade
            // - Setup de módulos

            $this->initialized = true;
            $this->initializing = false;

            return [
                'success' => true,
                'authenticated' => $this->isAuthenticated(),
                'tenant_id' => $this->config->getTenantId(),
                'environment' => $this->config->getEnvironment(),
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
        // TODO: Implementar verificação de autenticação
        return $this->authManager?->isAuthenticated() ?? false;
    }

    /**
     * Fazer logout (limpar autenticação)
     */
    public function logout(): void
    {
        // TODO: Implementar logout
        $this->authManager?->logout();
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
        $this->requireInitialized('setupOrganization');

        // TODO: Implementar quando OrganizationModule estiver pronto
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
        $this->requireInitialized('createCompleteProduct');

        // TODO: Implementar quando ProductsModule estiver pronto
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
        $this->requireInitialized('createCheckoutSession');

        // TODO: Implementar quando CheckoutModule estiver pronto
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
        $this->requireInitialized('processOneClick');

        // TODO: Implementar quando CheckoutModule estiver pronto
        return $this->checkout()->oneClick($paymentData);
    }

    /**
     * Acesso ao módulo Organization
     */
    public function organization(): mixed // TODO: OrganizationModule
    {
        if (!$this->organization) {
            // TODO: Criar instância do OrganizationModule
            // $this->organization = new OrganizationModule($this);
            throw new SDKException('OrganizationModule not implemented yet');
        }

        return $this->organization;
    }

    /**
     * Acesso ao módulo Products
     */
    public function products(): mixed // TODO: ProductsModule
    {
        if (!$this->products) {
            // TODO: Criar instância do ProductsModule
            // $this->products = new ProductsModule($this);
            throw new SDKException('ProductsModule not implemented yet');
        }

        return $this->products;
    }

    /**
     * Acesso ao módulo Checkout
     */
    public function checkout(): mixed // TODO: CheckoutModule
    {
        if (!$this->checkout) {
            // TODO: Criar instância do CheckoutModule
            // $this->checkout = new CheckoutModule($this);
            throw new SDKException('CheckoutModule not implemented yet');
        }

        return $this->checkout;
    }

    /**
     * Acesso ao módulo Payments
     */
    public function payments(): mixed // TODO: PaymentsModule
    {
        if (!$this->payments) {
            // TODO: Criar instância do PaymentsModule
            // $this->payments = new PaymentsModule($this);
            throw new SDKException('PaymentsModule not implemented yet');
        }

        return $this->payments;
    }

    /**
     * Acesso ao módulo Customers
     */
    public function customers(): mixed // TODO: CustomersModule
    {
        if (!$this->customers) {
            // TODO: Criar instância do CustomersModule
            // $this->customers = new CustomersModule($this);
            throw new SDKException('CustomersModule not implemented yet');
        }

        return $this->customers;
    }

    /**
     * Acesso ao módulo Webhooks
     */
    public function webhooks(): mixed // TODO: WebhooksModule
    {
        if (!$this->webhooks) {
            // TODO: Criar instância do WebhooksModule
            // $this->webhooks = new WebhooksModule($this);
            throw new SDKException('WebhooksModule not implemented yet');
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
        // TODO: Implementar quando componentes estiverem prontos
        // $this->httpClient = new HttpClient($this->config);
        // $this->authManager = new AuthManager($this->httpClient, $this->config);
        // $this->eventDispatcher = new EventDispatcher();
        // $this->logger = new Logger($this->config);
        // $this->cache = new CacheManager($this->config);
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
        // TODO: Propagar para logger quando estiver implementado
    }

    /**
     * Versão do SDK
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }
}