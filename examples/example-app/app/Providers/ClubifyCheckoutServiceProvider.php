<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Auth\CredentialStorageInterface;
use Clubify\Checkout\Core\Auth\EncryptedFileStorage;
use Clubify\Checkout\Core\Auth\CredentialManager;

/**
 * Service Provider para configuração do Clubify Checkout SDK
 *
 * SECURITY: Configuração unificada com storage criptografado
 */
class ClubifyCheckoutServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind storage interface
        $this->app->singleton(CredentialStorageInterface::class, function (Application $app) {
            $storageDir = storage_path('app/clubify/credentials');
            $encryptionKey = config('app.key'); // Use Laravel's app key for encryption

            return new EncryptedFileStorage($storageDir, $encryptionKey);
        });

        // Bind credential manager
        $this->app->singleton(CredentialManager::class, function (Application $app) {
            return new CredentialManager($app->make(CredentialStorageInterface::class));
        });

        // Bind main SDK instance
        $this->app->singleton(ClubifyCheckoutSDK::class, function (Application $app) {
            $config = config('clubify-checkout');

            if (empty($config)) {
                throw new \RuntimeException('Clubify Checkout configuration not found. Please publish and configure the package.');
            }

            // Initialize SDK with secure credential manager
            $sdk = new ClubifyCheckoutSDK($config);

            // Replace default credential manager with our secure implementation
            $credentialManager = $app->make(CredentialManager::class);
            $sdk->setCredentialManager($credentialManager);

            // Auto-configure super admin credentials if available
            if (!empty($config['super_admin']['api_key']) || !empty($config['super_admin']['email'])) {
                try {
                    $credentialManager->addSuperAdminContext([
                        'api_key' => $config['super_admin']['api_key'] ?? null,
                        'email' => $config['super_admin']['email'] ?? $config['super_admin']['username'] ?? null, // Padronizado: email
                        'password' => $config['super_admin']['password'] ?? null,
                        'tenant_id' => $config['super_admin']['tenant_id'] ?? null,
                    ]);
                } catch (\Exception $e) {
                    // Log warning but don't fail app initialization
                    logger()->warning('Failed to configure super admin credentials', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $sdk;
        });

        // Register config
        $this->mergeConfigFrom(
            $this->getConfigPath(),
            'clubify-checkout'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path('clubify-checkout.php'),
            ], 'clubify-checkout-config');
        }

        // Health check for storage
        $this->app->booted(function () {
            if ($this->app->bound(CredentialStorageInterface::class)) {
                $storage = $this->app->make(CredentialStorageInterface::class);

                if (!$storage->isHealthy()) {
                    logger()->error('Clubify credential storage is not healthy');
                }
            }
        });
    }

    /**
     * Get configuration file path
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/../../config/clubify-checkout.php';
    }
}