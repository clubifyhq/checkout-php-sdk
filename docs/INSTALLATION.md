# 🚀 Guia de Instalação e Configuração - Clubify Checkout SDK

Este guia abrange a instalação automatizada e configuração do Clubify Checkout SDK para PHP, incluindo a nova gestão automatizada de credenciais.

## 📦 Instalação

### Via Composer (Recomendado)

```bash
composer require clubify/checkout-sdk-php
```

### Instalação Manual

```bash
git clone https://github.com/clubify/checkout-sdk-php.git
cd checkout-sdk-php
composer install
```

## ⚙️ Configuração Automática

O SDK inclui scripts de configuração automática que são executados após a instalação:

### 🔧 Scripts Disponíveis

| Script | Descrição | Comando |
|--------|-----------|---------|
| `post-install-setup.php` | Configuração completa pós-instalação | `composer run clubify:setup` |
| `setup-credentials-config.php` | Configuração específica de credenciais | `composer run clubify:config` |
| `generate-env-config.php` | Gerador interativo de configuração | `composer run clubify:env` |

### 🎯 Execução Automática

Os scripts são executados automaticamente durante:
- `composer install`
- `composer update`
- Instalação manual via `composer run clubify:setup`

## 🏗️ Tipos de Projeto Suportados

### 📱 Laravel

**Detecção Automática:** O SDK detecta automaticamente projetos Laravel.

**Arquivos Criados:**
- `config/clubify.php` - Configuração principal
- `config/clubify-credentials.php` - Gestão de credenciais
- `.env.clubify.example` - Template de variáveis

**Configuração:**
```bash
# Após instalação
php artisan config:cache
php artisan cache:clear

# Publicar configurações (opcional)
php artisan vendor:publish --tag=clubify-config
```

### 🔧 Standalone

**Para projetos PHP puros:**

**Arquivos Criados:**
- `config/clubify-credentials.php`
- `.env.clubify.example`
- Diretórios de logs e cache

**Configuração:**
```bash
# Copiar template de ambiente
cp .env.clubify.example .env

# Editar configurações
nano .env
```

### 📦 Como Dependência

**Quando instalado em outro projeto:**

```bash
# Configurar manualmente
composer run clubify:config

# Gerar configuração personalizada
composer run clubify:env
```

## 🔑 Gestão de Credenciais Automatizada

### ✨ Recursos Novos

- ✅ **Criação automática** de chaves de API
- ✅ **Rotação automática** de chaves antigas
- ✅ **Permissões completas** (role `tenant_admin`)
- ✅ **Rate limits** adequados para produção
- ✅ **Logging detalhado** de operações

### 📊 Configuração via Ambiente

#### Laravel (.env)
```bash
# Gestão de Credenciais
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
CLUBIFY_MAX_API_KEY_AGE_DAYS=90
CLUBIFY_KEY_ROTATION_GRACE_PERIOD=24

# Segurança
CLUBIFY_IP_WHITELIST=
CLUBIFY_ALLOWED_ORIGINS=*
CLUBIFY_MASK_SENSITIVE_DATA=true

# Cache e Logs
CLUBIFY_CACHE_CREDENTIALS=true
CLUBIFY_LOG_CREDENTIAL_OPS=true
```

#### Standalone (.env)
```bash
# Mesmas configurações do Laravel
CLUBIFY_API_KEY=your-super-admin-key
CLUBIFY_BASE_URL=https://checkout.svelve.com
CLUBIFY_ENVIRONMENT=production

# Credenciais automatizadas
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
```

## 🎛️ Configuração Interativa

### Gerador de Configuração

Execute o configurador interativo:

```bash
composer run clubify:env
```

**Funcionalidades:**
- 🔍 Detecção automática do tipo de projeto
- ❓ Perguntas interativas para personalização
- 📝 Geração de arquivos de configuração personalizados
- ✅ Validação de configurações

### Exemplo de Execução

```bash
$ composer run clubify:env

🔧 Gerador de Configuração de Ambiente - Clubify SDK
ℹ️ Tipo de projeto: laravel

Coletando informações de configuração...
❓ API Key do Clubify [your-api-key-here]: sk_live_abc123...
❓ URL Base da API [https://checkout.svelve.com]:
❓ Ambiente (development/staging/production) [production]:

Configurações de Gestão de Credenciais:
❓ Habilitar criação automática de chaves de API? (y/N): y
❓ Habilitar rotação automática de chaves? (y/N): y
❓ Idade máxima da chave (em dias) [90]:

✅ Configuração gerada com sucesso!
```

## 📁 Estrutura de Arquivos Gerados

### Laravel
```
projeto-laravel/
├── config/
│   ├── clubify.php                    # Configuração principal
│   └── clubify-credentials.php        # Gestão de credenciais
├── .env.clubify.example               # Template de ambiente
└── .env.clubify-credentials.example   # Template específico
```

### Standalone
```
projeto-php/
├── config/
│   └── clubify-credentials.php        # Configuração standalone
├── .env.clubify.example               # Template de ambiente
└── storage/
    ├── logs/                          # Logs do SDK
    └── cache/                         # Cache do SDK
```

## 🧪 Testando a Instalação

### Teste Rápido

```php
<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Carregar configurações
$config = [
    'api_key' => $_ENV['CLUBIFY_API_KEY'],
    'base_url' => $_ENV['CLUBIFY_BASE_URL'] ?? 'https://checkout.svelve.com',
    'environment' => $_ENV['CLUBIFY_ENVIRONMENT'] ?? 'production'
];

// Inicializar SDK
$sdk = new ClubifyCheckoutSDK($config);

// Testar gestão de credenciais (se disponível)
if (method_exists($sdk->superAdmin(), 'ensureTenantCredentials')) {
    echo "✅ Gestão automatizada de credenciais disponível!\n";
} else {
    echo "⚠️ Usando métodos de credenciais legacy\n";
}

echo "🎉 SDK configurado com sucesso!\n";
```

### Exemplo Completo

Execute o exemplo completo:

```bash
cd examples/example-app
php laravel-complete-example.php
```

**Funcionalidades testadas:**
- 🔍 Busca de tenants
- 🆕 Criação de organizações
- 🔑 Gestão automatizada de credenciais
- 🔄 Rotação de chaves antigas

## 🔧 Personalização Avançada

### Configuração Manual

Se preferir configurar manualmente:

```php
// config/clubify-custom.php
<?php

return [
    'credentials' => [
        'auto_create_api_keys' => true,
        'enable_key_rotation' => true,
        'max_api_key_age_days' => 60, // Customizado
        'default_tenant_permissions' => [
            // Permissões customizadas
            'tenants' => ['read', 'write'],
            'users' => ['read', 'write'],
            // ... mais permissões
        ],
        'default_rate_limits' => [
            'requests_per_minute' => 500, // Limitado
            'requests_per_hour' => 20000,
            'requests_per_day' => 400000,
        ],
    ],
];
```

### Hooks de Instalação

Para personalizar o processo de instalação:

```json
// composer.json (do seu projeto)
{
    "scripts": {
        "post-install-cmd": [
            "@php scripts/custom-setup.php"
        ]
    }
}
```

## 🆘 Solução de Problemas

### Problemas Comuns

**1. Permissões de Arquivo**
```bash
chmod +x scripts/*.php
```

**2. Configuração não Encontrada**
```bash
composer run clubify:config
```

**3. Variáveis de Ambiente**
```bash
# Verificar se .env está carregado
php -r "var_dump($_ENV['CLUBIFY_API_KEY'] ?? 'não encontrado');"
```

**4. Cache de Configuração (Laravel)**
```bash
php artisan config:clear
php artisan config:cache
```

### Logs de Debug

Habilite logs detalhados:

```bash
# .env
CLUBIFY_DEBUG=true
CLUBIFY_LOG_ENABLED=true
CLUBIFY_LOG_LEVEL=debug
CLUBIFY_LOG_CREDENTIAL_OPS=true
```

### Suporte

- 📚 **Documentação:** https://docs.clubify.com/sdk/php
- 🐛 **Issues:** https://github.com/clubify/checkout-sdk-php/issues
- 📧 **Email:** sdk-support@clubify.com

## 🎯 Próximos Passos

1. ✅ **Instale o SDK:** `composer require clubify/checkout-sdk-php`
2. ⚙️ **Configure automaticamente:** Scripts executam sozinhos
3. 🔑 **Configure suas credenciais:** Edite `.env`
4. 🧪 **Teste a integração:** Execute exemplos
5. 🚀 **Implemente em produção:** Use gestão automatizada

---

**🎉 Pronto! Seu SDK está configurado e a gestão automatizada de credenciais está funcionando!**