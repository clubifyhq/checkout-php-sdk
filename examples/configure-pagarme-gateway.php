<?php

/**
 * Exemplo: Configuração de Gateway Pagar.me
 *
 * Este exemplo demonstra como usar o SDK PHP do Clubify Checkout
 * para configurar o gateway de pagamento Pagar.me.
 *
 * Pré-requisitos:
 * - SDK instalado via composer
 * - Credenciais de API (API Key, Secret, Tenant ID)
 * - ARN do AWS Secrets Manager com credenciais do Pagar.me
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Payments\Exceptions\GatewayException;

// ============================================
// 1. CONFIGURAÇÃO INICIAL DO SDK
// ============================================

$config = [
    'api_key' => 'your-api-key-here',
    'api_secret' => 'your-api-secret-here',
    'tenant_id' => 'your-tenant-id-here',
    'organization_id' => 'your-organization-id-here',
    'environment' => 'sandbox', // ou 'production'
    'base_url' => 'https://checkout.clubify.me/api/v1',

    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'retry' => [
            'enabled' => true,
            'attempts' => 3,
            'delay' => 1000,
        ],
    ],

    'logger' => [
        'enabled' => true,
        'level' => 'info',
    ],
];

try {
    // Inicializar SDK
    $sdk = new ClubifyCheckoutSDK($config);
    $result = $sdk->initialize();

    if (!$result['success']) {
        throw new Exception("Falha ao inicializar SDK: " . $result['message']);
    }

    echo "✅ SDK inicializado com sucesso!\n";
    echo "🆔 Tenant: {$result['tenant_id']}\n";
    echo "🌍 Ambiente: {$result['environment']}\n\n";

} catch (Exception $e) {
    die("❌ Erro na inicialização: " . $e->getMessage() . "\n");
}

// ============================================
// 2. LISTAR GATEWAYS DISPONÍVEIS
// ============================================

echo "📋 Listando gateways disponíveis...\n";

try {
    $gatewayConfig = $sdk->payments()->gatewayConfig();
    $availableGateways = $gatewayConfig->listAvailableGateways();

    echo "Gateways disponíveis:\n";
    foreach ($availableGateways['gateways'] as $gateway) {
        echo "  - {$gateway}\n";
    }
    echo "\n";

} catch (GatewayException $e) {
    echo "⚠️ Aviso: Não foi possível listar gateways - " . $e->getMessage() . "\n\n";
}

// ============================================
// 3. CONFIGURAR GATEWAY PAGAR.ME - MÉTODO 1
// Usando o método específico configurePagarMe()
// ============================================

echo "🔧 Configurando Pagar.me - Método 1 (método específico)...\n";

try {
    $gatewayConfig = $sdk->payments()->gatewayConfig();

    // Credenciais (ARN do AWS Secrets Manager)
    $credentials = [
        'secretArn' => 'arn:aws:secretsmanager:us-east-1:471112609943:secret:production/clubify-checkout/payment-7QzLSG'
    ];

    // Opções de configuração
    $options = [
        'name' => 'Pagar.me Production - Minha Empresa',
        'environment' => 'production', // ou 'sandbox'
        'isActive' => true,
        'priority' => 1, // Prioridade (menor = maior prioridade)

        // Métodos de pagamento suportados
        'supportedMethods' => ['credit_card', 'pix', 'boleto'],

        // Moedas suportadas
        'supportedCurrencies' => ['BRL'],

        // Configurações específicas do Pagar.me
        'autoCapture' => true,
        'maxInstallments' => 12,
        'pixExpirationMinutes' => 30,
        'boletoExpirationDays' => 3,
    ];

    $result = $gatewayConfig->configurePagarMe($credentials, $options);

    echo "✅ Gateway Pagar.me configurado com sucesso!\n";
    echo "📋 Config ID: {$result['config']['id']}\n";
    echo "🏷️  Provider: {$result['config']['provider']}\n";
    echo "🌍 Ambiente: {$result['config']['environment']}\n";
    echo "✓ Ativo: " . ($result['config']['isActive'] ? 'Sim' : 'Não') . "\n\n";

} catch (GatewayException $e) {
    echo "❌ Erro ao configurar Pagar.me: " . $e->getMessage() . "\n\n";
}

// ============================================
// 4. CONFIGURAR GATEWAY PAGAR.ME - MÉTODO 2
// Usando o método genérico configureGateway()
// ============================================

echo "🔧 Configurando Pagar.me - Método 2 (método genérico)...\n";

try {
    $gatewayConfig = $sdk->payments()->gatewayConfig();

    // Configuração completa
    $config = [
        'provider' => 'pagarme',
        'name' => 'Pagar.me Sandbox - Testes',
        'environment' => 'sandbox',
        'isActive' => true,
        'priority' => 2,

        // ARN do AWS Secrets Manager
        'credentialsSecretArn' => 'arn:aws:secretsmanager:us-east-1:471112609943:secret:sandbox/clubify-checkout/pagarme-abc123',

        // Métodos de pagamento
        'supportedMethods' => ['credit_card', 'pix', 'boleto'],

        // Moedas
        'supportedCurrencies' => ['BRL'],

        // Configuração avançada
        'configuration' => [
            'supportsTokenization' => true,
            'supportsRecurring' => true,
            'supportsRefunds' => true,
            'autoCapture' => true,
            'maxInstallments' => 12,
            'pixExpirationMinutes' => 30,
            'boletoExpirationDays' => 3,

            // Configurações opcionais
            'minAmount' => 100, // R$ 1,00 em centavos
            'maxAmount' => 1000000, // R$ 10.000,00 em centavos
            'creditCardFee' => 3.99, // Taxa em percentual
            'pixFee' => 0.99,
            'boletoFee' => 2.99,
        ],
    ];

    $result = $gatewayConfig->configureGateway('pagarme', $config);

    echo "✅ Gateway Pagar.me configurado com sucesso (método 2)!\n";
    echo "📋 Config ID: {$result['config']['id']}\n\n";

} catch (GatewayException $e) {
    echo "❌ Erro ao configurar Pagar.me: " . $e->getMessage() . "\n\n";
}

// ============================================
// 5. OBTER CONFIGURAÇÃO DO GATEWAY
// ============================================

echo "📖 Obtendo configuração do gateway Pagar.me...\n";

try {
    $gatewayConfig = $sdk->payments()->gatewayConfig();
    $config = $gatewayConfig->getGatewayConfig('pagarme');

    echo "Configuração do Pagar.me:\n";
    echo "  Provider: {$config['provider']}\n";
    echo "  Nome: {$config['name']}\n";
    echo "  Ambiente: {$config['environment']}\n";
    echo "  Ativo: " . ($config['isActive'] ? 'Sim' : 'Não') . "\n";
    echo "  Prioridade: {$config['priority']}\n";

    if (isset($config['publicKey'])) {
        echo "  Public Key: {$config['publicKey']}\n";
    }

    echo "  Métodos suportados: " . implode(', ', $config['supportedMethods']) . "\n";
    echo "  Moedas suportadas: " . implode(', ', $config['supportedCurrencies']) . "\n";
    echo "  Parcelamento: até {$config['configuration']['maxInstallments']}x\n";
    echo "\n";

} catch (GatewayException $e) {
    echo "⚠️ Erro ao obter configuração: " . $e->getMessage() . "\n\n";
}

// ============================================
// 6. VERIFICAR STATUS DO GATEWAY
// ============================================

echo "🔍 Verificando status do gateway Pagar.me...\n";

try {
    $gatewayConfig = $sdk->payments()->gatewayConfig();
    $status = $gatewayConfig->getGatewayStatus('pagarme');

    echo "Status do Pagar.me:\n";
    echo "  Status: {$status['status']}\n";
    echo "  Última verificação: {$status['lastChecked']}\n";
    echo "\n";

} catch (GatewayException $e) {
    echo "⚠️ Erro ao verificar status: " . $e->getMessage() . "\n\n";
}

// ============================================
// 7. EXEMPLO DE PROCESSAMENTO DE PAGAMENTO
// ============================================

echo "💳 Exemplo de processamento de pagamento com Pagar.me...\n";

try {
    // Criar sessão de pagamento
    $paymentData = [
        'amount' => 10000, // R$ 100,00 em centavos
        'currency' => 'BRL',
        'payment_method' => 'credit_card',

        // Dados do cartão
        'card' => [
            'number' => '4111111111111111',
            'holder_name' => 'João Silva',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvv' => '123',
        ],

        // Dados do cliente
        'customer' => [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678900',
            'phone' => '11999999999',
        ],

        // Endereço de cobrança
        'billing_address' => [
            'street' => 'Rua Exemplo',
            'number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01310-100',
        ],

        // Configurações adicionais
        'installments' => 3,
        'capture' => true,
        'metadata' => [
            'order_id' => 'ORDER-123456',
            'product' => 'Curso Premium',
        ],
    ];

    $payment = $sdk->payments()->process($paymentData);

    echo "✅ Pagamento processado com sucesso!\n";
    echo "📋 Payment ID: {$payment['id']}\n";
    echo "💰 Valor: R$ " . number_format($payment['amount'] / 100, 2, ',', '.') . "\n";
    echo "📊 Status: {$payment['status']}\n";
    echo "\n";

} catch (Exception $e) {
    echo "⚠️ Exemplo de pagamento (pode falhar se não houver gateway configurado): " . $e->getMessage() . "\n\n";
}

// ============================================
// 8. EXEMPLO DE PAGAMENTO VIA PIX
// ============================================

echo "📱 Exemplo de pagamento via PIX...\n";

try {
    $pixPayment = [
        'amount' => 5000, // R$ 50,00
        'currency' => 'BRL',
        'payment_method' => 'pix',

        'customer' => [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'document' => '98765432100',
        ],

        'pix' => [
            'expiration_minutes' => 30,
        ],

        'metadata' => [
            'order_id' => 'ORDER-789',
        ],
    ];

    $pixResult = $sdk->payments()->process($pixPayment);

    echo "✅ PIX gerado com sucesso!\n";
    echo "📋 Payment ID: {$pixResult['id']}\n";
    echo "🔗 QR Code: {$pixResult['pix']['qr_code']}\n";
    echo "📝 Código Copia e Cola: {$pixResult['pix']['qr_code_text']}\n";
    echo "⏰ Expira em: {$pixResult['pix']['expires_at']}\n";
    echo "\n";

} catch (Exception $e) {
    echo "⚠️ Exemplo de PIX: " . $e->getMessage() . "\n\n";
}

// ============================================
// 9. DICAS E BOAS PRÁTICAS
// ============================================

echo "💡 DICAS E BOAS PRÁTICAS:\n\n";

echo "1. ARMAZENAMENTO SEGURO DE CREDENCIAIS:\n";
echo "   - SEMPRE use AWS Secrets Manager para armazenar credenciais\n";
echo "   - NUNCA armazene chaves privadas no código ou banco de dados\n";
echo "   - Use diferentes secrets para sandbox e production\n\n";

echo "2. CONFIGURAÇÃO DO AWS SECRETS MANAGER:\n";
echo "   O secret no AWS deve ter o seguinte formato JSON:\n";
echo "   {\n";
echo "     \"pagarme_api_key\": \"sk_live_...\",\n";
echo "     \"pagarme_secret_key\": \"...\",\n";
echo "     \"pagarme_public_key\": \"pk_live_...\"\n";
echo "   }\n\n";

echo "3. AMBIENTES:\n";
echo "   - 'sandbox': Para testes e desenvolvimento\n";
echo "   - 'production': Para operação em produção\n\n";

echo "4. PRIORIDADE DE GATEWAYS:\n";
echo "   - Use priority=1 para gateway principal\n";
echo "   - Use priority=2+ para gateways de fallback\n";
echo "   - O sistema usa o gateway com menor priority\n\n";

echo "5. MÉTODOS DE PAGAMENTO:\n";
echo "   Pagar.me suporta:\n";
echo "   - credit_card: Cartão de crédito\n";
echo "   - pix: Pagamento instantâneo\n";
echo "   - boleto: Boleto bancário\n\n";

echo "6. TRATAMENTO DE ERROS:\n";
echo "   - Sempre use try-catch ao configurar gateways\n";
echo "   - GatewayException fornece mensagens detalhadas\n";
echo "   - Verifique logs para troubleshooting\n\n";

echo "7. CACHE:\n";
echo "   - Configurações de gateway são cacheadas por 5 minutos\n";
echo "   - Após atualizar, aguarde a expiração do cache\n";
echo "   - Ou limpe o cache manualmente se necessário\n\n";

echo "📚 REFERÊNCIAS:\n";
echo "   - Documentação SDK: sdk/checkout/php/README.md\n";
echo "   - GatewayConfigService: sdk/checkout/php/src/Modules/Payments/Services/GatewayConfigService.php\n";
echo "   - API Pagar.me: https://docs.pagar.me\n\n";

echo "✅ Script de exemplo concluído!\n";
