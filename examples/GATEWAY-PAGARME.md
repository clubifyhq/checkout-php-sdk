# Guia de Configuração do Gateway Pagar.me

Este guia mostra como utilizar o SDK PHP do Clubify Checkout para configurar e usar o gateway de pagamento Pagar.me.

## 📋 Índice

- [Configuração Inicial](#configuração-inicial)
- [Métodos Disponíveis](#métodos-disponíveis)
- [Exemplos de Uso](#exemplos-de-uso)
- [Estrutura de Dados](#estrutura-de-dados)
- [Boas Práticas](#boas-práticas)
- [Troubleshooting](#troubleshooting)

---

## Configuração Inicial

### 1. Inicializar o SDK

```php
<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'tenant_id' => 'your-tenant-id',
    'organization_id' => 'your-organization-id',
    'environment' => 'sandbox', // ou 'production'
    'base_url' => 'https://checkout.clubify.me/api/v1',
]);

$sdk->initialize();
```

### 2. Obter o Módulo de Gateway Config

```php
$gatewayConfig = $sdk->payments()->gatewayConfig();
```

---

## Métodos Disponíveis

### GatewayConfigService

O serviço `GatewayConfigService` oferece os seguintes métodos:

| Método | Descrição | Retorno |
|--------|-----------|---------|
| `listAvailableGateways()` | Lista todos os gateways disponíveis | `array` |
| `configurePagarMe($credentials, $options)` | Configura gateway Pagar.me (método específico) | `array` |
| `configureGateway($gateway, $config)` | Configura qualquer gateway (método genérico) | `array` |
| `getGatewayConfig($provider)` | Obtém configuração pública do gateway | `array` |
| `getGatewayStatus($gateway)` | Verifica status do gateway | `array` |

---

## Exemplos de Uso

### Método 1: Configurar Pagar.me (Específico)

Usa o método helper `configurePagarMe()` que já possui valores padrão:

```php
$gatewayConfig = $sdk->payments()->gatewayConfig();

// Credenciais (ARN do AWS Secrets Manager)
$credentials = [
    'secretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:pagarme-credentials'
];

// Opções de configuração
$options = [
    'name' => 'Pagar.me Production',
    'environment' => 'production', // 'sandbox' ou 'production'
    'isActive' => true,
    'priority' => 1,
    'supportedMethods' => ['credit_card', 'pix', 'boleto'],
    'supportedCurrencies' => ['BRL'],
    'autoCapture' => true,
    'maxInstallments' => 12,
    'pixExpirationMinutes' => 30,
    'boletoExpirationDays' => 3,
];

$result = $gatewayConfig->configurePagarMe($credentials, $options);

// Resposta
echo "Config ID: " . $result['config']['id'];
echo "Provider: " . $result['config']['provider'];
echo "Status: " . $result['config']['isActive'];
```

### Método 2: Configurar Pagar.me (Genérico)

Usa o método genérico `configureGateway()` com controle total:

```php
$gatewayConfig = $sdk->payments()->gatewayConfig();

$config = [
    'provider' => 'pagarme',
    'name' => 'Pagar.me Sandbox',
    'environment' => 'sandbox',
    'isActive' => true,
    'priority' => 1,
    'credentialsSecretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:pagarme-sandbox',
    'supportedMethods' => ['credit_card', 'pix', 'boleto'],
    'supportedCurrencies' => ['BRL'],
    'configuration' => [
        'supportsTokenization' => true,
        'supportsRecurring' => true,
        'supportsRefunds' => true,
        'autoCapture' => true,
        'maxInstallments' => 12,
        'pixExpirationMinutes' => 30,
        'boletoExpirationDays' => 3,
        'minAmount' => 100,           // R$ 1,00
        'maxAmount' => 1000000,       // R$ 10.000,00
        'creditCardFee' => 3.99,      // 3.99%
        'pixFee' => 0.99,             // 0.99%
        'boletoFee' => 2.99,          // 2.99%
    ],
];

$result = $gatewayConfig->configureGateway('pagarme', $config);
```

### Listar Gateways Disponíveis

```php
$gateways = $gatewayConfig->listAvailableGateways();

foreach ($gateways['gateways'] as $gateway) {
    echo "Gateway disponível: $gateway\n";
}
```

### Obter Configuração do Gateway

```php
// Obter configuração específica do Pagar.me
$config = $gatewayConfig->getGatewayConfig('pagarme');

echo "Provider: " . $config['provider'] . "\n";
echo "Nome: " . $config['name'] . "\n";
echo "Ambiente: " . $config['environment'] . "\n";
echo "Ativo: " . ($config['isActive'] ? 'Sim' : 'Não') . "\n";
echo "Public Key: " . $config['publicKey'] . "\n";
echo "Métodos: " . implode(', ', $config['supportedMethods']) . "\n";

// Obter todos os gateways configurados
$allConfigs = $gatewayConfig->getGatewayConfig();
```

### Verificar Status do Gateway

```php
$status = $gatewayConfig->getGatewayStatus('pagarme');

echo "Status: " . $status['status'] . "\n";
echo "Última verificação: " . $status['lastChecked'] . "\n";
```

### Processar Pagamento com Cartão de Crédito

```php
$payment = $sdk->payments()->process([
    'amount' => 10000,              // R$ 100,00 em centavos
    'currency' => 'BRL',
    'payment_method' => 'credit_card',

    'card' => [
        'number' => '4111111111111111',
        'holder_name' => 'João Silva',
        'exp_month' => 12,
        'exp_year' => 2025,
        'cvv' => '123',
    ],

    'customer' => [
        'name' => 'João Silva',
        'email' => 'joao@example.com',
        'document' => '12345678900',
        'phone' => '11999999999',
    ],

    'billing_address' => [
        'street' => 'Rua Exemplo',
        'number' => '123',
        'neighborhood' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zip_code' => '01310-100',
    ],

    'installments' => 3,
    'capture' => true,

    'metadata' => [
        'order_id' => 'ORDER-123456',
    ],
]);

echo "Payment ID: " . $payment['id'] . "\n";
echo "Status: " . $payment['status'] . "\n";
```

### Processar Pagamento via PIX

```php
$pixPayment = $sdk->payments()->process([
    'amount' => 5000,               // R$ 50,00
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
]);

echo "QR Code: " . $pixPayment['pix']['qr_code'] . "\n";
echo "Código Copia e Cola: " . $pixPayment['pix']['qr_code_text'] . "\n";
echo "Expira em: " . $pixPayment['pix']['expires_at'] . "\n";
```

### Processar Pagamento via Boleto

```php
$boletoPayment = $sdk->payments()->process([
    'amount' => 15000,              // R$ 150,00
    'currency' => 'BRL',
    'payment_method' => 'boleto',

    'customer' => [
        'name' => 'Pedro Oliveira',
        'email' => 'pedro@example.com',
        'document' => '11122233344',
    ],

    'boleto' => [
        'expiration_days' => 3,
        'instructions' => 'Não aceitar após o vencimento',
    ],
]);

echo "URL do Boleto: " . $boletoPayment['boleto']['url'] . "\n";
echo "Código de barras: " . $boletoPayment['boleto']['barcode'] . "\n";
echo "Vencimento: " . $boletoPayment['boleto']['expiration_date'] . "\n";
```

---

## Estrutura de Dados

### Configuração do Gateway

```php
[
    'provider' => 'pagarme',                    // OBRIGATÓRIO
    'name' => 'string',                         // OBRIGATÓRIO
    'environment' => 'sandbox|production',      // OBRIGATÓRIO
    'isActive' => true,                         // OBRIGATÓRIO
    'priority' => 1,                            // OBRIGATÓRIO
    'credentialsSecretArn' => 'arn:...',       // OBRIGATÓRIO
    'supportedMethods' => ['credit_card', 'pix', 'boleto'],
    'supportedCurrencies' => ['BRL'],
    'configuration' => [
        'supportsTokenization' => true,
        'supportsRecurring' => true,
        'supportsRefunds' => true,
        'autoCapture' => true,
        'maxInstallments' => 12,
        'pixExpirationMinutes' => 30,
        'boletoExpirationDays' => 3,
        'minAmount' => 100,
        'maxAmount' => 1000000,
        'creditCardFee' => 3.99,
        'pixFee' => 0.99,
        'boletoFee' => 2.99,
    ]
]
```

### Resposta da Configuração

```php
[
    'message' => 'Gateway pagarme configured successfully',
    'gateway' => 'pagarme',
    'status' => 'configured',
    'config' => [
        'id' => '507f1f77bcf86cd799439011',
        'provider' => 'pagarme',
        'environment' => 'production',
        'isActive' => true,
    ]
]
```

### Estrutura do AWS Secrets Manager

O secret no AWS Secrets Manager deve ter o seguinte formato JSON:

```json
{
  "pagarme_api_key": "sk_live_your_api_key_here",
  "pagarme_secret_key": "your_secret_key_here",
  "pagarme_public_key": "pk_live_your_public_key_here"
}
```

**Campos do Secret:**
- `pagarme_api_key`: Chave privada da API (para requisições do backend)
- `pagarme_secret_key`: Secret key para validação de webhooks
- `pagarme_public_key`: Chave pública (será exposta para o frontend)

---

## Boas Práticas

### 1. Segurança de Credenciais

✅ **FAÇA:**
- Sempre use AWS Secrets Manager para armazenar credenciais
- Use diferentes secrets para sandbox e production
- Rotacione as chaves periodicamente
- Configure IAM roles apropriadas para acesso aos secrets

❌ **NÃO FAÇA:**
- Nunca armazene credenciais no código
- Nunca armazene credenciais em arquivos .env no repositório
- Nunca compartilhe credenciais de production
- Nunca faça commit de credenciais

### 2. Ambientes

**Sandbox (Desenvolvimento/Testes):**
```php
'environment' => 'sandbox',
'credentialsSecretArn' => 'arn:aws:secretsmanager:...:secret:sandbox/pagarme-credentials'
```

**Production (Produção):**
```php
'environment' => 'production',
'credentialsSecretArn' => 'arn:aws:secretsmanager:...:secret:production/pagarme-credentials'
```

### 3. Prioridade de Gateways

Use o campo `priority` para definir a ordem de preferência:

```php
// Gateway principal
['provider' => 'pagarme', 'priority' => 1]

// Gateway de fallback
['provider' => 'stripe', 'priority' => 2]
```

O sistema automaticamente usa o gateway com menor `priority` (maior preferência).

### 4. Tratamento de Erros

Sempre use try-catch ao configurar gateways:

```php
use Clubify\Checkout\Modules\Payments\Exceptions\GatewayException;

try {
    $result = $gatewayConfig->configurePagarMe($credentials, $options);
    echo "✅ Gateway configurado com sucesso!";
} catch (GatewayException $e) {
    // Erro específico de gateway
    echo "❌ Erro: " . $e->getMessage();

    // Log do erro
    error_log("Gateway config failed: " . $e->getMessage());

    // Notificar admin
    // mail('admin@example.com', 'Gateway Config Error', $e->getMessage());
} catch (\Exception $e) {
    // Erro genérico
    echo "❌ Erro inesperado: " . $e->getMessage();
}
```

### 5. Cache

O SDK cacheia configurações de gateway por 5 minutos:

```php
// Primeira chamada: busca da API
$config = $gatewayConfig->getGatewayConfig('pagarme');

// Chamadas seguintes (< 5min): retorna do cache
$config = $gatewayConfig->getGatewayConfig('pagarme'); // Cache hit
```

Para forçar atualização após mudanças:
- Aguarde 5 minutos, ou
- Limpe o cache manualmente (se implementado)

### 6. Validação de Dados

Antes de configurar, valide os dados:

```php
function validateGatewayConfig(array $config): void {
    $required = ['provider', 'name', 'environment', 'credentialsSecretArn'];

    foreach ($required as $field) {
        if (empty($config[$field])) {
            throw new \InvalidArgumentException("Campo obrigatório: $field");
        }
    }

    if (!in_array($config['environment'], ['sandbox', 'production'])) {
        throw new \InvalidArgumentException("Environment inválido");
    }

    if (!preg_match('/^arn:aws:secretsmanager:/', $config['credentialsSecretArn'])) {
        throw new \InvalidArgumentException("ARN inválido");
    }
}

// Uso
try {
    validateGatewayConfig($config);
    $result = $gatewayConfig->configureGateway('pagarme', $config);
} catch (\InvalidArgumentException $e) {
    echo "Erro de validação: " . $e->getMessage();
}
```

---

## Troubleshooting

### Erro: "Authentication failed"

**Causa:** API key ou secret inválidos

**Solução:**
```php
// Verificar credenciais
var_dump(getenv('CLUBIFY_CHECKOUT_API_KEY'));
var_dump(getenv('CLUBIFY_CHECKOUT_API_SECRET'));
var_dump(getenv('CLUBIFY_CHECKOUT_TENANT_ID'));
```

### Erro: "Invalid credentialsSecretArn format"

**Causa:** ARN do AWS Secrets Manager inválido

**Solução:**
```php
// Formato correto do ARN
'credentialsSecretArn' => 'arn:aws:secretsmanager:us-east-1:123456789:secret:name-abc123'

// Verificar formato
if (!preg_match('/^arn:aws:secretsmanager:/', $arn)) {
    echo "ARN inválido!";
}
```

### Erro: "Gateway não configurado"

**Causa:** Tentando usar gateway antes de configurá-lo

**Solução:**
```php
// Primeiro: configurar
$gatewayConfig->configurePagarMe($credentials, $options);

// Depois: usar
$payment = $sdk->payments()->process($paymentData);
```

### Erro: "Failed to get public key from secrets"

**Causa:** Secret não existe ou não tem as chaves corretas

**Solução:**
1. Verificar se o secret existe no AWS Secrets Manager
2. Verificar se o secret tem o campo `pagarme_public_key`
3. Verificar IAM permissions para acessar o secret

```bash
# AWS CLI - verificar secret
aws secretsmanager get-secret-value --secret-id arn:aws:secretsmanager:...
```

### Erro: "Validation error"

**Causa:** Dados de configuração inválidos

**Solução:**
```php
// Verificar campos obrigatórios
$required = ['provider', 'name', 'environment', 'credentialsSecretArn'];
foreach ($required as $field) {
    if (empty($config[$field])) {
        echo "Campo obrigatório ausente: $field";
    }
}

// Verificar valores válidos
$validEnvironments = ['sandbox', 'production'];
if (!in_array($config['environment'], $validEnvironments)) {
    echo "Environment deve ser: " . implode(' ou ', $validEnvironments);
}
```

### Cache não atualiza

**Causa:** Configuração está em cache

**Solução:**
```php
// Aguardar 5 minutos ou implementar método de limpeza de cache
// O cache expira automaticamente após 5 minutos (300 segundos)

// Alternativa: usar timestamp para debug
echo "Cache TTL: 300 segundos (5 minutos)\n";
echo "Última atualização: " . date('Y-m-d H:i:s') . "\n";
```

### Debug Mode

Para ver logs detalhados:

```php
$sdk = new ClubifyCheckoutSDK([
    // ... outras configs
    'logger' => [
        'enabled' => true,
        'level' => 'debug', // debug, info, warning, error
    ],
]);
```

---

## Referências

### Documentação
- **SDK PHP**: `sdk/checkout/php/README.md`
- **GatewayConfigService**: `sdk/checkout/php/src/Modules/Payments/Services/GatewayConfigService.php`
- **Exemplo completo**: `sdk/checkout/php/examples/configure-pagarme-gateway.php`

### API Externa
- **Pagar.me Docs**: https://docs.pagar.me
- **AWS Secrets Manager**: https://docs.aws.amazon.com/secretsmanager/

### Métodos de Pagamento Suportados

| Método | Código | Descrição |
|--------|--------|-----------|
| Cartão de Crédito | `credit_card` | Pagamento com cartão, suporta parcelamento |
| PIX | `pix` | Pagamento instantâneo via PIX |
| Boleto | `boleto` | Boleto bancário com vencimento |

### Códigos de Status de Pagamento

| Status | Descrição |
|--------|-----------|
| `pending` | Pagamento pendente |
| `processing` | Pagamento em processamento |
| `paid` | Pagamento aprovado |
| `failed` | Pagamento falhou |
| `canceled` | Pagamento cancelado |
| `refunded` | Pagamento estornado |

---

**Exemplo completo:** Veja `configure-pagarme-gateway.php` para um exemplo executável.

**Suporte:** Para dúvidas ou problemas, consulte a documentação ou entre em contato com o suporte.
