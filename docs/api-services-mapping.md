# Mapeamento Completo dos Serviços API - Clubify Checkout

Este documento mapeia todos os serviços disponíveis na API do Clubify Checkout e seus endpoints principais.

## 1. Cart Service (`/apps/cart-service/`)
**Base URL:** `/api/v1/cart`
**Controladores:**
- **CartController** - Gestão de carrinho
- **NavigationController** - **Sistema Avançado de Flow Navigation** ✨
- **ItemsController** - Operações de itens do carrinho
- **AffiliateController** - Rastreamento de afiliados
- **PromotionController** - Gestão de cupons/promoções
- **OneClickCheckoutController** - Checkout one-click
- **UserPreferencesController** - Preferências do usuário

**Principais Endpoints:**
- `POST /api/v1/cart` - Criar carrinho
- `GET /api/v1/cart/:id` - Obter carrinho
- `POST /api/v1/cart/:id/items` - Adicionar itens
- `GET /navigation/flow/:offerId` - **Obter configuração de flow** ✨
- `POST /navigation/flow/:offerId` - **Criar flow** ✨
- `GET /navigation/flows` - **Listar todos os flows** ✨
- `GET /navigation/analytics/:flowId` - **Analytics de flow** ✨
- `POST /api/v1/cart/:id/promotions` - Aplicar promoção
- `POST /api/v1/cart/:id/one-click` - Checkout one-click

## 2. Checkout Service (`/apps/checkout-service/`)
**Base URL:** `/checkout`
**Controladores:**
- **CheckoutController** - Orquestração de checkout
- **PublicCheckoutController** - Endpoints públicos de checkout

**Principais Endpoints:**
- `POST /checkout/process` - Processar checkout
- `GET /checkout/:id/status` - Obter status do checkout
- `PUT /checkout/:id/payment-method` - Atualizar método de pagamento
- `GET /checkout/session/:sessionId` - Obter checkout por sessão

## 3. Customer Service (`/apps/customer-service/`)
**Base URL:** `/customers`
**Controladores:**
- **CustomerController** - Gestão de clientes
- **CustomerAddressController** - Gestão de endereços
- **PublicCustomerController** - Endpoints públicos de cliente
- **GdprController** - Conformidade GDPR
- **LgpdController** - Conformidade LGPD
- **IntegrationController** - Integrações externas

**Principais Endpoints:**
- `POST /customers` - Criar cliente
- `GET /customers` - Listar clientes com filtros
- `POST /customers/search` - Busca avançada de clientes
- `GET /customers/analytics` - Analytics de clientes
- `PATCH /customers/:id/pagarme-link` - Vincular ao Pagar.me
- `GET /customers/email/:email/payment-info` - Info de pagamento por email
- `POST /customers/:id/addresses` - Adicionar endereço
- `GET /customers/:id/gdpr/export` - Exportar dados GDPR
- `POST /customers/:id/lgpd/consent` - Consentimento LGPD

## 4. Documentation Service (`/apps/documentation-service/`)
**Base URL:** `/docs` (presumido)
**Nota:** Serviço de documentação estática - sem controladores REST

## 5. Notification Service (`/apps/notification-service/`)
**Base URL:** `/notifications`
**Controladores:**
- **NotificationController** - Gestão de notificações
- **WebhookConfigurationController** - Configuração de webhooks

**Principais Endpoints:**
- `GET /notifications/health` - Health check
- `GET /notifications/logs` - Obter logs de notificação com filtros
- `GET /notifications/stats` - Estatísticas de notificação
- `POST /notifications/test-webhook` - Testar entrega de webhook
- `POST /notifications/webhook/config` - Configurar webhook
- `GET /notifications/webhook/events` - Tipos de eventos disponíveis

## 6. Offer Service (`/apps/offer-service/`)
**Base URL:** `/offers`
**Controladores:**
- **OfferController** - Gestão de ofertas
- **UpsellController** - **Sistema de upsells** ✨
- **PublicOfferController** - Endpoints públicos de ofertas

**Principais Endpoints:**
- `POST /offers` - Criar oferta
- `GET /offers` - Listar ofertas
- `PUT /offers/:id/config/theme` - **Atualizar configuração de tema** ✨
- `PUT /offers/:id/config/layout` - **Atualizar configuração de layout** ✨
- `GET /offers/themes/presets` - **Obter presets de tema** ✨
- `POST /offers/:offerId/upsells` - **Criar upsell** ✨
- `GET /offers/:offerId/upsells` - **Listar upsells** ✨
- `GET /offers/:id/subscription/plans` - Obter planos de assinatura
- `POST /offers/:id/generate-url` - Gerar URL de checkout

## 7. Order Service (`/apps/order-service/`)
**Base URL:** `/orders`
**Controladores:**
- **OrderController** - Gestão de pedidos
- **PublicOrderController** - Endpoints públicos de pedidos
- **UpsellOrderController** - Processamento de pedidos upsell

**Principais Endpoints:**
- `GET /orders/health` - Health check
- `GET /orders/statistics` - Estatísticas de pedidos
- `GET /orders` - Listar pedidos com filtros
- `GET /orders/:id` - Obter pedido por ID
- `POST /orders` - Criar pedido
- `PUT /orders/:id/status` - Atualizar status do pedido
- `POST /orders/:id/cancel` - Cancelar pedido
- `POST /orders/:id/upsell` - Processar upsell

## 8. Payment Service (`/apps/payment-service/`)
**Base URL:** `/api/v1/payments`
**Controladores:**
- **PaymentController** - Processamento de pagamentos
- **CardVaultController** - Gestão de cartões salvos
- **DigitalWalletController** - **Carteiras digitais (Apple Pay/Google Pay)** ✨
- **PaymentMethodsController** - Métodos de pagamento
- **PixController** - Pagamentos PIX
- **SubscriptionController** - Pagamentos de assinatura
- **WebhookController** - Webhooks de pagamento
- **TokenizationController** - Tokenização de cartões
- **GatewayController** - Gestão de gateways de pagamento

**Principais Endpoints:**
- `POST /api/v1/payments/credit-card` - Processar pagamento cartão de crédito
- `POST /api/v1/payments/pix` - Criar pagamento PIX
- `POST /api/v1/payments/subscription` - Criar pagamento de assinatura
- `GET /digital-wallets/config` - **Configuração de carteira digital** ✨
- `POST /digital-wallets/apple-pay/validate-merchant` - **Validação Apple Pay** ✨
- `POST /digital-wallets/process-payment` - **Processar pagamento carteira digital** ✨
- `GET /api/v1/payments/installments` - Obter opções de parcelamento
- `GET /api/v1/tokenization/config` - Obter configuração de tokenização
- `POST /api/v1/payments/gateway/switch` - Alternar gateway
- `GET /api/v1/payments/methods` - Listar métodos de pagamento

## 9. Product Service (`/apps/product-service/`)
**Base URL:** `/products`
**Controladores:**
- **ProductController** - Gestão de produtos
- **PublicProductController** - Endpoints públicos de produtos

**Principais Endpoints:**
- `POST /products` - Criar produto
- `GET /products` - Listar produtos
- `GET /products/categories` - Obter categorias
- `GET /products/search` - Buscar produtos
- `PATCH /products/:id/inventory` - Atualizar estoque
- `GET /products/:id/analytics` - Analytics de produto
- `POST /products/:id/variants` - Criar variantes
- `GET /products/:id/variants` - Listar variantes

## 10. Subscription Service (`/apps/subscription-service/`)
**Base URL:** `/api/v1/subscriptions`
**Controladores:**
- **SubscriptionManagementController** - Gestão de assinaturas
- **SubscriptionPlanController** - Planos de assinatura

**Principais Endpoints:**
- `POST /api/v1/subscriptions` - Criar assinatura
- `GET /api/v1/subscriptions` - Listar assinaturas
- `GET /api/v1/subscriptions/metrics` - Métricas de assinatura
- `PATCH /api/v1/subscriptions/:id/status` - Atualizar status da assinatura
- `POST /api/v1/subscriptions/:id/cancel` - Cancelar assinatura
- `POST /api/v1/subscriptions/:id/upgrade` - Fazer upgrade da assinatura
- `POST /api/v1/subscriptions/:id/bill` - Cobrança manual
- `GET /api/v1/subscriptions/plans` - Listar planos de assinatura
- `POST /api/v1/subscriptions/plans` - Criar plano de assinatura

## 11. Tracking Service (`/apps/tracking-service/`)
**Base URL:** `/events`
**Controladores:**
- **EventsController** - Rastreamento de eventos

**Principais Endpoints:**
- `POST /events/event` - Rastrear evento único
- `POST /events/batch` - Rastrear lote de eventos (preferido)
- `POST /events/beacon` - Rastrear evento beacon (unload de página)
- `GET /events/stats` - Estatísticas de eventos
- `GET /events/analytics` - Analytics de eventos

## 12. User Management Service (`/apps/user-management-service/`)
**Base URL:** `/users`
**Controladores:**
- **UsersController** - Gestão de usuários
- **PasskeysController** - **Autenticação Passkeys/WebAuthn** ✨
- **AuthController** - Autenticação
- **TenantsController** - Gestão de tenants
- **RoleController** - Gestão de roles
- **SessionController** - Gestão de sessões
- **ApiKeysController** - Gestão de chaves API
- **DomainManagementController** - Gestão de domínios

**Principais Endpoints:**
- `POST /users` - Criar usuário
- `GET /users` - Listar usuários
- `GET /users/stats` - Estatísticas de usuários
- `POST /auth/passkeys/register/begin` - **Iniciar registro passkey** ✨
- `POST /auth/passkeys/register/complete` - **Completar registro passkey** ✨
- `POST /auth/passkeys/authenticate/begin` - **Iniciar autenticação passkey** ✨
- `POST /auth/passkeys/authenticate/complete` - **Completar autenticação passkey** ✨
- `GET /auth/passkeys/support` - **Verificar suporte WebAuthn do browser** ✨
- `POST /auth/passkeys/reauth/initiate` - **Iniciar re-autenticação** ✨
- `POST /tenants` - Criar tenant
- `GET /tenants/:id/api-keys` - Listar chaves API do tenant
- `POST /tenants/:id/domains` - Configurar domínio customizado

## Resumo de Funcionalidades por Serviço

### 🌟 **Funcionalidades Avançadas** ✨

1. **Sistema de Flow Navigation** (Cart Service)
   - Configuração de flow baseada em JSON
   - 50+ regras de validação
   - Analytics em tempo real
   - Otimização multi-dispositivo

2. **Sistema de Tema/Layout** (Offer Service)
   - 4 temas prontos para produção
   - Separação completa entre visual e estrutural
   - Propriedades CSS customizadas
   - Sistema de migração

3. **Passkeys/WebAuthn** (User Management Service)
   - Conformidade FIDO2
   - Suporte multi-dispositivo
   - Fluxos de re-autenticação
   - Compatibilidade universal de browsers

4. **Carteiras Digitais** (Payment Service)
   - Integração Apple Pay & Google Pay
   - Tokenização WebAuthn
   - Autenticação biométrica

5. **Sistema de Upsells** (Offer Service)
   - Upsells pós-compra
   - Targeting avançado
   - Múltiplos templates
   - Integração com analytics

### 📊 **Padrões da API**

- **Autenticação:** Bearer token (JWT) + isolamento baseado em tenant
- **Endpoints Públicos:** Disponíveis para fluxos de checkout sem autenticação
- **Multi-tenant:** Todos os serviços suportam isolamento via header `x-tenant-id`
- **Paginação:** Padrões consistentes de paginação em endpoints de listagem
- **Tratamento de Erros:** Códigos de status HTTP padronizados e respostas de erro
- **Validação:** DTOs com regras de validação abrangentes

### 🔄 **Comunicação Entre Serviços**

- **Orientado a eventos:** Serviços se comunicam via SQS/EventBridge para operações assíncronas
- **APIs REST:** Comunicação síncrona para operações críticas
- **Service Discovery:** Serviços se comunicam através do proxy nginx (porta 8000)

## Total de Serviços Mapeados: 12 serviços ativos