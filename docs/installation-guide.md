# Guia de Instalação e Configuração - Clubify Checkout SDK PHP

Este guia fornece instruções completas para instalar, configurar e começar a usar o Clubify Checkout SDK em projetos PHP e Laravel.

## 📋 Índice

- [Requisitos do Sistema](#requisitos-do-sistema)
- [Instalação](#instalação)
  - [Via Composer](#via-composer)
  - [Instalação Manual](#instalação-manual)
- [Configuração Básica](#configuração-básica)
- [Configuração Laravel](#configuração-laravel)
- [Configuração PHP Vanilla](#configuração-php-vanilla)
- [Configuração Avançada](#configuração-avançada)
- [Verificação da Instalação](#verificação-da-instalação)
- [Próximos Passos](#próximos-passos)
- [Troubleshooting](#troubleshooting)

## Requisitos do Sistema

### Requisitos Mínimos

- **PHP**: 8.2+ (recomendado 8.3+)
- **Extensões PHP**:
  - `curl` - Para requisições HTTP
  - `json` - Para processamento JSON
  - `mbstring` - Para manipulação de strings
  - `openssl` - Para criptografia e HTTPS
  - `gd` ou `imagick` - Para processamento de imagens (opcional)
- **Composer**: 2.0+
- **Memória PHP**: Mínimo 128MB (recomendado 256MB+)

### Para Laravel

- **Laravel**: 10.0+ (recomendado 11.0+)
- **PHP**: 8.2+ (seguindo requisitos do Laravel)

### Extensões Opcionais

```bash
# Para melhor performance
php-opcache
php-redis

# Para processamento avançado
php-gd
php-imagick
php-intl
```

## Instalação

### Via Composer

Esta é a forma recomendada de instalação:

```bash
# Instalação via Composer
composer require clubify/checkout-sdk-php

# Para projetos Laravel (instala automaticamente)
composer require clubify/checkout-sdk-php
php artisan clubify:install
```

### Verificação da Instalação

```bash
# Verifica se foi instalado corretamente
composer show clubify/checkout-sdk-php

# Para Laravel - verifica comandos disponíveis
php artisan list clubify
```

### Instalação Manual

Se preferir instalar manualmente:

```bash
# 1. Clone o repositório
git clone https://github.com/clubify/checkout-sdk-php.git

# 2. Instale dependências
cd checkout-sdk-php
composer install --no-dev

# 3. Inclua no seu projeto
# Adicione ao composer.json do seu projeto:
{
    "repositories": [
        {
            "type": "path",
            "url": "./path/to/checkout-sdk-php"
        }
    ],
    "require": {
        "clubify/checkout-sdk-php": "*"
    }
}
```

## Configuração Básica

### 1. Obtenha suas Credenciais

Primeiro, você precisa das credenciais da API:

```php
// Estas informações são fornecidas no painel da Clubify
$config = [
    'api_key' => 'sua_api_key_aqui',
    'organization_id' => 'sua_organization_id',
    'environment' => 'sandbox', // ou 'production'
];
```

### 2. Configuração Mínima

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

// Configuração básica
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key_aqui',
    'organization_id' => 'sua_organization_id',
    'environment' => 'sandbox'
]);

// Teste da conexão
try {
    $organization = $clubify->organization()->get();
    echo "Conectado com sucesso! Organização: " . $organization['name'];
} catch (Exception $e) {
    echo "Erro na conexão: " . $e->getMessage();
}
```

## Configuração Laravel

### 1. Instalação Automática

```bash
# Execute o comando de instalação
php artisan clubify:install

# Isso irá:
# - Publicar o arquivo de configuração
# - Criar migrações (se necessário)
# - Configurar o ServiceProvider
# - Registrar a Facade
```

### 2. Configuração Manual

Se preferir configurar manualmente:

```bash
# Publique o arquivo de configuração
php artisan vendor:publish --tag=clubify-config

# Publique outros assets (opcional)
php artisan vendor:publish --tag=clubify-views
php artisan vendor:publish --tag=clubify-assets
```

### 3. Configuração do Environment

Adicione ao seu arquivo `.env`:

```env
# Configurações obrigatórias
CLUBIFY_API_KEY=sua_api_key_aqui
CLUBIFY_ORGANIZATION_ID=sua_organization_id
CLUBIFY_ENVIRONMENT=sandbox

# Configurações opcionais
CLUBIFY_API_URL=https://api.clubify.app
CLUBIFY_TIMEOUT=30
CLUBIFY_RETRIES=3
CLUBIFY_CACHE_TTL=3600

# Para Laravel
CLUBIFY_QUEUE=default
CLUBIFY_LOG_REQUESTS=true
```

### 4. Arquivo de Configuração

O arquivo `config/clubify.php` será criado automaticamente:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações da API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'key' => env('CLUBIFY_API_KEY'),
        'url' => env('CLUBIFY_API_URL', 'https://api.clubify.app'),
        'timeout' => env('CLUBIFY_TIMEOUT', 30),
        'retries' => env('CLUBIFY_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações da Organização
    |--------------------------------------------------------------------------
    */
    'organization' => [
        'id' => env('CLUBIFY_ORGANIZATION_ID'),
        'environment' => env('CLUBIFY_ENVIRONMENT', 'sandbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('CLUBIFY_CACHE_ENABLED', true),
        'ttl' => env('CLUBIFY_CACHE_TTL', 3600),
        'store' => env('CLUBIFY_CACHE_STORE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filas (Jobs)
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('CLUBIFY_QUEUE_ENABLED', true),
        'name' => env('CLUBIFY_QUEUE', 'default'),
        'connection' => env('CLUBIFY_QUEUE_CONNECTION', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('CLUBIFY_LOG_REQUESTS', true),
        'channel' => env('CLUBIFY_LOG_CHANNEL', 'daily'),
        'level' => env('CLUBIFY_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('CLUBIFY_WEBHOOKS_ENABLED', true),
        'secret' => env('CLUBIFY_WEBHOOK_SECRET'),
        'tolerance' => env('CLUBIFY_WEBHOOK_TOLERANCE', 300), // 5 minutos
    ],

    /*
    |--------------------------------------------------------------------------
    | Validações Brasileiras
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'cpf' => env('CLUBIFY_VALIDATE_CPF', true),
        'cnpj' => env('CLUBIFY_VALIDATE_CNPJ', true),
        'cep' => env('CLUBIFY_VALIDATE_CEP', true),
        'phone' => env('CLUBIFY_VALIDATE_PHONE', true),
    ],
];
```

### 5. Registro de ServiceProvider

Adicione ao `config/app.php` (se não foi adicionado automaticamente):

```php
'providers' => [
    // ...
    ClubifyCheckout\Laravel\ClubifyServiceProvider::class,
],

'aliases' => [
    // ...
    'Clubify' => ClubifyCheckout\Laravel\Facades\ClubifyFacade::class,
],
```

## Configuração PHP Vanilla

Para projetos PHP sem framework:

### 1. Autoloader

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;
use ClubifyCheckout\Config\Configuration;

// Configuração personalizada
$config = new Configuration([
    'api_key' => 'sua_api_key_aqui',
    'organization_id' => 'sua_organization_id',
    'environment' => 'sandbox',
    'api_url' => 'https://api.clubify.app',
    'timeout' => 30,
    'retries' => 3,
    'cache_enabled' => true,
    'cache_ttl' => 3600
]);

$clubify = new ClubifyCheckout($config);
```

### 2. Configuração com Arquivo

```php
// config.php
return [
    'api_key' => getenv('CLUBIFY_API_KEY'),
    'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
    'environment' => getenv('CLUBIFY_ENVIRONMENT') ?: 'sandbox',
    'api_url' => getenv('CLUBIFY_API_URL') ?: 'https://api.clubify.app',
    'timeout' => (int) getenv('CLUBIFY_TIMEOUT') ?: 30,
];

// app.php
$config = require 'config.php';
$clubify = new ClubifyCheckout($config);
```

### 3. Configuração com DotEnv

```bash
composer require vlucas/phpdotenv
```

```php
<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use ClubifyCheckout\ClubifyCheckout;

// Carrega variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$clubify = new ClubifyCheckout([
    'api_key' => $_ENV['CLUBIFY_API_KEY'],
    'organization_id' => $_ENV['CLUBIFY_ORGANIZATION_ID'],
    'environment' => $_ENV['CLUBIFY_ENVIRONMENT'] ?? 'sandbox',
]);
```

## Configuração Avançada

### 1. Múltiplos Ambientes

```php
// config/environments.php
return [
    'sandbox' => [
        'api_url' => 'https://sandbox-api.clubify.app',
        'api_key' => 'sandbox_key',
        'debug' => true,
    ],
    'production' => [
        'api_url' => 'https://api.clubify.app',
        'api_key' => 'production_key',
        'debug' => false,
    ],
];

// Usage
$environments = require 'config/environments.php';
$env = getenv('APP_ENV') ?: 'sandbox';

$clubify = new ClubifyCheckout($environments[$env]);
```

### 2. Cache Personalizado

```php
use ClubifyCheckout\Cache\FileCache;
use ClubifyCheckout\Cache\RedisCache;

// Cache em arquivo
$cache = new FileCache('/tmp/clubify-cache');

// Cache Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$cache = new RedisCache($redis);

$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'cache' => $cache,
]);
```

### 3. Logger Personalizado

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('clubify');
$logger->pushHandler(new StreamHandler('logs/clubify.log', Logger::INFO));

$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'logger' => $logger,
]);
```

### 4. Client HTTP Personalizado

```php
use GuzzleHttp\Client;

$client = new Client([
    'timeout' => 60,
    'verify' => true,
    'headers' => [
        'User-Agent' => 'MeuApp/1.0'
    ]
]);

$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'http_client' => $client,
]);
```

## Verificação da Instalação

### 1. Teste Básico de Conexão

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

try {
    $clubify = new ClubifyCheckout([
        'api_key' => 'sua_api_key',
        'organization_id' => 'sua_organization_id',
        'environment' => 'sandbox'
    ]);

    // Teste de conectividade
    $organization = $clubify->organization()->get();

    echo "✅ Instalação bem-sucedida!\n";
    echo "📋 Organização: {$organization['name']}\n";
    echo "🆔 ID: {$organization['id']}\n";
    echo "🌍 Ambiente: {$organization['environment']}\n";

} catch (Exception $e) {
    echo "❌ Erro na instalação: {$e->getMessage()}\n";
    echo "💡 Verifique suas credenciais e conexão com a internet.\n";
}
```

### 2. Teste Laravel

```bash
# Comando de diagnóstico
php artisan clubify:test

# Verifica configuração
php artisan clubify:status

# Lista todos os comandos disponíveis
php artisan list clubify
```

### 3. Script de Verificação Completa

```php
<?php

// tests/verify-installation.php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

class InstallationVerifier
{
    private $clubify;
    private $results = [];

    public function __construct($config)
    {
        $this->clubify = new ClubifyCheckout($config);
    }

    public function run(): array
    {
        $this->testConnection();
        $this->testModules();
        $this->testPermissions();

        return $this->results;
    }

    private function testConnection(): void
    {
        try {
            $org = $this->clubify->organization()->get();
            $this->results['connection'] = [
                'status' => 'success',
                'organization' => $org['name']
            ];
        } catch (Exception $e) {
            $this->results['connection'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function testModules(): void
    {
        $modules = ['organization', 'products', 'checkout', 'payments', 'customers', 'webhooks'];

        foreach ($modules as $module) {
            try {
                $service = $this->clubify->$module();
                $this->results['modules'][$module] = 'available';
            } catch (Exception $e) {
                $this->results['modules'][$module] = 'error: ' . $e->getMessage();
            }
        }
    }

    private function testPermissions(): void
    {
        $permissions = [
            'read_products' => fn() => $this->clubify->products()->list(),
            'read_customers' => fn() => $this->clubify->customers()->list(limit: 1),
        ];

        foreach ($permissions as $permission => $test) {
            try {
                $test();
                $this->results['permissions'][$permission] = 'granted';
            } catch (Exception $e) {
                $this->results['permissions'][$permission] = 'denied: ' . $e->getMessage();
            }
        }
    }
}

// Executar verificação
$config = [
    'api_key' => getenv('CLUBIFY_API_KEY'),
    'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
    'environment' => 'sandbox'
];

$verifier = new InstallationVerifier($config);
$results = $verifier->run();

echo "🔍 Resultados da Verificação:\n\n";
print_r($results);
```

## Próximos Passos

### 1. Primeiros Passos

Após a instalação bem-sucedida:

```php
// 1. Configure sua primeira oferta
$offer = $clubify->offers()->create([
    'name' => 'Minha Primeira Oferta',
    'description' => 'Oferta de teste',
    'price' => 9999, // R$ 99,99 em centavos
]);

// 2. Crie um produto
$product = $clubify->products()->create([
    'name' => 'Produto Teste',
    'price' => 5000, // R$ 50,00
    'type' => 'digital'
]);

// 3. Adicione produto à oferta
$clubify->offers()->addProduct($offer['id'], $product['id']);

// 4. Teste um checkout
$checkout = $clubify->checkout()->create([
    'offer_id' => $offer['id'],
    'customer_email' => 'test@example.com'
]);

echo "✅ Checkout criado: {$checkout['checkout_url']}\n";
```

### 2. Configuração de Produção

```php
// Checklist para produção:

// ✅ 1. Configure environment como 'production'
'environment' => 'production',

// ✅ 2. Use HTTPS
'api_url' => 'https://api.clubify.app',

// ✅ 3. Configure cache para produção
'cache' => [
    'enabled' => true,
    'store' => 'redis', // ou 'memcached'
    'ttl' => 3600
],

// ✅ 4. Configure logs apropriados
'logging' => [
    'level' => 'error', // Menos verbose em produção
    'channel' => 'daily'
],

// ✅ 5. Configure timeouts adequados
'timeout' => 60,
'retries' => 5,
```

### 3. Recursos Recomendados

- 📖 [Documentação dos Módulos](modules/)
- 🎯 [Exemplos Básicos](examples/basic-usage.md)
- 🚀 [Casos de Uso Avançados](examples/advanced-usage.md)
- 🔧 [Integração Laravel](laravel/)
- ❓ [FAQ e Troubleshooting](troubleshooting.md)

## Troubleshooting

### Problemas Comuns

#### 1. Erro de Credenciais

```
Error: Invalid API key or organization ID
```

**Solução:**
```php
// Verifique se as credenciais estão corretas
var_dump([
    'api_key' => getenv('CLUBIFY_API_KEY'),
    'org_id' => getenv('CLUBIFY_ORGANIZATION_ID')
]);

// Teste com credenciais hardcoded temporariamente
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key_real',
    'organization_id' => 'sua_org_id_real',
    'environment' => 'sandbox'
]);
```

#### 2. Erro de SSL/TLS

```
cURL error 60: SSL certificate problem
```

**Solução:**
```php
// Temporariamente para desenvolvimento
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'verify_ssl' => false // ⚠️ Apenas para desenvolvimento!
]);

// Ou configure o CA bundle
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'ca_bundle' => '/path/to/cacert.pem'
]);
```

#### 3. Timeout de Conexão

```
cURL error 28: Operation timed out
```

**Solução:**
```php
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'timeout' => 60, // Aumenta timeout para 60s
    'connect_timeout' => 10 // Timeout de conexão
]);
```

#### 4. Erro de Memória PHP

```
Fatal error: Allowed memory size exhausted
```

**Solução:**
```php
// No php.ini ou via código
ini_set('memory_limit', '256M');

// Ou use processamento em lote
$customers = $clubify->customers()->list(limit: 100, page: 1);
```

#### 5. Laravel ServiceProvider não encontrado

```bash
# Re-registre o ServiceProvider
composer dump-autoload

# Limpe caches
php artisan config:clear
php artisan cache:clear

# Re-publique a configuração
php artisan vendor:publish --tag=clubify-config --force
```

### Debug Avançado

```php
// Habilite debug mode
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'debug' => true,
    'logger' => new \Monolog\Logger('clubify'),
]);

// Log de todas as requisições
$clubify->enableRequestLogging();

// Intercepte respostas para debug
$clubify->onResponse(function ($response) {
    error_log("API Response: " . json_encode($response));
});
```

### Suporte

Se você ainda tem problemas:

1. 📧 **Email**: support@clubify.app
2. 📚 **Documentação**: https://docs.clubify.app
3. 💬 **Discord**: https://discord.gg/clubify
4. 🐛 **Issues**: https://github.com/clubify/checkout-sdk-php/issues

---

## Conclusão

Parabéns! 🎉 Você agora tem o Clubify Checkout SDK instalado e configurado.

**Próximos passos recomendados:**

1. ✅ Complete a [configuração básica](#configuração-básica)
2. 📖 Leia os [exemplos básicos](examples/basic-usage.md)
3. 🛠️ Explore a [documentação dos módulos](modules/)
4. 🚀 Implemente seu primeiro [checkout completo](examples/complete-checkout.md)

O SDK está pronto para transformar sua aplicação em uma plataforma de checkout poderosa e confiável!