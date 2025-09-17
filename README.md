# Clubify Checkout SDK - PHP

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-10%2B-red.svg)](https://laravel.com/)

SDK oficial para integração com a plataforma Clubify Checkout em PHP. Uma solução unificada que combina todas as funcionalidades em uma arquitetura enterprise-grade, manutenível e extensível, seguindo os princípios de Clean Code.

## ✨ Características Principais

- 🚀 **SDK Unificado**: Paridade completa com o SDK JavaScript
- 💪 **Enterprise-Grade**: Arquitetura robusta seguindo princípios SOLID
- 🔧 **Developer Experience**: API intuitiva com suporte completo ao PHP 8.2+
- 📦 **Laravel Ready**: Integração nativa com Laravel Framework
- 🌐 **Multi-Platform**: Funciona em qualquer aplicação PHP
- 🔒 **Segurança Avançada**: JWT, criptografia e autenticação robusta
- ⚡ **Performance**: Cache multi-nível e operações otimizadas
- 🎯 **100% Testado**: Cobertura completa de testes (meta)

## 🏗️ Status Atual

### ✅ **Fase 1: Core Foundation** (CONCLUÍDA)
- [x] Estrutura do projeto e Composer
- [x] Classe principal ClubifyCheckoutSDK
- [x] Sistema de configuração centralizada
- [x] Cliente HTTP com Guzzle e retry
- [x] Autenticação JWT completa
- [x] Sistema de eventos
- [x] Cache manager
- [x] Logger PSR-3

### 🔄 **Próximas Fases**
- [ ] **Fase 2**: Módulos Principais (Organization, Products, Checkout, etc.)
- [ ] **Fase 3**: Laravel Integration completa
- [ ] **Fase 4**: Testes e Documentação

## 📦 Instalação

### Via Composer

```bash
composer require clubify/checkout-sdk-php
```

### Requisitos

- PHP 8.2 ou superior
- Extensões: `json`, `openssl`, `curl`
- Laravel 10+ (opcional, para integração)

## 🚀 Início Rápido

### 1. Configuração Básica

```php
<?php

use Clubify\Checkout\ClubifyCheckoutSDK;

// Instanciar o SDK
$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'tenant_id' => 'seu-tenant-id',
        'api_key' => 'sua-api-key',
        'environment' => 'production', // 'development' | 'staging' | 'production'
    ]
]);

// Inicializar
$result = $sdk->initialize();
echo "✅ SDK pronto para uso!";
```

### 2. Exemplo de Uso (Módulos em desenvolvimento)

```php
// Exemplo futuro quando módulos estiverem implementados
$session = $sdk->createCheckoutSession([
    'offer_id' => 'offer_123',
    'customer' => [
        'email' => 'cliente@exemplo.com',
        'name' => 'João Silva'
    ]
]);
```

## 🏗️ Arquitetura Implementada

### Componentes Core ✅

#### **Configuration System**
- Merge de configurações default e custom
- Validação automática de configurações
- Suporte a múltiplos ambientes
- API fluente para acesso

#### **HTTP Client**
- Baseado em Guzzle HTTP
- Retry automático com backoff exponencial
- Sistema de interceptors
- Timeout configurável
- Error handling padronizado

#### **Authentication Manager**
- Gerenciamento de tokens JWT
- Refresh automático de tokens
- Storage persistente de tokens
- Multi-tenant support
- Headers de autorização automáticos

#### **Event Dispatcher**
- Event dispatcher pattern
- Sistema de prioridades
- Event subscribers
- Cancelamento de eventos
- Performance otimizada

#### **Cache Manager**
- Interface PSR-6 compatível
- TTL support
- Múltiplos adapters
- Invalidação inteligente
- Estatísticas de cache

#### **Logger PSR-3**
- Compatível com PSR-3
- Múltiplos níveis de log
- Context injection
- Child loggers
- Formatação configurável

### Estrutura de Diretórios

```
sdk/php/
├── src/
│   ├── ClubifyCheckoutSDK.php      # Classe principal ✅
│   ├── Core/                       # Componentes centrais ✅
│   │   ├── Config/                 # Sistema de configuração ✅
│   │   ├── Http/                   # Cliente HTTP com retry ✅
│   │   ├── Auth/                   # Autenticação JWT ✅
│   │   ├── Events/                 # Sistema de eventos ✅
│   │   ├── Cache/                  # Gerenciamento de cache ✅
│   │   └── Logger/                 # Logging PSR-3 ✅
│   ├── Modules/                    # Módulos funcionais (TODO)
│   ├── Exceptions/                 # Exceções estruturadas ✅
│   ├── Enums/                      # Enumerações PHP 8+ ✅
│   ├── Utils/                      # Utilitários (TODO)
│   └── Laravel/                    # Integração Laravel (TODO)
├── tests/                          # Testes (TODO)
├── docs/                           # Documentação
├── examples/                       # Exemplos de uso
└── composer.json                   # Configuração Composer ✅
```

## 🧪 Desenvolvimento

```bash
# Instalar dependências
composer install

# Qualidade de código
composer cs-check               # Verificar code style
composer cs-fix                 # Corrigir code style
composer phpstan                # Static analysis
composer test                   # Executar testes (TODO)

# Validação completa
composer check                  # Todos os checks de qualidade
```

## 🤝 Princípios de Desenvolvimento

### Clean Code ✅
- SOLID principles aplicados
- Single Responsibility Pattern
- Dependency Injection
- Interface Segregation
- Type Safety com PHP 8.2+

### Design Patterns ✅
- Factory Pattern (planejado para gateways)
- Strategy Pattern (retry strategies)
- Observer Pattern (events)
- Decorator Pattern (interceptors)
- Repository Pattern (planejado)

### PHP 8.2+ Features ✅
- Readonly Properties
- Enums
- Union Types
- Named Arguments
- Constructor Property Promotion
- Attributes (planejado)

## 📚 Documentação

- 📖 **[Plano Completo de Desenvolvimento](../docs/technical-strategies/clubify-checkout-sdk-php-development-plan.md)** - Estratégia detalhada
- 🔧 **Configuração**: Veja exemplos no código fonte
- 🎯 **Exemplos**: Diretório `/examples` (TODO)

## 🚀 Roadmap Detalhado

### ✅ **Fase 1: Core Foundation** (3 dias - CONCLUÍDA)

**1.1 Configuração do Projeto ✅**
- [x] composer.json com dependências e scripts
- [x] Estrutura de diretórios PSR-4
- [x] Configuração de ferramentas de qualidade

**1.2 Core Modules ✅**
- [x] Sistema de configuração centralizada
- [x] Cliente HTTP com Guzzle e retry
- [x] Autenticação JWT com refresh automático
- [x] Sistema de eventos com dispatcher
- [x] Cache manager com PSR-6
- [x] Logger PSR-3 estruturado

**1.3 Classe Principal ✅**
- [x] ClubifyCheckoutSDK com inicialização
- [x] Validação de configuração mínima
- [x] Métodos de conveniência
- [x] Gestão de estado de inicialização

### 🔄 **Fase 2: Módulos Principais** (3-4 dias - PRÓXIMA)

**2.1 OrganizationModule**
- [ ] Setup completo de organização
- [ ] Gestão de tenants
- [ ] Criação de admin users
- [ ] Geração de API keys
- [ ] Configuração de domínios

**2.2 ProductsModule**
- [ ] CRUD de produtos
- [ ] Gestão de ofertas
- [ ] Order bumps
- [ ] Upsells
- [ ] Sistema de preços
- [ ] Flows de navegação

**2.3 CheckoutModule**
- [ ] Gestão de sessões
- [ ] Operações de carrinho
- [ ] One-click purchases
- [ ] Flow navigation
- [ ] Redirecionamentos

**2.4 PaymentsModule**
- [ ] Suporte multi-gateway
- [ ] Tokenização de cartões
- [ ] Processamento de transações
- [ ] Mecanismos de retry
- [ ] Histórico de transações

**2.5 CustomersModule**
- [ ] Customer matching
- [ ] Gestão de perfis
- [ ] Histórico de compras
- [ ] Sistema de recomendações
- [ ] Segmentação

**2.6 WebhooksModule**
- [ ] Configuração de webhooks
- [ ] Tipos de eventos
- [ ] Mecanismos de retry
- [ ] Validação de assinatura
- [ ] Utilitários de teste

### 📋 **Fase 3: Laravel Integration** (2-3 dias)

**3.1 Service Provider**
- [ ] Binding de interfaces
- [ ] Registro de facades
- [ ] Publicação de configuração
- [ ] Registro de commands

**3.2 Laravel Features**
- [ ] Facades ClubifyCheckout
- [ ] Artisan Commands
- [ ] Middleware de autenticação
- [ ] Form Requests de validação
- [ ] Jobs para processamento assíncrono
- [ ] Events/Listeners
- [ ] Rules de validação customizadas

### 🧪 **Fase 4: Testes e Documentação** (2 dias)

**4.1 Testing**
- [ ] Unit Tests com PHPUnit
- [ ] Integration Tests
- [ ] Feature Tests
- [ ] Laravel Tests
- [ ] Mocking com Mockery
- [ ] Coverage 90%+

**4.2 Documentação**
- [ ] API Documentation completa
- [ ] Exemplos de uso
- [ ] Guia de integração Laravel
- [ ] Guia de migração do JavaScript
- [ ] Best practices

**Total Estimado: 9-12 dias**

## 📄 Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE).

## 🆘 Suporte

- 📚 **Documentação**: [docs.clubify.com/sdk/php](https://docs.clubify.com/sdk/php)
- 🐛 **Issues**: [GitHub Issues](https://github.com/clubify/checkout-sdk-php/issues)
- 📧 **Email**: [sdk-support@clubify.com](mailto:sdk-support@clubify.com)

---

<div align="center">

**Desenvolvido com ❤️ pela equipe Clubify seguindo os princípios de Clean Code**

[Website](https://clubify.com) • [Documentação](https://docs.clubify.com) • [Blog](https://blog.clubify.com)

</div>