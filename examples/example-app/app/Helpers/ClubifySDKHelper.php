<?php

namespace App\Helpers;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Exception;

/**
 * Helper para gerenciar instância singleton do Clubify SDK
 */
class ClubifySDKHelper
{
    private static ?ClubifyCheckoutSDK $instance = null;
    private static bool $configurationLoaded = false;

    /**
     * Obter instância do SDK com lazy loading
     */
    public static function getInstance(): ClubifyCheckoutSDK
    {
        if (self::$instance === null) {
            self::$instance = self::createSDKInstance();

            // Inicializar automaticamente se habilitado
            if (self::shouldAutoInitialize()) {
                try {
                    self::$instance->initialize();
                } catch (\Exception $e) {
                    // Log do erro mas não quebra a aplicação
                    error_log('Clubify SDK auto-initialization failed: ' . $e->getMessage());

                    // Para ambiente de desenvolvimento/teste, tenta inicializar sem health check
                    if (config('app.env') !== 'production') {
                        try {
                            self::$instance->initialize(true);
                            error_log('Clubify SDK initialized with health check skipped');
                        } catch (\Exception $e2) {
                            error_log('Clubify SDK auto-initialization failed even with health check skipped: ' . $e2->getMessage());
                        }
                    }
                }
            }
        }

        return self::$instance;
    }

    /**
     * Verificar se SDK está disponível
     */
    public static function isAvailable(): bool
    {
        try {
            self::getInstance();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Inicializar SDK sob demanda (para testes) com timeout controlado
     */
    public static function initializeForTesting(): bool
    {
        try {
            $sdk = self::getInstance();

            if ($sdk->isInitialized()) {
                return true; // Já inicializado
            }

            // Tentar inicializar com timeout controlado
            $startTime = microtime(true);
            $maxTimeout = 10; // máximo 10 segundos

            set_time_limit($maxTimeout + 5); // PHP timeout um pouco maior

            $initResult = $sdk->initialize(true); // Pular health check para testes

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // em ms

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Clubify SDK inicializado para testes', [
                    'duration_ms' => $duration,
                    'success' => $initResult['success'] ?? false,
                    'init_result' => $initResult,
                    'helper_class' => self::class
                ]);
            }

            return $initResult['success'] ?? false;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Falha ao inicializar SDK para testes', [
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'helper_class' => self::class
                ]);
            }

            return false;
        }
    }

    /**
     * Verificar se SDK pode executar testes mesmo sem inicialização
     */
    public static function canRunBasicTests(): bool
    {
        try {
            $sdk = self::getInstance();

            // Verificar se pelo menos conseguimos acessar os módulos
            $org = $sdk->organization();
            $products = $sdk->products();

            return $org !== null && $products !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obter configuração do SDK
     */
    public static function getConfig(): array
    {
        return config('clubify-checkout', [
            'credentials' => [
                'api_key' => env('CLUBIFY_CHECKOUT_API_KEY'),
                'api_secret' => env('CLUBIFY_CHECKOUT_API_SECRET'),
                'tenant_id' => env('CLUBIFY_CHECKOUT_TENANT_ID'),
            ],
            'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'sandbox'),
            'api' => [
                'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', 'https://checkout.svelve.com/api/v1'),
            ],

            'http' => [
                'timeout' => (int) env('CLUBIFY_CHECKOUT_TIMEOUT', 30),
                'connect_timeout' => (int) env('CLUBIFY_CHECKOUT_CONNECT_TIMEOUT', 10),
                'retry' => [
                    'enabled' => true,
                    'attempts' => (int) env('CLUBIFY_CHECKOUT_RETRY_ATTEMPTS', 3),
                    'delay' => (int) env('CLUBIFY_CHECKOUT_RETRY_DELAY', 1000),
                ],
            ],

            'cache' => [
                'enabled' => env('CLUBIFY_CHECKOUT_CACHE_ENABLED', true),
                'default_ttl' => (int) env('CLUBIFY_CHECKOUT_CACHE_TTL', 3600),
                'prefix' => env('CLUBIFY_CHECKOUT_CACHE_PREFIX', 'clubify_checkout'),
                'store' => env('CLUBIFY_CHECKOUT_CACHE_STORE', 'default'),
            ],

            'logger' => [
                'enabled' => env('CLUBIFY_CHECKOUT_LOGGER_ENABLED', true),
                'level' => env('CLUBIFY_CHECKOUT_LOGGER_LEVEL', 'info'),
                'channel' => env('CLUBIFY_CHECKOUT_LOGGER_CHANNEL', 'single'),
            ],

            'webhooks' => [
                'enabled' => env('CLUBIFY_CHECKOUT_WEBHOOKS_ENABLED', true),
                'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
                'tolerance' => (int) env('CLUBIFY_CHECKOUT_WEBHOOK_TOLERANCE', 300),
            ],

            'features' => [
                'auto_initialize' => env('CLUBIFY_CHECKOUT_AUTO_INITIALIZE', true),
            ],
        ]);
    }

    /**
     * Obter informações de credenciais (sanitizadas)
     */
    public static function getCredentialsInfo(): array
    {
        return [
            'tenant_id' => env('CLUBIFY_CHECKOUT_TENANT_ID', 'NOT_SET'),
            'api_key_present' => !empty(env('CLUBIFY_CHECKOUT_API_KEY')),
            'api_key_first_10' => substr(env('CLUBIFY_CHECKOUT_API_KEY', ''), 0, 10) . '...',
            'api_secret_present' => !empty(env('CLUBIFY_CHECKOUT_API_SECRET')),
            'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'NOT_SET'),
            'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', 'NOT_SET'),
        ];
    }

    /**
     * Verificar se deve inicializar automaticamente
     */
    private static function shouldAutoInitialize(): bool
    {
        return config('clubify-checkout.features.auto_initialize', true);
    }

    /**
     * Resetar instância (útil para testes)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$configurationLoaded = false;
    }

    // ========== SUPER ADMIN METHODS ==========

    /**
     * Get Super Admin SDK instance with elevated privileges
     *
     * @param string|null $superAdminToken Optional super admin token for elevated access
     * @return ClubifyCheckoutSDK
     * @throws Exception When super admin configuration is invalid
     */
    public static function getSuperAdminInstance(?string $superAdminToken = null): ClubifyCheckoutSDK
    {
        $config = self::getSuperAdminConfig($superAdminToken);

        try {
            $sdk = new ClubifyCheckoutSDK($config);

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Super Admin SDK instance created', [
                    'has_token' => !empty($superAdminToken),
                    'helper_class' => self::class
                ]);
            }

            return $sdk;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Failed to create Super Admin SDK instance', [
                    'error' => $e->getMessage(),
                    'helper_class' => self::class
                ]);
            }

            throw new Exception('Failed to create Super Admin SDK instance: ' . $e->getMessage());
        }
    }

    /**
     * Get tenant-specific SDK instance for Super Admin operations
     *
     * @param string $tenantId Target tenant ID
     * @param string|null $superAdminToken Super admin authorization token
     * @return ClubifyCheckoutSDK
     * @throws Exception When tenant configuration is invalid
     */
    public static function getTenantInstance(string $tenantId, ?string $superAdminToken = null): ClubifyCheckoutSDK
    {
        if (empty($tenantId)) {
            throw new Exception('Tenant ID is required for tenant-specific operations');
        }

        $config = self::getTenantConfig($tenantId, $superAdminToken);

        try {
            $sdk = new ClubifyCheckoutSDK($config);

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Tenant SDK instance created', [
                    'tenant_id' => $tenantId,
                    'has_super_admin_token' => !empty($superAdminToken),
                    'helper_class' => self::class
                ]);
            }

            return $sdk;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Failed to create tenant SDK instance', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                    'helper_class' => self::class
                ]);
            }

            throw new Exception("Failed to create SDK instance for tenant {$tenantId}: " . $e->getMessage());
        }
    }

    /**
     * Validate Super Admin credentials and permissions
     *
     * @param string $superAdminToken Super admin token to validate
     * @return array Validation result with permissions
     */
    public static function validateSuperAdminAccess(string $superAdminToken): array
    {
        try {
            $sdk = self::getSuperAdminInstance($superAdminToken);

            // Attempt to validate token by making a test request
            // This would typically be a special endpoint for super admin validation
            $validationResult = [
                'valid' => true,
                'permissions' => self::getSuperAdminPermissions($superAdminToken),
                'expires_at' => null, // This would come from token validation
                'tenant_count' => 0, // This would come from actual API call
            ];

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Super Admin access validated', [
                    'permissions_count' => count($validationResult['permissions']),
                    'helper_class' => self::class
                ]);
            }

            return $validationResult;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->warning('Super Admin access validation failed', [
                    'error' => $e->getMessage(),
                    'helper_class' => self::class
                ]);
            }

            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'permissions' => [],
            ];
        }
    }

    /**
     * Get list of available tenants for Super Admin
     *
     * @param string $superAdminToken Super admin authorization token
     * @param array $filters Optional filters for tenant listing
     * @return array List of tenants with metadata
     */
    public static function getAvailableTenants(string $superAdminToken, array $filters = []): array
    {
        try {
            $sdk = self::getSuperAdminInstance($superAdminToken);

            // This would make an actual API call to get tenants
            // For now, returning a mock structure
            $tenants = [
                // This would be populated by actual API call
                // [
                //     'id' => 'tenant-1',
                //     'name' => 'Tenant 1',
                //     'status' => 'active',
                //     'created_at' => '2024-01-01T00:00:00Z',
                //     'subscription_plan' => 'premium',
                //     'features' => ['feature1', 'feature2']
                // ]
            ];

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Retrieved available tenants for Super Admin', [
                    'tenant_count' => count($tenants),
                    'filters_applied' => !empty($filters),
                    'helper_class' => self::class
                ]);
            }

            return $tenants;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Failed to retrieve available tenants', [
                    'error' => $e->getMessage(),
                    'helper_class' => self::class
                ]);
            }

            throw new Exception('Failed to retrieve available tenants: ' . $e->getMessage());
        }
    }

    /**
     * Switch current context to a specific tenant (for Super Admin)
     *
     * @param string $tenantId Target tenant ID
     * @param string $superAdminToken Super admin authorization token
     * @return bool Success status
     */
    public static function switchToTenant(string $tenantId, string $superAdminToken): bool
    {
        try {
            // Validate access to the tenant
            $sdk = self::getTenantInstance($tenantId, $superAdminToken);

            // Store the current tenant context in session or cache
            if (session()->has('super_admin_mode')) {
                session()->put('current_tenant_id', $tenantId);
                session()->put('tenant_switch_timestamp', now());

                if (function_exists('logger') && app()->bound('config')) {
                    logger()->info('Successfully switched to tenant context', [
                        'tenant_id' => $tenantId,
                        'helper_class' => self::class
                    ]);
                }

                return true;
            }

            return false;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Failed to switch to tenant context', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                    'helper_class' => self::class
                ]);
            }

            return false;
        }
    }

    /**
     * Get Super Admin configuration
     *
     * @param string|null $superAdminToken Optional super admin token
     * @return array Configuration array for super admin operations
     */
    private static function getSuperAdminConfig(?string $superAdminToken = null): array
    {
        $baseConfig = self::getConfig();

        // Override with super admin specific configuration
        $superAdminConfig = array_merge($baseConfig, [
            'credentials' => array_merge($baseConfig['credentials'], [
                'api_key' => env('CLUBIFY_SUPER_ADMIN_API_KEY', $baseConfig['credentials']['api_key']),
                'api_secret' => env('CLUBIFY_SUPER_ADMIN_API_SECRET', $baseConfig['credentials']['api_secret']),
                'super_admin_token' => $superAdminToken,
                'super_admin_mode' => true,
            ]),
            'base_url' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_API_URL', $baseConfig['base_url']),
            'super_admin_prefix' => '',

            'http' => array_merge($baseConfig['http'], [
                'timeout' => 60, // Longer timeout for admin operations
                'retry' => array_merge($baseConfig['http']['retry'], [
                    'attempts' => 2,
                ]),
            ]),
        ]);

        return $superAdminConfig;
    }

    /**
     * Get tenant-specific configuration
     *
     * @param string $tenantId Target tenant ID
     * @param string|null $superAdminToken Super admin authorization token
     * @return array Configuration array for tenant operations
     */
    private static function getTenantConfig(string $tenantId, ?string $superAdminToken = null): array
    {
        $baseConfig = self::getConfig();

        return array_merge($baseConfig, [
            'credentials' => array_merge($baseConfig['credentials'], [
                'tenant_id' => $tenantId,
                'super_admin_token' => $superAdminToken,
                'impersonation_mode' => true,
            ]),

            'http' => array_merge($baseConfig['http'], [
                'headers' => array_merge($baseConfig['http']['headers'] ?? [], [
                    'X-Tenant-ID' => $tenantId,
                    'X-Super-Admin-Token' => $superAdminToken,
                ]),
            ]),
        ]);
    }

    /**
     * Get Super Admin permissions based on token
     *
     * @param string $superAdminToken Super admin authorization token
     * @return array List of permissions
     */
    private static function getSuperAdminPermissions(string $superAdminToken): array
    {
        // This would typically decode the token or make an API call
        // For now, returning a default set of permissions
        return [
            'tenant.list',
            'tenant.create',
            'tenant.read',
            'tenant.update',
            'tenant.delete',
            'tenant.switch',
            'user.impersonate',
            'system.monitor',
            'analytics.view',
            'configuration.manage',
        ];
    }

    // ========== END SUPER ADMIN METHODS ==========

    /**
     * Criar nova instância do SDK
     */
    private static function createSDKInstance(): ClubifyCheckoutSDK
    {
        $config = self::getConfig();

        try {
            $sdk = new ClubifyCheckoutSDK($config);

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Clubify SDK criado via helper', [
                    'config_keys' => array_keys($config),
                    'helper_class' => self::class
                ]);
            }

            return $sdk;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->error('Erro ao criar Clubify SDK via helper', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'helper_class' => self::class
                ]);
            }

            throw $e;
        }
    }
}