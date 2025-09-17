<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Facades;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Checkout\CheckoutModule;
use Clubify\Checkout\Modules\Customers\CustomersModule;
use Clubify\Checkout\Modules\Organization\OrganizationModule;
use Clubify\Checkout\Modules\Payments\PaymentsModule;
use Clubify\Checkout\Modules\Products\ProductsModule;
use Clubify\Checkout\Modules\Webhooks\WebhooksModule;
use Illuminate\Support\Facades\Facade;

/**
 * Facade para acesso simplificado ao Clubify Checkout SDK
 *
 * @method static ClubifyCheckoutSDK make(array $config = [])
 * @method static bool initialize()
 * @method static bool isInitialized()
 * @method static array getStats()
 * @method static ClubifyCheckoutSDK setDebugMode(bool $debug = true)
 * @method static OrganizationModule organization()
 * @method static ProductsModule products()
 * @method static CheckoutModule checkout()
 * @method static PaymentsModule payments()
 * @method static CustomersModule customers()
 * @method static WebhooksModule webhooks()
 * @method static array healthCheck()
 * @method static string getVersion()
 * @method static array getConfiguration()
 * @method static void clearCache()
 * @method static void reset()
 *
 * @see ClubifyCheckoutSDK
 */
final class ClubifyCheckout extends Facade
{
    /**
     * Nome do binding no container
     */
    protected static function getFacadeAccessor(): string
    {
        return ClubifyCheckoutSDK::class;
    }

    /**
     * Cria uma nova instância do SDK com configuração específica
     */
    public static function make(array $config = []): ClubifyCheckoutSDK
    {
        if (empty($config)) {
            return static::getFacadeRoot();
        }

        // Cria nova instância com configuração específica
        $sdk = new ClubifyCheckoutSDK($config);
        $sdk->initialize();

        return $sdk;
    }

    /**
     * Métodos de conveniência para operações comuns
     */

    /**
     * Setup rápido de organização
     */
    public static function setupOrganization(array $organizationData): array
    {
        return static::organization()->setupOrganization($organizationData);
    }

    /**
     * Criação rápida de produto
     */
    public static function createProduct(array $productData): array
    {
        return static::products()->product()->create($productData);
    }

    /**
     * Processamento rápido de pagamento
     */
    public static function processPayment(array $paymentData): array
    {
        return static::payments()->payment()->processPayment($paymentData);
    }

    /**
     * Criação rápida de sessão de checkout
     */
    public static function createSession(array $sessionData): array
    {
        return static::checkout()->session()->create($sessionData);
    }

    /**
     * Busca/criação rápida de cliente
     */
    public static function findOrCreateCustomer(array $customerData): array
    {
        return static::customers()->findOrCreateCustomer($customerData);
    }

    /**
     * Configuração rápida de webhook
     */
    public static function setupWebhook(array $webhookData): array
    {
        return static::webhooks()->webhook()->create($webhookData);
    }

    /**
     * Métodos para Laravel específico
     */

    /**
     * Integração com Laravel Cache
     */
    public static function cached(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $cache = app('cache');

        return $cache->remember("clubify.checkout.{$key}", $ttl, $callback);
    }

    /**
     * Dispatch Laravel Job para operação assíncrona
     */
    public static function queue(string $method, array $params = []): void
    {
        dispatch(function () use ($method, $params) {
            static::$method(...$params);
        });
    }

    /**
     * Integração com Laravel Events
     */
    public static function event(string $event, array $data = []): void
    {
        event("clubify.checkout.{$event}", $data);
    }

    /**
     * Integração com Laravel Log
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        app('log')->{$level}("[Clubify Checkout] {$message}", $context);
    }

    /**
     * Validação usando Laravel Validator
     */
    public static function validate(array $data, array $rules): array
    {
        return validator($data, $rules)->validate();
    }

    /**
     * Helpers para desenvolvimento
     */

    /**
     * Simula operação em ambiente de desenvolvimento
     */
    public static function fake(): ClubifyCheckoutSDK
    {
        if (!app()->environment('testing', 'local')) {
            throw new \RuntimeException('Fake mode only available in testing/local environment');
        }

        $config = config('clubify-checkout');
        $config['environment'] = 'sandbox';
        $config['features']['fake_mode'] = true;

        return static::make($config);
    }

    /**
     * Debug helper
     */
    public static function debug(): array
    {
        return [
            'sdk_version' => static::getVersion(),
            'configuration' => static::getConfiguration(),
            'stats' => static::getStats(),
            'health' => static::healthCheck(),
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
        ];
    }
}