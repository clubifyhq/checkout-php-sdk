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

            $initResult = $sdk->initialize();

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // em ms

            if (function_exists('logger') && app()->bound('config')) {
                logger()->info('Clubify SDK inicializado para testes', [
                    'duration_ms' => $duration,
                    'success' => $initResult['success'] ?? false,
                    'helper_class' => self::class
                ]);
            }

            return $initResult['success'] ?? false;

        } catch (Exception $e) {
            if (function_exists('logger') && app()->bound('config')) {
                logger()->warning('Falha ao inicializar SDK para testes (esperado em desenvolvimento)', [
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                    'helper_class' => self::class
                ]);
            }

            // Em desenvolvimento, falha de inicialização é esperada
            // Retornamos false mas não consideramos erro crítico
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
        return [
            'credentials' => [
                'tenant_id' => env('CLUBIFY_CHECKOUT_TENANT_ID', 'demo_tenant'),
                'api_key' => env('CLUBIFY_CHECKOUT_API_KEY', 'demo_api_key_123'),
                'api_secret' => env('CLUBIFY_CHECKOUT_API_SECRET', 'demo_secret_456')
            ],
            'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'development'),
            'api' => [
                'base_url' => env('CLUBIFY_CHECKOUT_API_URL', 'https://checkout.svelve.com/api/v1'),
                'timeout' => env('CLUBIFY_CHECKOUT_TIMEOUT', 5), // Timeout reduzido para desenvolvimento
                'retries' => env('CLUBIFY_CHECKOUT_RETRIES', 1), // Menos tentativas
                'verify_ssl' => env('CLUBIFY_CHECKOUT_VERIFY_SSL', false)
            ],
            'cache' => [
                'enabled' => env('CLUBIFY_CHECKOUT_CACHE_ENABLED', true),
                'ttl' => env('CLUBIFY_CHECKOUT_CACHE_TTL', 3600)
            ],
            'logging' => [
                'enabled' => env('CLUBIFY_CHECKOUT_LOG_REQUESTS', true),
                'level' => env('CLUBIFY_CHECKOUT_LOG_LEVEL', 'info')
            ]
        ];
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
            'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'NOT_SET'),
            'base_url' => env('CLUBIFY_CHECKOUT_API_URL', 'NOT_SET'),
        ];
    }

    /**
     * Resetar instância (útil para testes)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$configurationLoaded = false;
    }

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