# Clubify Checkout SDK - Exemplos de Uso

Este diretório contém exemplos práticos de como usar o SDK PHP do Clubify Checkout.

## Exemplos Disponíveis

### 1. Organization Tenant Management
**Arquivo:** `organization-tenant-management-example.php`

Demonstra como:
- Autenticar como Organization Admin usando Organization API Key
- Listar tenants existentes na organização
- Criar novos tenants dentro da organização
- Alternar entre contextos (organization ↔ tenant)
- Provisionar recursos (produtos, webhooks, credenciais)
- Realizar operações cross-tenant

**Quando usar:** Quando você é um Organization Admin e precisa gerenciar múltiplos tenants.

### 2. Organization Authentication
**Arquivo:** `organization-authentication-example.php`

Demonstra os diferentes métodos de autenticação usando Organization API Keys com escopos:
- ORGANIZATION: Acesso total à organização
- CROSS_TENANT: Acesso multi-tenant
- TENANT: Acesso restrito a um tenant específico

### 3. Laravel Complete Example
**Arquivo:** `example-app/laravel-complete-example.php`

Exemplo completo de integração com Laravel, incluindo:
- Bootstrap do Laravel
- Autenticação como Super Admin
- Criação de organizações
- Provisionamento completo de recursos

## Configuração

### Método 1: Arquivo .env (Recomendado)

1. Copie o arquivo de exemplo:
```bash
cp sdk/checkout/php/.env.example sdk/checkout/php/.env
```

2. Edite o arquivo `.env` com suas credenciais:
```bash
CLUBIFY_CHECKOUT_ORGANIZATION_ID=sua-organization-id
CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY=clb_org_live_xxxxx
CLUBIFY_CHECKOUT_ENVIRONMENT=staging
```

### Método 2: Variáveis de Ambiente

```bash
export CLUBIFY_CHECKOUT_ORGANIZATION_ID="sua-organization-id"
export CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY="clb_org_live_xxxxx"
export CLUBIFY_CHECKOUT_ENVIRONMENT="staging"
```

### Método 3: Configuração Direta no Código

Edite o arquivo de exemplo e altere os valores diretamente no array `$EXAMPLE_CONFIG`.

## Execução

```bash
# Navegar para o diretório de exemplos
cd sdk/checkout/php/examples

# Executar o exemplo de organization tenant management
php organization-tenant-management-example.php

# Executar o exemplo de autenticação
php organization-authentication-example.php
```

## Diferenças entre Super Admin e Organization Admin

### Super Admin
- Acesso total ao sistema
- Pode criar e gerenciar organizations
- Pode acessar qualquer tenant de qualquer organization
- Usado para operações administrativas do sistema

### Organization Admin
- Acesso limitado à sua organization
- Pode criar e gerenciar tenants dentro da sua organization
- Não pode acessar tenants de outras organizations
- Usado para operações do dia a dia

## Estrutura de Hierarquia

```
Sistema
  └── Organization (gerenciada por Super Admin)
      └── Tenant 1 (gerenciado por Organization Admin)
      └── Tenant 2 (gerenciado por Organization Admin)
      └── Tenant N (gerenciado por Organization Admin)
```

## Troubleshooting

### Erro: "Variáveis de ambiente obrigatórias não configuradas"

Certifique-se de que configurou as variáveis de ambiente corretamente usando um dos métodos acima.

### Erro: "Falha na autenticação como organization"

Verifique se:
- O Organization ID está correto
- A API Key é válida e não expirou
- A API Key tem as permissões corretas
- Você está usando o ambiente correto (staging/production)

### Erro de permissão ao criar tenant

Verifique se sua Organization API Key tem o escopo `ORGANIZATION` e permissões para criar tenants.

## Suporte

Para mais informações, consulte a documentação oficial ou entre em contato com o suporte técnico.
