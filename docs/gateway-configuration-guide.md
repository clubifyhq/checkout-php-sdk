# Guia de Configuração de Gateway de Pagamento

Este guia demonstra como configurar gateways de pagamento para seu tenant usando o SDK PHP do Clubify Checkout.

## Índice

1. [Visão Geral](#visão-geral)
2. [Instalação](#instalação)
3. [Configuração Inicial](#configuração-inicial)
4. [Gateways Suportados](#gateways-suportados)
5. [Exemplos de Uso](#exemplos-de-uso)
6. [Configuração Avançada](#configuração-avançada)
7. [Gerenciamento de Credenciais](#gerenciamento-de-credenciais)
8. [Troubleshooting](#troubleshooting)

## Visão Geral

O `GatewayConfigService` permite configurar e gerenciar gateways de pagamento para seu tenant através da API do Payment Service.

### Funcionalidades

- ✅ Listar gateways disponíveis
- ✅ Configurar múltiplos gateways
- ✅ Métodos auxiliares para gateways populares (Stripe, Pagar.me, Mercado Pago)
- ✅ Obter configurações públicas (sem credenciais sensíveis)
- ✅ Verificar status e saúde dos gateways
- ✅ Cache automático de configurações
- ✅ Logging detalhado

## Instalação

```bash
composer require clubify/checkout-sdk
```

## Configuração Inicial

```php
<?php

use Clubify\Checkout\Modules\Payments\Services\GatewayConfigService;
use Clubify\Checkout\Core\HttpClient;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// 1. Configurar logger
$logger = new Logger('gateway-config');

// 2. Configurar cache
$cache = new FilesystemAdapter('gateway', 300);

// 3. Configurar HTTP client
$httpClient = new HttpClient(
    'https://checkout.clubify.me',
    [
        'Authorization' => 'Bearer YOUR_API_KEY',
    ],
    $logger,
    $cache
);

// 4. Criar instância do serviço
$gatewayService = new GatewayConfigService(
    $logger,
    $cache,
    $httpClient,
    'https://checkout.clubify.me',
    'YOUR_TENANT_ID',
    'YOUR_ORGANIZATION_ID'
);
```

## Gateways Suportados

| Gateway | Provider | Métodos de Pagamento | Moedas |
|---------|----------|---------------------|--------|
| Stripe | `stripe` | Credit Card | BRL, USD |
| Pagar.me | `pagarme` | Credit Card, PIX, Boleto | BRL |
| Mercado Pago | `mercado_pago` | Credit Card, PIX | BRL |
| Cielo | `cielo` | Credit Card, Debit Card | BRL |
| Rede | `rede` | Credit Card, Debit Card | BRL |
| PayPal | `paypal` | Credit Card, PayPal | USD |

## Exemplos de Uso

### 1. Listar Gateways Disponíveis

```php
$gateways = $gatewayService->listAvailableGateways();

echo "Gateways disponíveis:\n";
print_r($gateways);
```

**Resposta:**
```json
{
  "gateways": ["stripe", "pagarme", "cielo", "rede", "paypal", "mercado_pago"],
  "status": "active"
}
```

### 2. Configurar Gateway Stripe

```php
$config = $gatewayService->configureStripe(
    // Credenciais (ARN do AWS Secrets Manager)
    [
        'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:stripe-prod',
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

print_r($config);
```

**Resposta:**
```json
{
  "message": "Gateway stripe configured successfully",
  "gateway": "stripe",
  "status": "configured",
  "config": {
    "id": "67f8a9b0c1d2e3f4g5h6i7j8",
    "provider": "stripe",
    "environment": "production",
    "isActive": true
  }
}
```

### 3. Configurar Gateway Pagar.me

```php
$config = $gatewayService->configurePagarMe(
    [
        'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:pagarme-prod',
    ],
    [
        'name' => 'Pagar.me Production',
        'environment' => 'production',
        'isActive' => true,
        'priority' => 2,
        'maxInstallments' => 12,
        'pixExpirationMinutes' => 30,
        'boletoExpirationDays' => 3,
        'supportedMethods' => ['credit_card', 'pix', 'boleto'],
    ]
);
```

### 4. Configurar Gateway Mercado Pago

```php
$config = $gatewayService->configureMercadoPago(
    [
        'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:mercadopago-sandbox',
    ],
    [
        'name' => 'Mercado Pago Sandbox',
        'environment' => 'sandbox',
        'isActive' => false,
        'priority' => 3,
        'maxInstallments' => 12,
    ]
);
```

### 5. Configuração Manual (Qualquer Gateway)

```php
$config = $gatewayService->configureGateway('cielo', [
    'provider' => 'cielo',
    'name' => 'Cielo Gateway',
    'environment' => 'production',
    'isActive' => true,
    'priority' => 4,
    'credentialsSecretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:cielo-prod',
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
```

### 6. Obter Configuração do Gateway

```php
// Obter configuração de um gateway específico
$stripeConfig = $gatewayService->getGatewayConfig('stripe');

// Obter configuração de todos os gateways
$allGateways = $gatewayService->getGatewayConfig();
```

**Nota:** As configurações retornadas são públicas e **não contêm credenciais sensíveis**.

### 7. Verificar Status do Gateway

```php
$status = $gatewayService->getGatewayStatus('stripe');

print_r($status);
```

**Resposta:**
```json
{
  "gateway": "stripe",
  "status": "active",
  "lastChecked": "2025-10-09T19:30:00.000Z"
}
```

## Configuração Avançada

### Estrutura Completa de Configuração

```php
[
    // Informações básicas
    'provider' => 'stripe',              // OBRIGATÓRIO
    'name' => 'Gateway Name',            // OBRIGATÓRIO
    'environment' => 'production',       // OBRIGATÓRIO: 'sandbox' ou 'production'
    'isActive' => true,                  // Ativa/desativa o gateway
    'priority' => 1,                     // Prioridade (menor = maior prioridade)

    // Credenciais (AWS Secrets Manager)
    'credentialsSecretArn' => 'arn:...', // OBRIGATÓRIO
    'webhookSecret' => 'whsec_...',      // Opcional

    // Métodos e moedas suportados
    'supportedMethods' => ['credit_card', 'pix', 'boleto'],
    'supportedCurrencies' => ['BRL', 'USD'],

    // Configuração específica
    'configuration' => [
        // Recursos
        'supportsTokenization' => true,
        'supportsRecurring' => true,
        'supportsRefunds' => true,
        'supportsFraudAnalysis' => false,

        // Processamento
        'autoCapture' => true,
        'captureDelay' => 0,              // minutos

        // Limites
        'maxInstallments' => 12,
        'minAmount' => 100,               // centavos
        'maxAmount' => 100000000,         // centavos

        // Taxas
        'creditCardFee' => 2.5,           // porcentagem
        'pixFee' => 0.99,                 // porcentagem
        'boletoFee' => 3.49,              // valor fixo em BRL

        // Específico para PIX
        'pixExpirationMinutes' => 30,

        // Específico para Boleto
        'boletoExpirationDays' => 3,
        'boletoInstructions' => 'Pagável em qualquer banco',
    ],

    // Regras de roteamento (opcional)
    'routingRules' => [
        [
            'condition' => [
                'amount' => ['min' => 100, 'max' => 10000],
                'currency' => 'BRL',
                'paymentMethod' => 'credit_card',
            ],
            'priority' => 1,
            'weight' => 10,
            'failoverGateways' => ['pagarme'],
        ],
    ],
]
```

### Priorização de Gateways

Configure múltiplos gateways com diferentes prioridades:

```php
// Gateway principal (prioridade 1)
$gatewayService->configureStripe($credentials, [
    'priority' => 1,
    'isActive' => true,
]);

// Gateway secundário (prioridade 2)
$gatewayService->configurePagarMe($credentials, [
    'priority' => 2,
    'isActive' => true,
]);

// Gateway de backup (prioridade 3)
$gatewayService->configureMercadoPago($credentials, [
    'priority' => 3,
    'isActive' => true,
]);
```

## Gerenciamento de Credenciais

### ⚠️ Segurança de Credenciais

**IMPORTANTE:** As credenciais dos gateways **devem** ser armazenadas no AWS Secrets Manager. **Nunca** armazene credenciais diretamente no código ou banco de dados.

### Criar Secret no AWS Secrets Manager

```bash
# Criar secret para Stripe
aws secretsmanager create-secret \
    --name stripe-production-credentials \
    --description "Stripe Production API Keys" \
    --secret-string '{
        "api_key": "sk_live_...",
        "webhook_secret": "whsec_..."
    }'

# Criar secret para Pagar.me
aws secretsmanager create-secret \
    --name pagarme-production-credentials \
    --description "Pagar.me Production API Keys" \
    --secret-string '{
        "api_key": "ak_live_...",
        "encryption_key": "ek_live_..."
    }'
```

### Usar ARN do Secret na Configuração

```php
$config = $gatewayService->configureStripe([
    'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:stripe-production-credentials',
], $options);
```

## Verificação de Saúde

```php
// Verificar se o serviço está saudável
$isHealthy = $gatewayService->isHealthy();

// Obter status completo do serviço
$status = $gatewayService->getStatus();

// Obter métricas
$metrics = $gatewayService->getMetrics();

echo "Serviço saudável: " . ($isHealthy ? 'Sim' : 'Não') . "\n";
echo "Status: \n";
print_r($status);
```

## Troubleshooting

### Erro: "Campo obrigatório ausente: credentialsSecretArn"

**Causa:** O ARN do AWS Secrets Manager não foi fornecido.

**Solução:**
```php
$config = $gatewayService->configureStripe([
    'secretArn' => 'arn:aws:secretsmanager:...',  // Obrigatório
], $options);
```

### Erro: "Provider inválido"

**Causa:** O provider especificado não é suportado.

**Solução:** Use um dos providers suportados:
- `stripe`
- `pagarme`
- `cielo`
- `rede`
- `paypal`
- `mercado_pago`

### Erro: "Environment inválido"

**Causa:** O environment deve ser `sandbox` ou `production`.

**Solução:**
```php
[
    'environment' => 'production', // ou 'sandbox'
]
```

### Cache não está funcionando

**Solução:** Verifique se o cache está configurado corretamente:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter(
    'gateway',           // namespace
    300,                 // TTL em segundos
    __DIR__ . '/cache'   // diretório de cache
);
```

### Limpar cache manualmente

```php
// Limpar cache de um gateway específico
$cache->deleteItem("gateway_config:{$tenantId}:stripe");

// Limpar todo o cache de gateway
$cache->clear();
```

## Exemplos Completos

Veja o arquivo de exemplo completo em:
```
sdk/checkout/php/examples/gateway-configuration-example.php
```

Para executar:
```bash
php examples/gateway-configuration-example.php
```

## Referências

- [Payment Service API](../../../apps/payment-service/README.md)
- [AWS Secrets Manager](https://docs.aws.amazon.com/secretsmanager/)
- [SDK Core Documentation](./README.md)

## Suporte

Para problemas ou dúvidas, abra uma issue no GitHub ou entre em contato com o suporte técnico.
