<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Auth\AuthManager;
use Clubify\Checkout\Core\Auth\AuthManagerInterface;
use Clubify\Checkout\Core\Auth\TokenStorage;
use Clubify\Checkout\Core\Auth\TokenStorageInterface;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Events\EventDispatcher;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Laravel\Commands\InstallCommand;
use Clubify\Checkout\Laravel\Commands\PublishCommand;
use Clubify\Checkout\Laravel\Commands\SyncCommand;
use Clubify\Checkout\Laravel\Middleware\AuthenticateSDK;
use Clubify\Checkout\Laravel\Middleware\ValidateWebhook;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service Provider para Laravel Integration do Clubify Checkout SDK
 */
final class ClubifyCheckoutServiceProvider extends ServiceProvider
{
    /**
     * Indica se o loading é deferido
     */
    public bool $defer = false;

    /**
     * Lista de serviços fornecidos por este provider
     */
    public function provides(): array
    {
        return [
            ClubifyCheckoutSDK::class,
            ConfigurationInterface::class,
            AuthManagerInterface::class,
            TokenStorageInterface::class,
            EventDispatcherInterface::class,
            CacheManagerInterface::class,
            LoggerInterface::class,
            Client::class,
        ];
    }

    /**
     * Registra os serviços no container
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            realpath(__DIR__ . '/../../config/clubify-checkout.php') ?: __DIR__ . '/../../config/clubify-checkout.php',
            'clubify-checkout'
        );

        $this->registerCoreServices();
        $this->registerSDK();
        $this->registerCommands();
    }

    /**
     * Executa após todos os providers serem registrados
     */
    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootMiddleware();
        $this->bootEventListeners();
        $this->bootCommands();
    }

    /**
     * Registra os serviços core do SDK
     */
    private function registerCoreServices(): void
    {
        // Configuration
        $this->app->singleton(ConfigurationInterface::class, function (Container $app): Configuration {
            // Verificar se a configuração está disponível antes de tentar acessá-la
            $config = [];
            if ($app->bound('config')) {
                $config = $app['config']['clubify-checkout'] ?? [];
            }
            return new Configuration($config);
        });

        // Logger
        $this->app->singleton(LoggerInterface::class, function (Container $app): Logger {
            try {
                $config = $app[ConfigurationInterface::class];
                return new Logger($config->getLoggerConfig());
            } catch (\Throwable $e) {
                // Fallback se houver problema na configuração
                return new Logger([]);
            }
        });

        // Cache Manager
        $this->app->singleton(CacheManagerInterface::class, function (Container $app): CacheManager {
            try {
                $config = $app[ConfigurationInterface::class];
                return new CacheManager($config->getCacheConfig());
            } catch (\Throwable $e) {
                // Fallback se houver problema na configuração
                return new CacheManager([]);
            }
        });

        // Event Dispatcher
        $this->app->singleton(EventDispatcherInterface::class, function (Container $app): EventDispatcher {
            try {
                $logger = $app[LoggerInterface::class];
                return new EventDispatcher($logger);
            } catch (\Throwable $e) {
                // Fallback sem logger se necessário
                return new EventDispatcher();
            }
        });

        // Token Storage
        $this->app->singleton(TokenStorageInterface::class, function (Container $app): TokenStorage {
            try {
                $cache = $app[CacheManagerInterface::class];
                return new TokenStorage($cache);
            } catch (\Throwable $e) {
                // Fallback sem cache se necessário
                return new TokenStorage(new CacheManager([]));
            }
        });

        // Auth Manager
        $this->app->singleton(AuthManagerInterface::class, function (Container $app): AuthManager {
            try {
                return new AuthManager(
                    $app[ConfigurationInterface::class],
                    $app[TokenStorageInterface::class],
                    $app[LoggerInterface::class]
                );
            } catch (\Throwable $e) {
                // Fallback com configurações padrão
                return new AuthManager(
                    new Configuration([]),
                    new TokenStorage(new CacheManager([])),
                    new Logger([])
                );
            }
        });

        // HTTP Client
        $this->app->singleton(Client::class, function (Container $app): Client {
            try {
                return new Client(
                    $app[ConfigurationInterface::class],
                    $app[LoggerInterface::class]
                );
            } catch (\Throwable $e) {
                // Fallback com configurações padrão
                return new Client(
                    new Configuration([]),
                    new Logger([])
                );
            }
        });
    }

    /**
     * Registra o SDK principal
     */
    private function registerSDK(): void
    {
        $this->app->singleton(ClubifyCheckoutSDK::class, function (Container $app): ClubifyCheckoutSDK {
            $config = $app[ConfigurationInterface::class];

            $sdk = new ClubifyCheckoutSDK($config->toArray());

            // Não inicializa automaticamente para evitar problemas durante o boot
            // A inicialização deve ser feita lazy quando o SDK for usado

            return $sdk;
        });

        // Alias para facilitar uso
        $this->app->alias(ClubifyCheckoutSDK::class, 'clubify-checkout');
    }

    /**
     * Registra os comandos Artisan
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(InstallCommand::class, function (Container $app): InstallCommand {
                return new InstallCommand();
            });

            $this->app->singleton(PublishCommand::class, function (Container $app): PublishCommand {
                return new PublishCommand();
            });

            $this->app->singleton(SyncCommand::class, function (Container $app): SyncCommand {
                // Não injeta o SDK diretamente para evitar problemas de inicialização
                return new SyncCommand();
            });
        }
    }

    /**
     * Configura publicação de assets
     */
    private function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publica configuração
            $this->publishes([
                __DIR__ . '/../../config/clubify-checkout.php' => config_path('clubify-checkout.php'),
            ], 'clubify-checkout-config');

            // Publica translations
            $this->publishes([
                __DIR__ . '/../../resources/lang' => $this->app->langPath('vendor/clubify-checkout'),
            ], 'clubify-checkout-lang');

            // Publica stubs
            $this->publishes([
                __DIR__ . '/../../resources/stubs' => resource_path('stubs/vendor/clubify-checkout'),
            ], 'clubify-checkout-stubs');

            // Publica tudo
            $this->publishes([
                __DIR__ . '/../../config/clubify-checkout.php' => config_path('clubify-checkout.php'),
                __DIR__ . '/../../resources/lang' => $this->app->langPath('vendor/clubify-checkout'),
                __DIR__ . '/../../resources/stubs' => resource_path('stubs/vendor/clubify-checkout'),
            ], 'clubify-checkout');
        }
    }

    /**
     * Registra middleware
     */
    private function bootMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('clubify.auth', AuthenticateSDK::class);
        $router->aliasMiddleware('clubify.webhook', ValidateWebhook::class);

        // Não registra automaticamente no grupo web para evitar problemas
        // $router->pushMiddlewareToGroup('web', AuthenticateSDK::class);
    }

    /**
     * Registra event listeners
     */
    private function bootEventListeners(): void
    {
        // Registra listeners automáticos para eventos do SDK
        $events = $this->app['events'];

        // Listener para logs do SDK
        $events->listen('clubify.checkout.*', function (string $event, array $data): void {
            $this->app[LoggerInterface::class]->info("SDK Event: {$event}", $data);
        });

        // Listener para métricas
        $events->listen('clubify.checkout.metrics.*', function (string $event, array $data): void {
            // Aqui poderia integrar com Laravel Telescope ou outros sistemas de métricas
            $this->app[LoggerInterface::class]->debug("SDK Metrics: {$event}", $data);
        });
    }

    /**
     * Registra comandos Artisan
     */
    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishCommand::class,
                SyncCommand::class,
            ]);
        }
    }
}