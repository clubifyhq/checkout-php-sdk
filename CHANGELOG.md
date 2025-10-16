# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added
- Suporte nativo a webhooks multi-tenant
  - Callback `webhook.secret_resolver` para customização total da resolução de secrets
  - Busca automática de secret por `organization_id` via Model Organization
  - Suporte a `organization_id` via header `X-Organization-ID` ou payload JSON
  - Fallback para config global (retrocompatibilidade com single-tenant)
  - Documentação completa em `docs/webhooks/multi-tenant-setup.md`
  - Exemplos práticos em `examples/webhooks/`
- Configurações multi-tenant no arquivo de configuração:
  - `webhook.secret_resolver` - Callback customizado
  - `webhook.organization_model` - Namespace do model Organization
  - `webhook.organization_secret_key` - Nome do campo onde está o secret

### Changed
- Middleware `ValidateWebhook` agora suporta múltiplas estratégias de autenticação
  - Prioridade 1: Callback customizado (`secret_resolver`)
  - Prioridade 2: Model Organization automático
  - Prioridade 3: Config global (fallback single-tenant)
- Busca de `organization_id` aprimorada:
  - Header `X-Organization-ID` (prioridade)
  - Payload JSON: `organization_id`, `data.organization_id`, `organizationId`, `data.organizationId`

### Fixed
- Nenhum (100% retrocompatível)

### Security
- Webhooks agora podem ter secrets únicos por organização
- Maior segurança em ambientes multi-tenant com isolamento de secrets

---

## [1.0.0] - 2025-01-16

### Added
- Release inicial do Clubify Checkout SDK para PHP
- Módulos principais:
  - Organizations (gerenciamento de organizações)
  - Products (gerenciamento de produtos)
  - Offers (gerenciamento de ofertas)
  - Cart (carrinho de compras)
  - Checkout (processo de checkout)
  - Payments (processamento de pagamentos)
  - Customers (gerenciamento de clientes)
  - Webhooks (gerenciamento de webhooks)
- Integração nativa com Laravel via Service Provider
- Middleware para validação de webhooks
- Sistema de cache configurável
- Sistema de logging integrado
- Retry automático em requisições HTTP
- Validação de dados com DTOs
- Suporte a múltiplos ambientes (development, sandbox, staging, production)

### Documentation
- README.md completo com guias de instalação e uso
- Documentação de módulos em `docs/modules/`
- Exemplos práticos em `examples/`
- Guias de migração e troubleshooting

---

[Unreleased]: https://github.com/clubify/checkout-sdk-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/clubify/checkout-sdk-php/releases/tag/v1.0.0
