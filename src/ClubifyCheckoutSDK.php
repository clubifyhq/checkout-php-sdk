<?php

declare(strict_types=1);

namespace Clubify\Checkout;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Auth\AuthManager;
use Clubify\Checkout\Core\Auth\CredentialManager;
use Clubify\Checkout\Core\Auth\EncryptedFileStorage;
use Clubify\Checkout\Core\Events\EventDispatcher;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Modules\Organization\OrganizationModule;
use Clubify\Checkout\Modules\Products\ProductsModule;
use Clubify\Checkout\Modules\Checkout\CheckoutModule;
use Clubify\Checkout\Modules\Cart\CartModule;
use Clubify\Checkout\Modules\Payments\PaymentsModule;
use Clubify\Checkout\Modules\Customers\CustomersModule;
use Clubify\Checkout\Modules\Webhooks\WebhooksModule;
use Clubify\Checkout\Modules\Tracking\TrackingModule;
use Clubify\Checkout\Modules\UserManagement\UserManagementModule;
use Clubify\Checkout\Modules\Offer\OfferModule;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\Customers\Factories\CustomersServiceFactory;
use Clubify\Checkout\Modules\Products\Factories\ProductsServiceFactory;
use Clubify\Checkout\Modules\Cart\Factories\CartServiceFactory;
use Clubify\Checkout\Modules\Webhooks\Factories\WebhooksServiceFactory;
use Clubify\Checkout\Modules\Notifications\Factories\NotificationsServiceFactory;
use Clubify\Checkout\Modules\Tracking\Factories\TrackingServiceFactory;
use Clubify\Checkout\Modules\Subscriptions\SubscriptionsModule;
use Clubify\Checkout\Modules\Subscriptions\Factories\SubscriptionsServiceFactory;
use Clubify\Checkout\Modules\Orders\Factories\OrdersServiceFactory;
use Clubify\Checkout\Modules\Payments\Factories\PaymentsServiceFactory;
use Clubify\Checkout\Modules\SuperAdmin\SuperAdminModule;
use Clubify\Checkout\Modules\Offer\Factories\OfferServiceFactory;
use Clubify\Checkout\Enums\Environment;
use Clubify\Checkout\Exceptions\ConfigurationException;
use Clubify\Checkout\Exceptions\SDKException;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService;
use Clubify\Checkout\Modules\UserManagement\Factories\TenantServiceFactory;

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
    private string $operatingMode = 'single_tenant'; // 'single_tenant' | 'super_admin'

    // Core components
    private ?Client $httpClient = null;
    private ?AuthManager $authManager = null;
    private ?CredentialManager $credentialManager = null;
    private ?EventDispatcher $eventDispatcher = null;
    private ?Logger $logger = null;
    private ?CacheManager $cache = null;

    // Module instances (created on demand)
    private ?OrganizationModule $organization = null;
    private ?ProductsModule $products = null;
    private ?CheckoutModule $checkout = null;
    private ?CartModule $cart = null;
    private ?PaymentsModule $payments = null;
    private ?CustomersModule $customers = null;
    private ?WebhooksModule $webhooks = null;
    private ?TrackingModule $tracking = null;
    private ?UserManagementModule $userManagement = null;
    private ?SubscriptionsModule $subscriptions = null;
    private ?SuperAdminModule $superAdmin = null;
    private ?OfferModule $offer = null;

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
     * @param bool $skipHealthCheck Pular verificação de health check (útil para testes)
     * @return array Resultado da inicialização
     * @throws SDKException
     */
    public function initialize(bool $skipHealthCheck = false): array
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

            // Validação de conectividade (Lazy Loading) - pode ser pulada para testes
            $connectivityCheck = true;
            if (!$skipHealthCheck) {
                $connectivityCheck = $this->getHttpClient()->healthCheck();
                if (!$connectivityCheck) {
                    throw new SDKException('API connectivity check failed');
                }
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
                'health_check_skipped' => $skipHealthCheck,
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
     * Verificar se API key foi validada
     */
    public function isApiKeyValidated(): bool
    {
        if ($this->authManager === null) {
            return false;
        }
        return $this->authManager->isApiKeyValidated();
    }

    /**
     * Verificar se precisa fazer login de usuário
     */
    public function requiresUserLogin(): bool
    {
        if ($this->authManager === null) {
            return false;
        }
        return $this->authManager->requiresUserLogin();
    }

    /**
     * Inicializar SDK como super admin
     */
    public function initializeAsSuperAdmin(array $superAdminCredentials): array
    {
        $this->operatingMode = 'super_admin';

        // Criar storage criptografado para credenciais
        $storageDir = sys_get_temp_dir() . '/clubify_sdk_storage';
        // Usar chave consistente baseada na configuração, não timestamp
        $encryptionKey = hash('sha256', 'clubify_sdk_encryption_key_' . ($this->config->getApiKey() ?? 'default_key'));

        $storage = new EncryptedFileStorage($storageDir, $encryptionKey);
        $this->credentialManager = new CredentialManager($storage);

        // Configurar credenciais de super admin na configuração
        $this->config->setSuperAdminCredentials($superAdminCredentials);

        // Configurar credential manager no auth manager
        $authManager = $this->getAuthManager();
        $authManager->setCredentialManager($this->credentialManager);

        // Autenticar como super admin
        $authResult = $authManager->authenticateAsSuperAdmin($superAdminCredentials);

        if (!$authResult) {
            throw new SDKException('Super admin authentication failed');
        }

        $this->initialized = true;

        return [
            'success' => true,
            'mode' => 'super_admin',
            'authenticated' => true,
            'role' => 'super_admin',
            'timestamp' => date('c')
        ];
    }

    /**
     * Criar tenant (apenas super admin)
     */
    public function createTenant(array $tenantData): array
    {
        $this->requireSuperAdminMode();

        // Criar tenant e tenant_admin
        $result = $this->getAuthManager()->createTenantCredentials(
            $tenantData['name'],
            $tenantData
        );

        return $result;
    }

    /**
     * Criar organização REAL (nova hierarquia organizacional)
     */
    public function createOrganization(array $organizationData): array
    {
        $this->requireSuperAdminMode();

        // Usar OrganizationModule para criar organização real
        return $this->organization()->setupOrganization($organizationData);
    }

    /**
     * ✅ NEW: Autenticar usando Organization API Key
     *
     * Este método autentica usando Organization-Level API Keys e configura
     * automaticamente:
     * - Access token no TokenStorage para uso em requisições HTTP
     * - Headers organizacionais (X-Organization-Id, X-Tenant-Id)
     * - Contexto organizacional na configuração
     *
     * @param string $organizationId ID da organização
     * @param string $apiKey Organization API Key (clb_org_*, clb_multi_*, clb_tenant_*)
     * @param string|null $tenantId Tenant específico (opcional, para cross-tenant keys)
     * @return array Resultado da autenticação com access_token, scope, permissions, etc.
     */
    public function authenticateWithOrganizationApiKey(
        string $organizationId,
        string $apiKey,
        ?string $tenantId = null
    ): array {
        $orgAuthManager = new \Clubify\Checkout\Core\Auth\OrganizationAuthManager(
            $this->config,
            $this->getHttpClient(),
            $this->getLogger()
        );

        $result = $orgAuthManager->authenticateWithOrganizationApiKey($organizationId, $apiKey, $tenantId);

        // IMPORTANTE: Também atualizar o AuthManager regular para que o token seja usado nas requisições
        if ($result['success'] && isset($result['access_token'])) {
            $authManager = $this->getAuthManager();

            // Armazenar token no TokenStorage do AuthManager
            $tokenStorage = new \ReflectionClass($authManager);
            $tokenStorageProperty = $tokenStorage->getProperty('tokenStorage');
            $tokenStorageProperty->setAccessible(true);
            $storage = $tokenStorageProperty->getValue($authManager);

            if ($storage) {
                $storage->storeAccessToken($result['access_token'], $result['expires_in']);
                if (isset($result['refresh_token'])) {
                    $storage->storeRefreshToken($result['refresh_token']);
                }
            }

            // Marcar SDK como inicializado após autenticação bem-sucedida
            $this->initialized = true;
        }

        return $result;
    }

    /**
     * ✅ NEW: Autenticar com acesso total à organização
     */
    public function authenticateAsOrganization(string $organizationId, string $organizationApiKey): array
    {
        return $this->authenticateWithOrganizationApiKey($organizationId, $organizationApiKey);
    }

    /**
     * ✅ NEW: Autenticar com acesso multi-tenant
     */
    public function authenticateAsCrossTenant(
        string $organizationId,
        string $crossTenantApiKey,
        string $targetTenantId
    ): array {
        return $this->authenticateWithOrganizationApiKey($organizationId, $crossTenantApiKey, $targetTenantId);
    }

    /**
     * ✅ NEW: Verificar se está autenticado com organization key
     */
    public function isOrganizationAuthenticated(): bool
    {
        $orgAuthManager = new \Clubify\Checkout\Core\Auth\OrganizationAuthManager(
            $this->config,
            $this->getHttpClient(),
            $this->getLogger()
        );

        return $orgAuthManager->isAuthenticated();
    }

    /**
     * ✅ NEW: Obter contexto organizacional atual
     */
    public function getOrganizationContext(): array
    {
        $orgAuthManager = new \Clubify\Checkout\Core\Auth\OrganizationAuthManager(
            $this->config,
            $this->getHttpClient(),
            $this->getLogger()
        );

        return $orgAuthManager->getOrganizationContext();
    }

    /**
     * ✅ NEW: Setar contexto de tenant/organization para requisições subsequentes
     *
     * Este método atualiza a configuração do SDK com tenant_id e organization_id
     * que serão incluídos nos headers de todas as requisições HTTP subsequentes.
     *
     * Use este método após criar um tenant ou quando precisar alternar o contexto
     * de operações para um tenant específico.
     *
     * @param string|null $tenantId ID do tenant (X-Tenant-Id header)
     * @param string|null $organizationId ID da organization (X-Organization-Id header)
     */
    public function setTenantContext(?string $tenantId, ?string $organizationId = null): void
    {
        if ($tenantId) {
            $this->config->set('tenant_id', $tenantId);
            $this->config->set('credentials.tenant_id', $tenantId);
        }

        if ($organizationId) {
            $this->config->set('organization_id', $organizationId);
            $this->config->set('credentials.organization_id', $organizationId);
        }

        $this->getLogger()->info('Tenant context updated', [
            'tenant_id' => $tenantId,
            'organization_id' => $organizationId
        ]);
    }

    /**
     * Registrar tenant existente para permitir alternância de contexto
     */
    public function registerExistingTenant(string $tenantId, array $tenantData = []): array
    {
        $this->requireSuperAdminMode();
        return $this->getAuthManager()->registerExistingTenant($tenantId, $tenantData);
    }

    /**
     * Alternar para tenant específico
     */
    public function switchToTenant(string $tenantId): array
    {
        $this->requireSuperAdminMode();

        // CORREÇÃO: Limpar completamente o cache de autenticação do super admin
        // antes de alternar para evitar reutilização do JWT incorreto
        $this->clearAuthenticationCache();

        $result = $this->getAuthManager()->switchToTenant($tenantId);

        // Verificar se a alternância foi bem-sucedida
        if (!($result['success'] ?? false)) {
            throw new \Exception('Failed to switch to tenant: ' . ($result['error'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Alternar para super admin
     */
    public function switchToSuperAdmin(): void
    {
        $this->requireSuperAdminMode();
        $this->getAuthManager()->switchToSuperAdmin();
    }

    /**
     * Obter contexto atual
     */
    public function getCurrentContext(): array
    {
        if ($this->operatingMode === 'single_tenant') {
            return [
                'mode' => 'single_tenant',
                'tenant_id' => $this->config->getTenantId(),
                'role' => 'tenant_admin'
            ];
        }

        $authManager = $this->getAuthManager();
        return [
            'mode' => 'super_admin',
            'current_role' => $authManager->getCurrentRole(),
            'available_contexts' => $authManager->getAvailableContexts()
        ];
    }

    /**
     * Verificar se está em modo super admin
     */
    public function isSuperAdminMode(): bool
    {
        return $this->operatingMode === 'super_admin';
    }

    /**
     * Fazer login com usuário e senha para obter access token
     */
    public function loginUser(string $email, string $password, ?string $deviceFingerprint = null): array
    {
        return $this->getAuthManager()->login($email, $password, null, $deviceFingerprint);
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
     * Criar oferta completa (método de conveniência)
     *
     * @param array $offerData Dados da oferta
     * @return array Oferta criada
     * @throws SDKException
     */
    public function createOffer(array $offerData): array
    {
        return $this->offer()->createOffer($offerData);
    }

    /**
     * Obter oferta pública por slug (método de conveniência)
     *
     * @param string $slug Slug da oferta
     * @return array|null Dados da oferta pública
     * @throws SDKException
     */
    public function getPublicOffer(string $slug): ?array
    {
        return $this->offer()->getPublicOffer($slug);
    }

    /**
     * Configurar tema de oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $themeData Dados do tema
     * @return array Resultado da configuração
     * @throws SDKException
     */
    public function configureOfferTheme(string $offerId, array $themeData): array
    {
        return $this->offer()->configureTheme($offerId, $themeData);
    }

    /**
     * Configurar layout de oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $layoutData Dados do layout
     * @return array Resultado da configuração
     * @throws SDKException
     */
    public function configureOfferLayout(string $offerId, array $layoutData): array
    {
        return $this->offer()->configureLayout($offerId, $layoutData);
    }

    /**
     * Adicionar upsell à oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $upsellData Dados do upsell
     * @return array Upsell criado
     * @throws SDKException
     */
    public function addOfferUpsell(string $offerId, array $upsellData): array
    {
        return $this->offer()->addUpsell($offerId, $upsellData);
    }

    /**
     * Criar carrinho (método de conveniência)
     *
     * @param string $sessionId ID da sessão
     * @param array $data Dados do carrinho
     * @return array Carrinho criado
     * @throws SDKException
     */
    public function createCart(string $sessionId, array $data = []): array
    {
        return $this->cart()->create($sessionId, $data);
    }

    /**
     * Buscar carrinho por ID (método de conveniência)
     *
     * @param string $id ID do carrinho
     * @return array|null Carrinho encontrado
     * @throws SDKException
     */
    public function findCart(string $id): ?array
    {
        return $this->cart()->find($id);
    }

    /**
     * Adicionar item ao carrinho (método de conveniência)
     *
     * @param string $cartId ID do carrinho
     * @param array $itemData Dados do item
     * @return array Carrinho atualizado
     * @throws SDKException
     */
    public function addCartItem(string $cartId, array $itemData): array
    {
        return $this->cart()->addItem($cartId, $itemData);
    }

    /**
     * Aplicar promoção ao carrinho (método de conveniência)
     *
     * @param string $cartId ID do carrinho
     * @param string $promotionCode Código da promoção
     * @return array Carrinho atualizado
     * @throws SDKException
     */
    public function applyCartPromotion(string $cartId, string $promotionCode): array
    {
        return $this->cart()->applyPromotion($cartId, $promotionCode);
    }

    /**
     * Processar checkout one-click no carrinho (método de conveniência)
     *
     * @param string $cartId ID do carrinho
     * @param array $paymentData Dados do pagamento
     * @return array Resultado do processamento
     * @throws SDKException
     */
    public function processCartOneClick(string $cartId, array $paymentData): array
    {
        return $this->cart()->processOneClick($cartId, $paymentData);
    }

    /**
     * Iniciar navegação de fluxo (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $context Contexto da navegação
     * @return array Navegação iniciada
     * @throws SDKException
     */
    public function startCartNavigation(string $offerId, array $context = []): array
    {
        return $this->cart()->startFlowNavigation($offerId, $context);
    }

    /**
     * Setup completo de carrinho (método de conveniência)
     *
     * @param array $cartData Dados do carrinho
     * @return array Carrinho criado e configurado
     * @throws SDKException
     */
    public function setupCompleteCart(array $cartData): array
    {
        return $this->cart()->setupComplete($cartData);
    }

    /**
     * Acesso ao módulo Organization
     */
    public function organization(): OrganizationModule
    {
        if (!$this->organization) {
            $this->organization = new OrganizationModule();
            $this->organization->initialize($this->config, $this->getLogger());
            $this->organization->setDependencies(
                $this->getHttpClient(),
                $this->getCache(),
                $this->getEventDispatcher()
            );
        }

        // Lazy inject UserManagement services (only once, on first access)
        if ($this->organization->needsUserManagementInjection()) {
            try {
                $this->organization->setUserManagementServices(
                    $this->userManagement()->getTenantService()
                );
            } catch (\Exception $e) {
                $this->getLogger()->warning('Failed to inject UserManagement services into Organization', [
                    'error' => $e->getMessage()
                ]);
            }
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
     * Acesso ao módulo Cart
     */
    public function cart(): CartModule
    {
        if (!$this->cart) {
            $this->cart = new CartModule();
            $this->cart->initialize($this->config, $this->getLogger());
            $this->cart->setDependencies(
                $this->getHttpClient(),
                $this->getCache(),
                $this->getEventDispatcher()
            );
        }

        return $this->cart;
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
            $this->configureModuleWithDependencies($this->webhooks);
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
     * Acesso ao módulo Offer
     */
    public function offer(): OfferModule
    {
        if (!$this->offer) {
            $this->offer = new OfferModule($this);
            $this->offer->initialize($this->config, $this->getLogger());
            $this->configureModuleWithDependencies($this->offer);
        }

        return $this->offer;
    }

    /**
     * Acesso ao módulo Super Admin
     */
    public function superAdmin(): SuperAdminModule
    {
        if (!$this->superAdmin) {
            $this->superAdmin = new SuperAdminModule();
            $this->superAdmin->initialize($this->config, $this->getLogger());
            $this->superAdmin->setDependencies(
                $this->getHttpClient(),
                $this->getCache(),
                $this->getEventDispatcher()
            );

            // Injetar serviços centralizados necessários
            $this->superAdmin->setCentralizedServices(
                $this->userManagement()->getUserService(),
                $this->userManagement()->getTenantService(),
                $this->organization()->apiKey(),
                $this->organization()->tenant()
            );
        }

        return $this->superAdmin;
    }

    /**
     * Validar configuração mínima
     */
    private function validateMinimalConfig(array $config): void
    {
        // Para uso com super-admin, credentials pode estar vazio inicialmente
        // O super-admin se autentica com email/password e cria credenciais dinamicamente
        if (!isset($config['credentials'])) {
            // Permitir configuração sem credentials para super-admin workflow
            return;
        }

        $credentials = $config['credentials'];

        // Se credentials está vazio, permitir (será usado super-admin email/password)
        if (empty($credentials)) {
            return;
        }

        // Verificar se é configuração para super admin ou tenant
        $hasApiKey = isset($credentials['api_key']) && !empty($credentials['api_key']);
        $hasTenantId = isset($credentials['tenant_id']) && !empty($credentials['tenant_id']);
        $hasEmailPassword = (isset($credentials['email']) && !empty($credentials['email'])) ||
                           (isset($credentials['password']) && !empty($credentials['password']));

        // Se tem credenciais, validar baseado no tipo
        if ($hasApiKey || $hasTenantId) {
            // Fluxo tradicional com API key - validar normalmente
            if (!$hasApiKey) {
                throw new ConfigurationException(
                    "Missing required credential: api_key",
                    0,
                    null,
                    ['field' => 'api_key', 'credentials' => array_keys($credentials)]
                );
            }

            // Para single tenant, exigir tenant_id
            if (!$hasTenantId && !$this->detectSuperAdminConfig($credentials)) {
                throw new ConfigurationException(
                    "Missing required credential: tenant_id",
                    0,
                    null,
                    ['field' => 'tenant_id', 'credentials' => array_keys($credentials)]
                );
            }
        }
        // Se tem email/password, é fluxo super-admin - não validar mais nada
        // Se não tem nada, também permitir (será configurado depois)
    }

    /**
     * Detectar se a configuração é para super admin
     */
    private function detectSuperAdminConfig(array $credentials): bool
    {
        // Se tem api_key mas não tem tenant_id, pode ser super admin
        $hasApiKey = isset($credentials['api_key']) && !empty($credentials['api_key']);
        $hasTenantId = isset($credentials['tenant_id']) && !empty($credentials['tenant_id']);

        // Se tem role super_admin explícito
        if (isset($credentials['role']) && $credentials['role'] === 'super_admin') {
            return true;
        }

        // Se a API key começa com super_admin_ ou sk_super_
        if ($hasApiKey && (
            strpos($credentials['api_key'], 'super_admin_') === 0 ||
            strpos($credentials['api_key'], 'sk_super_') === 0
        )) {
            return true;
        }

        // Se tem api_key mas não tem tenant_id, assumir super admin por compatibilidade
        return $hasApiKey && !$hasTenantId;
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
     * Verificar se está em modo super admin
     */
    private function requireSuperAdminMode(): void
    {
        if ($this->operatingMode !== 'super_admin') {
            throw new SDKException('SDK must be in super_admin mode for this operation');
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
            $this->httpClient = new Client($this->config, $this->getLogger());
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
    public function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            // Criar Client sem AuthManager primeiro para evitar dependência circular
            $this->httpClient = new Client($this->config, $this->getLogger());
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
            // Configurar AuthManager no Client para resolver dependência circular
            $this->getHttpClient()->setAuthManager($this->authManager);

        }
        return $this->authManager;
    }

    /**
     * Obter Event Dispatcher (Lazy Loading)
     */
    public function getEventDispatcher(): EventDispatcher
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new EventDispatcher();
        }
        return $this->eventDispatcher;
    }

    /**
     * Configurar módulo com dependências necessárias (HttpClient, Cache, EventDispatcher)
     */
    private function configureModuleWithDependencies(object $module): void
    {
        // Verificar se o módulo suporta setDependencies
        if (method_exists($module, 'setDependencies')) {
            $module->setDependencies(
                $this->getHttpClient(),  // ← HttpClient with AuthManager configured
                $this->getCache(),
                $this->getEventDispatcher()
            );

        }
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
    public function getCache(): CacheManagerInterface
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
                'cart' => $this->cart !== null,
                'payments' => $this->payments !== null,
                'customers' => $this->customers !== null,
                'webhooks' => $this->webhooks !== null,
                'tracking' => $this->tracking !== null,
                'user_management' => $this->userManagement !== null,
                'subscriptions' => $this->subscriptions !== null,
                'offer' => $this->offer !== null,
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
            $this->getEventDispatcher(),
            $this
        );
    }

    /**
     * Cria TenantService com todas as dependências necessárias
     */
    public function createTenantService(): TenantService
    {
        $this->requireInitialized('createTenantService');

        return TenantServiceFactory::create(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Criar Customers Service Factory
     *
     * Cria uma factory para gerenciar services do Customers
     * com todas as dependências necessárias injetadas.
     *
     * @return CustomersServiceFactory Factory configurada
     */
    public function createCustomersServiceFactory(): CustomersServiceFactory
    {
        return new CustomersServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Cria uma factory para gerenciar services do Products
     * com todas as dependências necessárias injetadas.
     *
     * @return ProductsServiceFactory Factory configurada
     */
    public function createProductsServiceFactory(): ProductsServiceFactory
    {
        return new ProductsServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Criar Cart Service Factory
     *
     * Cria uma factory para gerenciar services do Cart
     * com todas as dependências necessárias injetadas.
     *
     * @return CartServiceFactory Factory configurada
     */
    public function createCartServiceFactory(): CartServiceFactory
    {
        return new CartServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Cria uma factory para gerenciar services do Webhooks
     * com todas as dependências necessárias injetadas.
     *
     * @return WebhooksServiceFactory Factory configurada
     */
    public function createWebhooksServiceFactory(): WebhooksServiceFactory
    {
        return new WebhooksServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Cria uma factory para gerenciar services do Notifications
     * com todas as dependências necessárias injetadas.
     *
     * @return NotificationsServiceFactory Factory configurada
     */
    public function createNotificationsServiceFactory(): NotificationsServiceFactory
    {
        return new NotificationsServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher(),
            $this
        );
    }

    /**
     * Cria uma factory para gerenciar services do Tracking
     * com todas as dependências necessárias injetadas.
     *
     * @return TrackingServiceFactory Factory configurada
     */
    public function createTrackingServiceFactory(): TrackingServiceFactory
    {
        return new TrackingServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher(),
            $this
        );
    }

    /**
     * Cria uma factory para gerenciar services do Subscriptions
     * com todas as dependências necessárias injetadas.
     *
     * @return SubscriptionsServiceFactory Factory configurada
     */
    public function createSubscriptionsServiceFactory(): SubscriptionsServiceFactory
    {
        return new SubscriptionsServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher(),
            $this
        );
    }

    /**
     * Cria uma factory para gerenciar services do Orders
     * com todas as dependências necessárias injetadas.
     *
     * @return OrdersServiceFactory Factory configurada
     */
    public function createOrdersServiceFactory(): OrdersServiceFactory
    {
        return new OrdersServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Criar Payments Service Factory
     *
     * Cria uma factory para gerenciar services do Payments
     * com todas as dependências necessárias injetadas.
     *
     * @return PaymentsServiceFactory Factory configurada
     */
    public function createPaymentsServiceFactory(): PaymentsServiceFactory
    {
        return new PaymentsServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Criar Offer Service Factory
     *
     * Cria uma factory para gerenciar services do Offer
     * com todas as dependências necessárias injetadas.
     *
     * @return OfferServiceFactory Factory configurada
     */
    public function createOfferServiceFactory(): OfferServiceFactory
    {
        return new OfferServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * Helper: Migrar dados de usuário entre tenants
     *
     * Método de conveniência para resolver o problema de dados órfãos
     * quando um usuário é transferido entre tenants.
     *
     * @param string $userId ID do usuário
     * @param string $sourceTenantId Tenant de origem (onde estão os dados órfãos)
     * @param string $targetTenantId Tenant de destino
     * @param array $options Opções de migração
     * @return array Resultado da migração
     */
    public function migrateUserDataBetweenTenants(
        string $userId,
        string $sourceTenantId,
        string $targetTenantId,
        array $options = []
    ): array {
        try {
            $this->getLogger()->info('Iniciando migração de dados via helper method', [
                'user_id' => $userId,
                'source_tenant' => $sourceTenantId,
                'target_tenant' => $targetTenantId
            ]);

            $result = [
                'success' => false,
                'user_id' => $userId,
                'source_tenant' => $sourceTenantId,
                'target_tenant' => $targetTenantId,
                'migrations' => [],
                'errors' => [],
                'started_at' => date('c')
            ];

            // 1. Migrar produtos
            if (!isset($options['skip_products']) || !$options['skip_products']) {
                try {
                    $productsMigration = $this->migrateUserProducts($userId, $sourceTenantId, $targetTenantId);
                    $result['migrations']['products'] = $productsMigration;
                } catch (\Exception $e) {
                    $result['errors'][] = "Erro ao migrar produtos: " . $e->getMessage();
                }
            }

            // 2. TODO: Adicionar migração de outras entidades conforme necessário
            // $customersMigration = $this->migrateUserCustomers($userId, $sourceTenantId, $targetTenantId);
            // $ordersMigration = $this->migrateUserOrders($userId, $sourceTenantId, $targetTenantId);

            $result['success'] = count($result['errors']) === 0;
            $result['completed_at'] = date('c');

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'source_tenant' => $sourceTenantId,
                'target_tenant' => $targetTenantId,
                'completed_at' => date('c')
            ];
        }
    }

    /**
     * Helper privado: Migrar produtos do usuário
     */
    private function migrateUserProducts(string $userId, string $sourceTenantId, string $targetTenantId): array
    {
        $result = [
            'total_found' => 0,
            'migrated' => 0,
            'errors' => []
        ];

        try {
            // Buscar produtos do usuário no tenant de origem
            $products = $this->products()->list([
                'tenant_id' => $sourceTenantId,
                'created_by' => $userId
            ]);

            $result['total_found'] = count($products);

            foreach ($products as $product) {
                try {
                    // Preparar dados do produto
                    $productData = $product;
                    unset($productData['id'], $productData['_id'], $productData['created_at'], $productData['updated_at']);

                    // Criar produto no novo tenant
                    $newProduct = $this->products()->create($productData);
                    $result['migrated']++;

                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'product' => $product['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Erro geral: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Helper: Verificar dados órfãos de um usuário
     *
     * @param string $userId ID do usuário
     * @param string $tenantId ID do tenant para verificar
     * @return array Dados órfãos encontrados
     */
    public function findUserOrphanedData(string $userId, string $tenantId): array
    {
        try {
            $orphanedData = [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'orphaned_items' => [],
                'total_orphaned' => 0,
                'checked_at' => date('c')
            ];

            // Verificar produtos órfãos
            try {
                $products = $this->products()->list([
                    'tenant_id' => $tenantId,
                    'created_by' => $userId
                ]);

                if (count($products) > 0) {
                    $orphanedData['orphaned_items']['products'] = [
                        'count' => count($products),
                        'items' => array_map(function($product) {
                            return [
                                'id' => $product['id'] ?? $product['_id'],
                                'name' => $product['name'] ?? 'Unknown',
                                'type' => $product['type'] ?? 'Unknown'
                            ];
                        }, $products)
                    ];
                }
            } catch (\Exception $e) {
                $orphanedData['errors']['products'] = $e->getMessage();
            }

            // Calcular total
            $orphanedData['total_orphaned'] = array_sum(
                array_column($orphanedData['orphaned_items'], 'count')
            );

            return $orphanedData;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ];
        }
    }

    /**
     * Helper: Transferir usuário completo com migração automática
     *
     * @param string $userId ID do usuário
     * @param string $newTenantId Novo tenant
     * @param array $options Opções da transferência
     * @return array Resultado completo da operação
     */
    public function transferUserWithDataMigration(
        string $userId,
        string $newTenantId,
        array $options = []
    ): array {
        // Este método seria uma integração completa que:
        // 1. Detecta o tenant atual do usuário
        // 2. Migra todos os dados
        // 3. Atualiza o tenant do usuário
        // 4. Verifica a integridade

        return [
            'success' => false,
            'note' => 'Este método requer integração com user-management-service para detectar tenant atual do usuário',
            'user_id' => $userId,
            'new_tenant' => $newTenantId,
            'recommendation' => 'Use migrateUserDataBetweenTenants() se você souber o tenant de origem'
        ];
    }

    /**
     * Limpa completamente o cache de autenticação
     *
     * CORREÇÃO: Necessário para evitar reutilização de JWT do super admin
     * quando alternando para contexto de tenant
     */
    private function clearAuthenticationCache(): void
    {
        try {
            // Limpar cache do AuthManager se disponível
            if ($this->authManager) {
                // Método para limpar tokens em cache
                if (method_exists($this->authManager, 'clearTokenCache')) {
                    $this->authManager->clearTokenCache();
                }

                // Limpar informações de usuário em cache
                if (method_exists($this->authManager, 'clearUserInfo')) {
                    $this->authManager->clearUserInfo();
                }
            }

            // Limpar cache do CredentialManager se disponível
            if ($this->credentialManager) {
                if (method_exists($this->credentialManager, 'clearCache')) {
                    $this->credentialManager->clearCache();
                }
            }

            // Limpar cache do HTTP Client se disponível
            if ($this->httpClient) {
                if (method_exists($this->httpClient, 'clearAuthHeaders')) {
                    $this->httpClient->clearAuthHeaders();
                }
            }

            $this->getLogger()->info('Authentication cache cleared for context switch');

        } catch (\Exception $e) {
            $this->getLogger()->warning('Failed to clear authentication cache', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Versão do SDK
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }
}
