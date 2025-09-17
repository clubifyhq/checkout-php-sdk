<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments\Services;

use ClubifyCheckout\Core\BaseService;
use ClubifyCheckout\Modules\Payments\Contracts\GatewayInterface;
use ClubifyCheckout\Modules\Payments\Gateways\PagarMeGateway;
use ClubifyCheckout\Modules\Payments\Gateways\StripeGateway;
use ClubifyCheckout\Modules\Payments\Exceptions\GatewayException;
use ClubifyCheckout\Modules\Payments\Exceptions\ConfigurationException;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use InvalidArgumentException;

/**
 * Serviço de gerenciamento de gateways
 *
 * Implementa Factory Pattern para criação e gestão
 * de instâncias de gateways de pagamento, fornecendo
 * uma interface unificada para operações multi-gateway.
 *
 * Funcionalidades principais:
 * - Factory de gateways
 * - Configuração dinâmica
 * - Gestão de estado e saúde
 * - Balanceamento de carga
 * - Failover automático
 * - Monitoramento de performance
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gestão de gateways
 * - O: Open/Closed - Extensível via novos gateways
 * - L: Liskov Substitution - Gateways intercambiáveis
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class GatewayService extends BaseService
{
    private array $gatewayInstances = [];
    private array $gatewayConfigs = [];
    private array $gatewayFactories = [];
    private array $healthStatus = [];
    private array $performanceMetrics = [];

    private array $loadBalancingConfig = [
        'strategy' => 'round_robin', // round_robin, weighted, performance_based
        'weights' => [],
        'failover_enabled' => true,
        'health_check_interval' => 300, // 5 minutos
    ];

    public function __construct(
        LoggerInterface $logger,
        CacheItemPoolInterface $cache
    ) {
        parent::__construct($logger, $cache);
        $this->initializeGatewayFactories();
    }

    /**
     * Registra configuração de gateway
     */
    public function registerGatewayConfig(string $name, array $config): void
    {
        $this->validateGatewayConfig($config);

        $this->gatewayConfigs[$name] = array_merge([
            'enabled' => true,
            'priority' => 100,
            'weight' => 1,
            'max_concurrent_requests' => 100,
            'timeout' => 30,
            'retry_attempts' => 3,
            'health_check_enabled' => true,
        ], $config);

        $this->logger->info('Configuração de gateway registrada', [
            'gateway' => $name,
            'enabled' => $config['enabled'] ?? true,
        ]);
    }

    /**
     * Obtém instância de gateway
     */
    public function getGateway(string $name): GatewayInterface
    {
        // Verifica se gateway está configurado
        if (!isset($this->gatewayConfigs[$name])) {
            throw new GatewayException("Gateway não configurado: {$name}");
        }

        // Verifica se gateway está habilitado
        if (!$this->gatewayConfigs[$name]['enabled']) {
            throw new GatewayException("Gateway desabilitado: {$name}");
        }

        // Retorna instância em cache ou cria nova
        if (!isset($this->gatewayInstances[$name])) {
            $this->gatewayInstances[$name] = $this->createGatewayInstance($name);
        }

        return $this->gatewayInstances[$name];
    }

    /**
     * Obtém gateway recomendado baseado em critérios
     */
    public function getRecommendedGateway(array $criteria = []): GatewayInterface
    {
        $availableGateways = $this->getAvailableGateways($criteria);

        if (empty($availableGateways)) {
            throw new GatewayException("Nenhum gateway disponível para os critérios especificados");
        }

        // Aplica estratégia de balanceamento de carga
        $selectedGateway = $this->selectGatewayByStrategy($availableGateways, $criteria);

        return $this->getGateway($selectedGateway);
    }

    /**
     * Lista gateways disponíveis
     */
    public function getAvailableGateways(array $criteria = []): array
    {
        $available = [];

        foreach ($this->gatewayConfigs as $name => $config) {
            if (!$config['enabled']) {
                continue;
            }

            // Verifica saúde do gateway
            if (!$this->isGatewayHealthy($name)) {
                continue;
            }

            // Verifica critérios específicos
            if (!$this->gatewayMeetsCriteria($name, $criteria)) {
                continue;
            }

            $available[] = $name;
        }

        return $available;
    }

    /**
     * Verifica saúde de um gateway
     */
    public function checkGatewayHealth(string $name): array
    {
        $cacheKey = "gateway_health:{$name}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $gateway = $this->getGateway($name);
            $healthResult = $gateway->testConnection();

            $healthData = [
                'gateway' => $name,
                'healthy' => true,
                'response_time' => $healthResult['response_time'] ?? null,
                'last_check' => date('Y-m-d H:i:s'),
                'details' => $healthResult,
            ];

            // Atualiza status de saúde
            $this->healthStatus[$name] = $healthData;

            // Cache por 5 minutos
            $this->setCache($cacheKey, $healthData, 300);

            return $healthData;

        } catch (\Throwable $e) {
            $healthData = [
                'gateway' => $name,
                'healthy' => false,
                'error' => $e->getMessage(),
                'last_check' => date('Y-m-d H:i:s'),
            ];

            $this->healthStatus[$name] = $healthData;
            $this->setCache($cacheKey, $healthData, 60); // Cache erro por 1 minuto

            $this->logger->warning('Gateway não saudável', [
                'gateway' => $name,
                'error' => $e->getMessage(),
            ]);

            return $healthData;
        }
    }

    /**
     * Verifica saúde de todos os gateways
     */
    public function checkAllGatewaysHealth(): array
    {
        $results = [];

        foreach (array_keys($this->gatewayConfigs) as $name) {
            $results[$name] = $this->checkGatewayHealth($name);
        }

        return $results;
    }

    /**
     * Obtém métricas de performance de um gateway
     */
    public function getGatewayMetrics(string $name): array
    {
        $cacheKey = "gateway_metrics:{$name}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $gateway = $this->getGateway($name);
            $metrics = $gateway->getPerformanceMetrics();

            $metricsData = [
                'gateway' => $name,
                'success_rate' => $metrics['success_rate'] ?? 0,
                'average_response_time' => $metrics['average_response_time'] ?? 0,
                'total_requests' => $metrics['total_requests'] ?? 0,
                'failed_requests' => $metrics['failed_requests'] ?? 0,
                'last_updated' => date('Y-m-d H:i:s'),
            ];

            $this->performanceMetrics[$name] = $metricsData;
            $this->setCache($cacheKey, $metricsData, 600); // 10 minutos

            return $metricsData;

        } catch (\Throwable $e) {
            $this->logger->error('Falha ao obter métricas do gateway', [
                'gateway' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'gateway' => $name,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtém status consolidado de todos os gateways
     */
    public function getGatewaysStatus(): array
    {
        $status = [
            'total_gateways' => count($this->gatewayConfigs),
            'enabled_gateways' => 0,
            'healthy_gateways' => 0,
            'gateways' => [],
        ];

        foreach ($this->gatewayConfigs as $name => $config) {
            if ($config['enabled']) {
                $status['enabled_gateways']++;
            }

            $health = $this->checkGatewayHealth($name);
            $metrics = $this->getGatewayMetrics($name);

            if ($health['healthy']) {
                $status['healthy_gateways']++;
            }

            $status['gateways'][$name] = [
                'enabled' => $config['enabled'],
                'healthy' => $health['healthy'],
                'priority' => $config['priority'],
                'weight' => $config['weight'],
                'health_details' => $health,
                'metrics' => $metrics,
            ];
        }

        return $status;
    }

    /**
     * Habilita gateway
     */
    public function enableGateway(string $name): void
    {
        if (!isset($this->gatewayConfigs[$name])) {
            throw new GatewayException("Gateway não encontrado: {$name}");
        }

        $this->gatewayConfigs[$name]['enabled'] = true;

        // Limpa cache relacionado
        $this->clearCachePattern("gateway_*:{$name}");

        $this->logger->info('Gateway habilitado', ['gateway' => $name]);
    }

    /**
     * Desabilita gateway
     */
    public function disableGateway(string $name): void
    {
        if (!isset($this->gatewayConfigs[$name])) {
            throw new GatewayException("Gateway não encontrado: {$name}");
        }

        $this->gatewayConfigs[$name]['enabled'] = false;

        // Remove instância em cache
        unset($this->gatewayInstances[$name]);

        // Limpa cache relacionado
        $this->clearCachePattern("gateway_*:{$name}");

        $this->logger->info('Gateway desabilitado', ['gateway' => $name]);
    }

    /**
     * Atualiza configuração de gateway
     */
    public function updateGatewayConfig(string $name, array $config): void
    {
        if (!isset($this->gatewayConfigs[$name])) {
            throw new GatewayException("Gateway não encontrado: {$name}");
        }

        $this->validateGatewayConfig($config);

        $this->gatewayConfigs[$name] = array_merge($this->gatewayConfigs[$name], $config);

        // Remove instância em cache para recriar com nova configuração
        unset($this->gatewayInstances[$name]);

        // Limpa cache relacionado
        $this->clearCachePattern("gateway_*:{$name}");

        $this->logger->info('Configuração de gateway atualizada', [
            'gateway' => $name,
            'updated_fields' => array_keys($config),
        ]);
    }

    /**
     * Remove gateway
     */
    public function removeGateway(string $name): void
    {
        if (!isset($this->gatewayConfigs[$name])) {
            throw new GatewayException("Gateway não encontrado: {$name}");
        }

        // Remove configuração e instância
        unset($this->gatewayConfigs[$name]);
        unset($this->gatewayInstances[$name]);
        unset($this->healthStatus[$name]);
        unset($this->performanceMetrics[$name]);

        // Limpa cache relacionado
        $this->clearCachePattern("gateway_*:{$name}");

        $this->logger->info('Gateway removido', ['gateway' => $name]);
    }

    /**
     * Configura estratégia de balanceamento de carga
     */
    public function setLoadBalancingStrategy(string $strategy, array $options = []): void
    {
        $validStrategies = ['round_robin', 'weighted', 'performance_based', 'random'];

        if (!in_array($strategy, $validStrategies)) {
            throw new InvalidArgumentException("Estratégia inválida: {$strategy}");
        }

        $this->loadBalancingConfig['strategy'] = $strategy;
        $this->loadBalancingConfig = array_merge($this->loadBalancingConfig, $options);

        $this->logger->info('Estratégia de balanceamento configurada', [
            'strategy' => $strategy,
            'options' => $options,
        ]);
    }

    /**
     * Inicializa factories de gateways
     */
    private function initializeGatewayFactories(): void
    {
        $this->gatewayFactories = [
            'pagarme' => function (array $config): PagarMeGateway {
                return new PagarMeGateway($config);
            },
            'stripe' => function (array $config): StripeGateway {
                return new StripeGateway($config);
            },
        ];
    }

    /**
     * Cria instância de gateway
     */
    private function createGatewayInstance(string $name): GatewayInterface
    {
        $config = $this->gatewayConfigs[$name];

        if (!isset($this->gatewayFactories[$name])) {
            throw new GatewayException("Factory não encontrada para gateway: {$name}");
        }

        try {
            $factory = $this->gatewayFactories[$name];
            $gateway = $factory($config);

            $this->logger->info('Instância de gateway criada', [
                'gateway' => $name,
                'class' => get_class($gateway),
            ]);

            return $gateway;

        } catch (\Throwable $e) {
            throw new GatewayException(
                "Falha ao criar instância do gateway {$name}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Verifica se gateway está saudável
     */
    private function isGatewayHealthy(string $name): bool
    {
        // Verifica cache de saúde
        if (isset($this->healthStatus[$name])) {
            $lastCheck = strtotime($this->healthStatus[$name]['last_check']);
            $interval = $this->loadBalancingConfig['health_check_interval'];

            // Se check recente, usa resultado em cache
            if ((time() - $lastCheck) < $interval) {
                return $this->healthStatus[$name]['healthy'];
            }
        }

        // Executa check de saúde
        $health = $this->checkGatewayHealth($name);
        return $health['healthy'];
    }

    /**
     * Verifica se gateway atende aos critérios
     */
    private function gatewayMeetsCriteria(string $name, array $criteria): bool
    {
        if (empty($criteria)) {
            return true;
        }

        try {
            $gateway = $this->getGateway($name);

            // Verifica métodos de pagamento suportados
            if (isset($criteria['payment_method'])) {
                if (!$gateway->supportsMethod($criteria['payment_method'])) {
                    return false;
                }
            }

            // Verifica moedas suportadas
            if (isset($criteria['currency'])) {
                if (!$gateway->supportsCurrency($criteria['currency'])) {
                    return false;
                }
            }

            // Verifica limites de valor
            if (isset($criteria['amount'])) {
                $currency = $criteria['currency'] ?? 'BRL';
                if (!$gateway->isAmountValid($criteria['amount'], $currency)) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            $this->logger->warning('Falha ao verificar critérios do gateway', [
                'gateway' => $name,
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Seleciona gateway baseado na estratégia configurada
     */
    private function selectGatewayByStrategy(array $availableGateways, array $criteria): string
    {
        $strategy = $this->loadBalancingConfig['strategy'];

        switch ($strategy) {
            case 'round_robin':
                return $this->selectByRoundRobin($availableGateways);

            case 'weighted':
                return $this->selectByWeight($availableGateways);

            case 'performance_based':
                return $this->selectByPerformance($availableGateways);

            case 'random':
                return $availableGateways[array_rand($availableGateways)];

            default:
                return $availableGateways[0]; // Fallback para primeiro disponível
        }
    }

    /**
     * Seleção round-robin
     */
    private function selectByRoundRobin(array $gateways): string
    {
        static $lastUsed = [];

        $gatewaysKey = implode(',', $gateways);
        $index = ($lastUsed[$gatewaysKey] ?? -1) + 1;

        if ($index >= count($gateways)) {
            $index = 0;
        }

        $lastUsed[$gatewaysKey] = $index;

        return $gateways[$index];
    }

    /**
     * Seleção por peso
     */
    private function selectByWeight(array $gateways): string
    {
        $weights = [];
        $totalWeight = 0;

        foreach ($gateways as $gateway) {
            $weight = $this->gatewayConfigs[$gateway]['weight'] ?? 1;
            $weights[$gateway] = $weight;
            $totalWeight += $weight;
        }

        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($weights as $gateway => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $gateway;
            }
        }

        return $gateways[0]; // Fallback
    }

    /**
     * Seleção baseada em performance
     */
    private function selectByPerformance(array $gateways): string
    {
        $bestGateway = null;
        $bestScore = -1;

        foreach ($gateways as $gateway) {
            $metrics = $this->getGatewayMetrics($gateway);

            // Calcula score baseado em taxa de sucesso e tempo de resposta
            $successRate = $metrics['success_rate'] ?? 0;
            $responseTime = $metrics['average_response_time'] ?? 1000; // ms

            // Score: peso maior para taxa de sucesso, menor para tempo de resposta
            $score = ($successRate * 100) - ($responseTime / 10);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestGateway = $gateway;
            }
        }

        return $bestGateway ?? $gateways[0];
    }

    /**
     * Valida configuração de gateway
     */
    private function validateGatewayConfig(array $config): void
    {
        $required = ['api_key'];

        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new ConfigurationException("Campo obrigatório ausente na configuração: {$field}");
            }
        }

        // Validações adicionais podem ser adicionadas aqui
    }
}