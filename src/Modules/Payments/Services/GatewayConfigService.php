<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Services;

use Clubify\Checkout\Core\BaseService;
use Clubify\Checkout\Core\Http\Client as HttpClient;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Payments\Exceptions\GatewayException;
use Psr\Log\LoggerInterface;

/**
 * Gateway Configuration Service
 *
 * Serviço para gerenciar configurações de gateway de pagamento via API.
 * Permite configurar gateways para tenants específicos através do payment-service.
 *
 * Funcionalidades:
 * - Listar gateways disponíveis
 * - Configurar gateway para tenant
 * - Obter configuração de gateway
 * - Verificar status do gateway
 * - Gerenciar credenciais de gateway
 */
class GatewayConfigService extends BaseService implements ServiceInterface
{
    private HttpClient $httpClient;
    private string $baseUrl;
    private ?ConfigurationInterface $config;

    public function __construct(
        LoggerInterface $logger,
        CacheManagerInterface $cache,
        HttpClient $httpClient,
        string $baseUrl,
        string $tenantId,  // Deprecated: kept for backward compatibility
        string $organizationId,  // Deprecated: kept for backward compatibility
        ?ConfigurationInterface $config = null
    ) {
        parent::__construct($logger, $cache);
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->config = $config;
    }

    /**
     * Lista todos os gateways disponíveis
     *
     * @return array Lista de gateways disponíveis
     * @throws GatewayException
     */
    public function listAvailableGateways(): array
    {
        try {
            $response = $this->httpClient->get(
                "{$this->baseUrl}/payments/gateway/list",
                [
                    'headers' => $this->getHeaders(),
                ]
            );

            // Decodificar resposta HTTP para array
            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new GatewayException('Falha ao decodificar resposta do servidor');
            }

            $this->logger->info('Gateways listados com sucesso', [
                'total' => count($data['gateways'] ?? []),
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao listar gateways', [
                'error' => $e->getMessage(),
            ]);

            throw new GatewayException(
                "Falha ao listar gateways: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Configura gateway de pagamento para o tenant
     *
     * @param string $gateway Nome do gateway (stripe, pagarme, etc)
     * @param array $config Configuração do gateway
     * @return array Configuração salva
     * @throws GatewayException
     */
    public function configureGateway(string $gateway, array $config): array
    {
        try {
            // Valida dados mínimos
            $this->validateGatewayConfig($config);

            $response = $this->httpClient->post(
                "{$this->baseUrl}/payments/gateway/configure/{$gateway}",
                [
                    'headers' => $this->getHeaders(),
                    'json' => $config,
                ]
            );

            // Decodificar resposta HTTP para array
            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new GatewayException('Falha ao decodificar resposta do servidor');
            }

            // Limpa cache de configuração
            $this->clearCachePattern("gateway_config:{$this->tenantId}:*");

            $this->logger->info('Gateway configurado com sucesso', [
                'gateway' => $gateway,
                'tenant_id' => $this->tenantId,
                'config_id' => $data['config']['id'] ?? null,
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao configurar gateway', [
                'gateway' => $gateway,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw new GatewayException(
                "Falha ao configurar gateway {$gateway}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Obtém configuração pública do gateway (sem credenciais sensíveis)
     *
     * @param string|null $provider Provider específico ou todos se null
     * @return array Configuração do gateway
     * @throws GatewayException
     */
    public function getGatewayConfig(?string $provider = null): array
    {
        try {
            $cacheKey = "gateway_config:{$this->tenantId}:" . ($provider ?? 'all');
            $cached = $this->getFromCache($cacheKey);
            //if ($cached) {
            //    return $cached;
            //}

            $url = "{$this->baseUrl}/payments/gateway/config";
            if ($provider) {
                $url .= "/{$provider}";
            }


            $response = $this->httpClient->get(
                $url,
                [
                    'headers' => $this->getHeaders(),
                ]
            );

            // Decodificar resposta HTTP para array
            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new GatewayException('Falha ao decodificar resposta do servidor');
            }

            // Cache por 5 minutos
            $this->setCache($cacheKey, $data, 300);

            $this->logger->info('Configuração do gateway obtida', [
                'tenant_id' => $this->tenantId,
                'provider' => $provider ?? 'all',
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao obter configuração do gateway', [
                'tenant_id' => $this->tenantId,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw new GatewayException(
                "Falha ao obter configuração do gateway: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Verifica status de um gateway específico
     *
     * @param string $gateway Nome do gateway
     * @return array Status do gateway
     * @throws GatewayException
     */
    public function getGatewayStatus(string $gateway): array
    {
        try {
            $response = $this->httpClient->get(
                "{$this->baseUrl}/payments/gateway/status/{$gateway}",
                [
                    'headers' => $this->getHeaders(),
                ]
            );

            // Decodificar resposta HTTP para array
            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new GatewayException('Falha ao decodificar resposta do servidor');
            }

            $this->logger->info('Status do gateway obtido', [
                'gateway' => $gateway,
                'status' => $data['status'] ?? 'unknown',
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao obter status do gateway', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            throw new GatewayException(
                "Falha ao obter status do gateway {$gateway}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Configura gateway Stripe
     *
     * @param array $credentials Credenciais do Stripe
     * @param array $options Opções adicionais
     * @return array Configuração salva
     */
    public function configureStripe(array $credentials, array $options = []): array
    {
        $config = array_merge([
            'provider' => 'stripe',
            'name' => $options['name'] ?? 'Stripe Gateway',
            'environment' => $options['environment'] ?? 'sandbox',
            'isActive' => $options['isActive'] ?? true,
            'priority' => $options['priority'] ?? 1,
            'credentialsSecretArn' => $credentials['secretArn'] ?? '',
            'supportedMethods' => $options['supportedMethods'] ?? ['credit_card'],
            'supportedCurrencies' => $options['supportedCurrencies'] ?? ['BRL', 'USD'],
            'configuration' => [
                'supportsTokenization' => true,
                'supportsRecurring' => true,
                'supportsRefunds' => true,
                'autoCapture' => $options['autoCapture'] ?? true,
            ],
        ], $options);

        return $this->configureGateway('stripe', $config);
    }

    /**
     * Configura gateway Pagar.me
     *
     * @param array $credentials Credenciais do Pagar.me
     * @param array $options Opções adicionais
     * @return array Configuração salva
     */
    public function configurePagarMe(array $credentials, array $options = []): array
    {
        $config = array_merge([
            'provider' => 'pagarme',
            'name' => $options['name'] ?? 'Pagar.me Gateway',
            'environment' => $options['environment'] ?? 'sandbox',
            'isActive' => $options['isActive'] ?? true,
            'priority' => $options['priority'] ?? 1,
            'credentialsSecretArn' => $credentials['secretArn'] ?? '',
            'supportedMethods' => $options['supportedMethods'] ?? ['credit_card', 'pix', 'boleto'],
            'supportedCurrencies' => $options['supportedCurrencies'] ?? ['BRL'],
            'configuration' => [
                "supportsFraudAnalysis" => false,
                'supportsTokenization' => $options['supportsTokenization'] ?? true,
                'supportsRecurring' => $options['supportsRecurring'] ?? true,
                'supportsRefunds' => $options['supportsRefunds'] ?? true,
                'defaultInstallments' => $options['defaultInstallments'] ?? 12,
                'showInterestBreakdown' => $options['showInterestBreakdown'] ?? true,
                'autoCapture' => $options['autoCapture'] ?? true,
                'maxInstallments' => $options['maxInstallments'] ?? 12,
                'pixExpirationMinutes' => $options['pixExpirationMinutes'] ?? 30,
                'boletoExpirationDays' => $options['boletoExpirationDays'] ?? 3,
                "minAmount" => $options['minAmount'] ?? 5,
                "maxAmount" => $options['maxAmount'] ?? 50000000,
                "creditCardFee" => $options['creditCardFee'] ?? 2.99,
                "pixFee" => $options['pixFee'] ?? 0,
                "boletoFee" => $options['boletoFee'] ?? 0,
                "captureDelay" => $options['captureDelay'] ?? 0,
                "boletoInstructions" => $options['boletoInstructions'] ?? "Pagamento referente à compra. Não aceitar após o vencimento.",
            ],
        ], $options);

        return $this->configureGateway('pagarme', $config);
    }

    /**
     * Configura gateway Mercado Pago
     *
     * @param array $credentials Credenciais do Mercado Pago
     * @param array $options Opções adicionais
     * @return array Configuração salva
     */
    public function configureMercadoPago(array $credentials, array $options = []): array
    {
        $config = array_merge([
            'provider' => 'mercado_pago',
            'name' => $options['name'] ?? 'Mercado Pago Gateway',
            'environment' => $options['environment'] ?? 'sandbox',
            'isActive' => $options['isActive'] ?? true,
            'priority' => $options['priority'] ?? 1,
            'credentialsSecretArn' => $credentials['secretArn'] ?? '',
            'supportedMethods' => $options['supportedMethods'] ?? ['credit_card', 'pix'],
            'supportedCurrencies' => $options['supportedCurrencies'] ?? ['BRL'],
            'configuration' => [
                'supportsTokenization' => true,
                'supportsRecurring' => false,
                'supportsRefunds' => true,
                'autoCapture' => $options['autoCapture'] ?? true,
                'maxInstallments' => $options['maxInstallments'] ?? 12,
            ],
        ], $options);

        return $this->configureGateway('mercado_pago', $config);
    }

    /**
     * Obtém headers HTTP para as requisições
     *
     * FIXED: Now dynamically retrieves tenant_id and organization_id from Config
     * to ensure they reflect any changes made via setTenantContext()
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Dynamically retrieve tenant_id and organization_id from Config
        // This ensures values are always up-to-date even after setTenantContext()
        if ($this->config) {
            $tenantId = $this->config->getTenantId();
            if ($tenantId) {
                $headers['X-Tenant-Id'] = $tenantId;
            }

            $organizationId = $this->config->getOrganizationId();
            if ($organizationId) {
                $headers['X-Organization-Id'] = $organizationId;
            }
        }

        return $headers;
    }

    /**
     * Valida configuração do gateway
     */
    private function validateGatewayConfig(array $config): void
    {
        $required = ['provider', 'environment', 'credentialsSecretArn'];

        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new GatewayException("Campo obrigatório ausente: {$field}");
            }
        }

        // Valida provider
        $validProviders = ['stripe', 'pagarme', 'cielo', 'rede', 'paypal', 'mercado_pago'];
        if (!in_array($config['provider'], $validProviders)) {
            throw new GatewayException("Provider inválido: {$config['provider']}");
        }

        // Valida environment
        $validEnvironments = ['sandbox', 'production'];
        if (!in_array($config['environment'], $validEnvironments)) {
            throw new GatewayException("Environment inválido: {$config['environment']}");
        }
    }

    // ===============================================
    // ServiceInterface Implementation
    // ===============================================

    public function getName(): string
    {
        return 'gateway-config';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function isHealthy(): bool
    {
        try {
            $this->listAvailableGateways();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'tenant_id' => $this->config ? $this->config->getTenantId() : null,
            'organization_id' => $this->config ? $this->config->getOrganizationId() : null,
            'base_url' => $this->baseUrl,
            'timestamp' => time(),
        ];
    }

    public function getConfig(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'tenant_id' => $this->config ? $this->config->getTenantId() : null,
            'organization_id' => $this->config ? $this->config->getOrganizationId() : null,
        ];
    }

    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    public function getStatus(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available' => $this->isAvailable(),
            'metrics' => $this->getMetrics(),
            'config' => $this->getConfig(),
            'timestamp' => time(),
        ];
    }
}
