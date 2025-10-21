<?php

declare(strict_types=1);

/**
 * Gateway Configuration Example
 *
 * Exemplo de uso do GatewayConfigService para configurar gateways de pagamento
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\Modules\Payments\Services\GatewayConfigService;
use Clubify\Checkout\Core\Http\Client as HttpClient;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Core\Config\Configuration;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;




// Load .env file from parent directory if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    echo "✓ Loaded environment variables from .env\n\n";
}

// Configurações (em produção, use variáveis de ambiente)
$organizationId = getenv('CLUBIFY_CHECKOUT_ORGANIZATION_ID');
$organizationApiKey = getenv('CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY');
$tenantId = getenv('CLUBIFY_TENANT_ID'); // Tenant específico para as operações
$environment = getenv('CLUBIFY_CHECKOUT_ENVIRONMENT');

// ============================================
// CONFIGURAÇÃO
// ============================================

$config = [
    'payment_service_url' => 'https://checkout.clubify.me',
    'tenant_id' => getenv('CLUBIFY_TENANT_ID'),
    'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
    'api_key' => getenv('CLUBIFY_API_KEY'),
];

// ============================================
// INICIALIZAÇÃO
// ============================================

// Logger
$logger = new Logger('gateway-config');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Configuration
$configuration = new Configuration([
    'cache.compression.enabled' => false,
    'cache.encryption.enabled' => false,
]);

// Cache
$cache = new CacheManager($configuration);

// HTTP Client
$httpClient = new HttpClient(
    $config['payment_service_url'],
    [
        'Authorization' => "Bearer {$config['api_key']}",
    ],
    $logger,
    $cache
);

// Gateway Config Service
$gatewayService = new GatewayConfigService(
    $logger,
    $cache,
    $httpClient,
    $config['payment_service_url'],
    $config['tenant_id'],
    $config['organization_id']
);

// ============================================
// EXEMPLO 1: Listar Gateways Disponíveis
// ============================================

echo "=== EXEMPLO 1: Listar Gateways Disponíveis ===\n";

try {
    $gateways = $gatewayService->listAvailableGateways();

    echo "Gateways disponíveis:\n";
    print_r($gateways);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 2: Configurar Gateway Stripe
// ============================================

echo "\n=== EXEMPLO 2: Configurar Gateway Stripe ===\n";

try {
    $stripeConfig = $gatewayService->configureStripe(
        // Credenciais (devem estar no AWS Secrets Manager)
        [
            'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:stripe-credentials',
        ],
        // Opções
        [
            'name' => 'Stripe Production',
            'environment' => 'production',
            'isActive' => true,
            'priority' => 1,
            'autoCapture' => true,
            'supportedMethods' => ['credit_card'],
            'supportedCurrencies' => ['BRL', 'USD'],
        ]
    );

    echo "Stripe configurado com sucesso:\n";
    print_r($stripeConfig);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 3: Configurar Gateway Pagar.me
// ============================================

echo "\n=== EXEMPLO 3: Configurar Gateway Pagar.me ===\n";

try {
    $pagarmeConfig = $gatewayService->configurePagarMe(
        // Credenciais (devem estar no AWS Secrets Manager)
        [
            'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:pagarme-credentials',
        ],
        // Opções
        [
            'name' => 'Pagar.me Production',
            'environment' => 'production',
            'isActive' => true,
            'priority' => 2,
            'autoCapture' => true,
            'maxInstallments' => 12,
            'pixExpirationMinutes' => 30,
            'boletoExpirationDays' => 3,
            'supportedMethods' => ['credit_card', 'pix', 'boleto'],
            'supportedCurrencies' => ['BRL'],
        ]
    );

    echo "Pagar.me configurado com sucesso:\n";
    print_r($pagarmeConfig);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 4: Configurar Gateway Mercado Pago
// ============================================

echo "\n=== EXEMPLO 4: Configurar Gateway Mercado Pago ===\n";

try {
    $mercadoPagoConfig = $gatewayService->configureMercadoPago(
        // Credenciais (devem estar no AWS Secrets Manager)
        [
            'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:mercadopago-credentials',
        ],
        // Opções
        [
            'name' => 'Mercado Pago Sandbox',
            'environment' => 'sandbox',
            'isActive' => false, // Desativado inicialmente para testes
            'priority' => 3,
            'autoCapture' => true,
            'maxInstallments' => 12,
            'supportedMethods' => ['credit_card', 'pix'],
            'supportedCurrencies' => ['BRL'],
        ]
    );

    echo "Mercado Pago configurado com sucesso:\n";
    print_r($mercadoPagoConfig);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 5: Configuração Manual (Gateway Genérico)
// ============================================

echo "\n=== EXEMPLO 5: Configuração Manual ===\n";

try {
    $customConfig = $gatewayService->configureGateway('cielo', [
        'provider' => 'cielo',
        'name' => 'Cielo Gateway',
        'environment' => 'production',
        'isActive' => true,
        'priority' => 4,
        'credentialsSecretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:cielo-credentials',
        'supportedMethods' => ['credit_card', 'debit_card'],
        'supportedCurrencies' => ['BRL'],
        'configuration' => [
            'supportsTokenization' => true,
            'supportsRecurring' => true,
            'supportsRefunds' => true,
            'autoCapture' => true,
            'maxInstallments' => 12,
            'creditCardFee' => 2.5, // 2.5%
        ],
    ]);

    echo "Cielo configurado com sucesso:\n";
    print_r($customConfig);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 6: Obter Configuração do Gateway
// ============================================

echo "\n=== EXEMPLO 6: Obter Configuração do Gateway ===\n";

try {
    // Obter configuração de um gateway específico
    $stripeInfo = $gatewayService->getGatewayConfig('stripe');

    echo "Configuração do Stripe:\n";
    print_r($stripeInfo);
    echo "\n";

    // Obter configuração de todos os gateways
    $allGateways = $gatewayService->getGatewayConfig();

    echo "Todos os gateways configurados:\n";
    print_r($allGateways);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 7: Verificar Status do Gateway
// ============================================

echo "\n=== EXEMPLO 7: Verificar Status do Gateway ===\n";

try {
    $status = $gatewayService->getGatewayStatus('stripe');

    echo "Status do Stripe:\n";
    print_r($status);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================
// EXEMPLO 8: Verificar Saúde do Serviço
// ============================================

echo "\n=== EXEMPLO 8: Verificar Saúde do Serviço ===\n";

try {
    $isHealthy = $gatewayService->isHealthy();
    $status = $gatewayService->getStatus();

    echo "Serviço saudável: " . ($isHealthy ? 'Sim' : 'Não') . "\n";
    echo "Status completo:\n";
    print_r($status);
    echo "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DOS EXEMPLOS ===\n";
