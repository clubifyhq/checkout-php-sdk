# Clubify Checkout - Sequence Diagrams and Flow Documentation

## Overview

This document provides comprehensive sequence diagrams and flow documentation for the Clubify checkout system, focusing on the complete setup process from organization creation to ready-to-use checkout flows.

## 1. Super-Admin Initialization Sequence

### Key Components
- **SDK Client**: PHP SDK instance
- **Authentication Service**: Token validation and role management
- **Organization Service**: Multi-tenant organization management
- **Tenant Service**: Individual tenant configuration
- **Credential Service**: API key and access token management

### Sequence Diagram: Super-Admin Initialization

```mermaid
sequenceDiagram
    participant Client as SDK Client
    participant Auth as Authentication Service
    participant SDK as ClubifyCheckoutSDK
    participant OrgSvc as Organization Service
    participant Cache as Cache Manager
    participant Logger as Logger

    Note over Client, Logger: Phase 1: SDK Initialization as Super Admin

    Client->>SDK: new ClubifyCheckoutSDK()
    SDK->>Logger: Initialize logging
    SDK->>Cache: Initialize cache manager

    Client->>SDK: initializeAsSuperAdmin(credentials)
    SDK->>Auth: validateSuperAdminCredentials(credentials)

    alt API Key Authentication
        Auth->>Auth: validateApiKey(api_key)
        Auth-->>SDK: {success: true, role: 'super_admin'}
    else Token Authentication
        Auth->>Auth: validateTokens(access_token, refresh_token)
        Auth-->>SDK: {success: true, role: 'super_admin', tokens: {...}}
    else Email/Password Fallback
        Auth->>Auth: authenticateUser(email, password)
        Auth->>Auth: generateTokens(user)
        Auth-->>SDK: {success: true, role: 'super_admin', tokens: {...}}
    end

    SDK->>Cache: storeCredentials(credentials)
    SDK->>OrgSvc: initializeSuperAdminContext()
    OrgSvc-->>SDK: {success: true, mode: 'super_admin'}

    SDK-->>Client: {success: true, mode: 'super_admin', authenticated: true}

    Note over Client, Logger: Super Admin Mode Active - Can manage organizations
```

## 2. Complete Tenant Creation and Configuration Flow

### Key Services
- **Organization Module**: Handles organization CRUD operations
- **Tenant Service**: Individual tenant management
- **Admin Service**: Admin user creation and management
- **API Key Service**: Credential generation and management
- **Domain Service**: Custom domain and SSL configuration

### Sequence Diagram: Complete Tenant Setup

```mermaid
sequenceDiagram
    participant Client as SDK Client
    participant SDK as ClubifyCheckoutSDK
    participant OrgMod as Organization Module
    participant TenantSvc as Tenant Service
    participant AdminSvc as Admin Service
    participant ApiKeySvc as API Key Service
    participant DomainSvc as Domain Service
    participant ConflictRes as Conflict Resolver
    participant UserSvc as User Service

    Note over Client, UserSvc: Phase 2: Complete Organization Setup

    Client->>SDK: createOrganization(organizationData)
    SDK->>OrgMod: isInitialized()
    OrgMod-->>SDK: true

    Note over SDK, ConflictRes: Step 1: Check for existing organization

    SDK->>TenantSvc: findTenantByDomain(custom_domain)
    TenantSvc-->>SDK: existingTenant || null

    alt Tenant Exists
        SDK->>ConflictRes: handleExistingTenant(tenant)
        ConflictRes->>TenantSvc: registerExistingTenant(tenantId, tenantData)
        TenantSvc-->>ConflictRes: {success: true, has_api_key: boolean}
        ConflictRes-->>SDK: {tenant: existingTenant, action: 'reused'}
    else Create New Tenant
        Note over SDK, UserSvc: Step 2: Create new organization and tenant

        SDK->>OrgMod: createOrganization(data)
        OrgMod->>TenantSvc: createTenant(organizationData)
        TenantSvc-->>OrgMod: {tenant_id, organization_id, subdomain}
        OrgMod-->>SDK: {organization: {...}, tenant: {...}}

        Note over SDK, UserSvc: Step 3: Setup admin user and credentials

        SDK->>UserSvc: checkEmailAvailability(admin_email)
        UserSvc-->>SDK: {exists: boolean, resource?: user}

        alt User Exists
            SDK->>ApiKeySvc: getTenantCredentials(tenant_id)
            ApiKeySvc-->>SDK: {api_key?, access_token?}

            alt No API Key
                SDK->>ApiKeySvc: createTenantApiKey(tenant_id, userData)
                ApiKeySvc-->>SDK: {api_key: {...}, success: true}
            end
        else Create New User
            SDK->>AdminSvc: provisionTenantCredentials(tenant_id, userData)
            AdminSvc->>UserSvc: createAdminUser(userData)
            UserSvc-->>AdminSvc: {user: {...}, password: temp_password}
            AdminSvc->>ApiKeySvc: generateApiKey(user_id, tenant_id)
            ApiKeySvc-->>AdminSvc: {api_key: key, refresh_token: token}
            AdminSvc-->>SDK: {user: {...}, api_key: {...}, credentials: {...}}
        end

        Note over SDK, UserSvc: Step 4: Domain and SSL configuration

        SDK->>DomainSvc: provisionDomain(custom_domain, tenant_id)
        DomainSvc->>DomainSvc: configureSSL(domain)
        DomainSvc->>DomainSvc: setupDNS(domain, tenant_id)
        DomainSvc-->>SDK: {domain: configured, ssl: active}
    end

    Note over SDK, UserSvc: Step 5: Context switching setup

    SDK->>TenantSvc: registerTenantForSwitching(tenant_id, credentials)
    TenantSvc-->>SDK: {context_registered: true}

    SDK-->>Client: {organization: {...}, tenant: {...}, admin: {...}, credentials: {...}}

    Note over Client, UserSvc: Organization ready for tenant operations
```

## 3. Product and Offer Creation Workflow

### Key Services
- **Products Module**: Product CRUD and management
- **Product Service**: Individual product operations
- **Offer Service**: Offer creation and configuration
- **Flow Service**: Sales flow configuration
- **Theme Service**: Visual customization
- **Layout Service**: Page layout management

### Sequence Diagram: Product and Offer Setup

```mermaid
sequenceDiagram
    participant Client as SDK Client
    participant SDK as ClubifyCheckoutSDK
    participant ProdMod as Products Module
    participant ProdSvc as Product Service
    participant OfferSvc as Offer Service
    participant FlowSvc as Flow Service
    participant ThemeSvc as Theme Service
    participant LayoutSvc as Layout Service
    participant ConflictRes as Conflict Resolver

    Note over Client, ConflictRes: Phase 3: Product and Offer Configuration

    Client->>SDK: switchToTenant(tenant_id)
    SDK->>SDK: updateContext(tenant_id)
    SDK-->>Client: {context: 'tenant', tenant_id: ...}

    Note over Client, ConflictRes: Step 1: Product Creation with Conflict Handling

    Client->>SDK: products().create(productData)
    SDK->>ProdMod: getProductService()
    ProdMod-->>SDK: ProductService instance

    SDK->>ProdSvc: findByName(productData.name)
    ProdSvc-->>SDK: existingProduct || null

    alt Product Exists
        SDK->>ConflictRes: handleExistingProduct(product)
        ConflictRes-->>SDK: {product: existing, action: 'reused'}
    else Create New Product
        SDK->>ProdSvc: create(productData)
        ProdSvc->>ProdSvc: validateProductData(data)
        ProdSvc->>ProdSvc: setDefaultPricing(data)
        ProdSvc-->>SDK: {product: {...}, id: product_id}
    end

    Note over Client, ConflictRes: Step 2: Offer Creation with Product Association

    Client->>SDK: products().offers().create(offerData)
    SDK->>OfferSvc: findByName(offerData.name)
    OfferSvc-->>SDK: existingOffer || null

    alt Offer Exists
        SDK->>ConflictRes: handleExistingOffer(offer)
        ConflictRes-->>SDK: {offer: existing, action: 'reused'}
    else Create New Offer
        SDK->>OfferSvc: create(offerData)
        OfferSvc->>OfferSvc: validateOfferStructure(data)
        OfferSvc->>OfferSvc: associateProducts(data.products)
        OfferSvc->>OfferSvc: configurePaymentMethods(data.payments)
        OfferSvc-->>SDK: {offer: {...}, id: offer_id, url: checkout_url}
    end

    Note over Client, ConflictRes: Step 3: Sales Flow Configuration

    Client->>SDK: products().flows().create(flowData)
    SDK->>FlowSvc: create(flowData)
    FlowSvc->>FlowSvc: setupLandingPage(data)
    FlowSvc->>FlowSvc: configureCheckoutFlow(data)
    FlowSvc->>FlowSvc: setupThankYouPage(data)
    FlowSvc-->>SDK: {flow: {...}, pages: [...]}

    Note over Client, ConflictRes: Step 4: Theme and Layout Customization

    Client->>SDK: products().themes().create(themeData)
    SDK->>ThemeSvc: create(themeData)
    ThemeSvc->>ThemeSvc: generateCustomCSS(data)
    ThemeSvc->>ThemeSvc: configureVisualElements(data)
    ThemeSvc-->>SDK: {theme: {...}, css: customCSS}

    Client->>SDK: products().layouts().create(layoutData)
    SDK->>LayoutSvc: create(layoutData)
    LayoutSvc->>LayoutSvc: configurePageLayout(data)
    LayoutSvc->>LayoutSvc: setResponsiveBreakpoints(data)
    LayoutSvc-->>SDK: {layout: {...}, config: {...}}

    Note over Client, ConflictRes: Step 5: Apply configurations to offer

    SDK->>OfferSvc: updateConfiguration(offer_id, {theme, layout, flow})
    OfferSvc->>OfferSvc: linkThemeToOffer(theme_id)
    OfferSvc->>OfferSvc: linkLayoutToOffer(layout_id)
    OfferSvc->>OfferSvc: linkFlowToOffer(flow_id)
    OfferSvc-->>SDK: {offer: updated, ready: true}

    SDK-->>Client: {product: {...}, offer: {...}, flow: {...}, theme: {...}, layout: {...}}

    Note over Client, ConflictRes: Product and Offer ready for checkout
```

## 4. API Method Dependencies and Timing

### Critical Path Dependencies

#### Initialization Dependencies
```mermaid
graph TD
    A[SDK Initialize] --> B[Auth Validation]
    B --> C[Super Admin Mode]
    C --> D[Organization Module Ready]
    D --> E[Can Create Organizations]
```

#### Organization Setup Dependencies
```mermaid
graph TD
    A[Super Admin Active] --> B[Create/Find Organization]
    B --> C[Create/Register Tenant]
    C --> D[Check Admin User]
    D --> E{User Exists?}
    E -->|No| F[Create Admin User]
    E -->|Yes| G[Check API Key]
    F --> H[Generate API Key]
    G --> I{API Key Exists?}
    I -->|No| J[Create API Key]
    I -->|Yes| K[Register Tenant Context]
    H --> K
    J --> K
    K --> L[Setup Domain & SSL]
    L --> M[Tenant Ready]
```

#### Product/Offer Dependencies
```mermaid
graph TD
    A[Switch to Tenant Context] --> B[Products Module Ready]
    B --> C[Create/Find Product]
    C --> D[Create/Find Offer]
    D --> E[Create Sales Flow]
    E --> F[Create Theme]
    F --> G[Create Layout]
    G --> H[Link All Components]
    H --> I[Offer Ready for Checkout]
```

### Timing Requirements

#### Synchronous Operations (Must Wait)
1. **Authentication**: All subsequent operations depend on valid credentials
2. **Context Switching**: Must complete before tenant-specific operations
3. **Product Creation**: Must exist before offer creation
4. **Offer Configuration**: Must be complete before checkout URL is functional

#### Asynchronous Operations (Can be Parallel)
1. **Domain SSL Setup**: Can happen in background after domain creation
2. **Theme CSS Generation**: Can be processed while other components are created
3. **Webhook Configuration**: Can be setup independently of main flow

### Method Call Sequence for Complete Setup

#### 1. Super Admin Initialization
```php
$sdk = new ClubifyCheckoutSDK();
$result = $sdk->initializeAsSuperAdmin($credentials);
// Dependencies: None
// Timing: Must complete first (3-5 seconds)
```

#### 2. Organization Creation
```php
$organization = $sdk->createOrganization($organizationData);
// Dependencies: Super admin mode active
// Timing: 5-15 seconds (includes user creation, API key generation)
```

#### 3. Context Switch
```php
$sdk->switchToTenant($tenantId);
// Dependencies: Tenant registered with API key
// Timing: Immediate (< 1 second)
```

#### 4. Product Creation
```php
$product = $sdk->products()->create($productData);
// Dependencies: Valid tenant context
// Timing: 2-5 seconds
```

#### 5. Offer Creation
```php
$offer = $sdk->products()->offers()->create($offerData);
// Dependencies: Product exists
// Timing: 3-8 seconds (includes configuration)
```

#### 6. Flow, Theme, Layout Setup (Can be parallel)
```php
// These can run simultaneously
$flow = $sdk->products()->flows()->create($flowData);    // 2-4 seconds
$theme = $sdk->products()->themes()->create($themeData); // 1-3 seconds
$layout = $sdk->products()->layouts()->create($layoutData); // 1-2 seconds
```

#### 7. Final Configuration
```php
$finalOffer = $sdk->products()->offers()->updateConfiguration($offerId, $config);
// Dependencies: All components created
// Timing: 1-3 seconds
```

## 5. Error Handling and Conflict Resolution

### Common Conflict Scenarios

#### Organization Conflicts
- **Subdomain already exists**: Reuse existing tenant or generate alternative
- **Email already registered**: Use existing user or create with different email
- **Domain already configured**: Reuse existing configuration

#### Product/Offer Conflicts
- **Product name collision**: Append suffix or reuse existing
- **Offer URL collision**: Generate unique URL or reuse existing
- **Theme name collision**: Create variant or reuse existing

### Conflict Resolution Sequence

```mermaid
sequenceDiagram
    participant SDK as ClubifyCheckoutSDK
    participant ConflictSvc as Conflict Resolver Service
    participant Resource as Resource Service
    participant Logger as Logger

    SDK->>Resource: createResource(data)
    Resource-->>SDK: ConflictException(409)

    SDK->>ConflictSvc: resolve(exception)
    ConflictSvc->>ConflictSvc: analyzeConflict(exception)

    alt Auto-Resolvable
        ConflictSvc->>Resource: findExisting(identifier)
        Resource-->>ConflictSvc: existingResource
        ConflictSvc->>ConflictSvc: validateCompatibility(existing, new)
        ConflictSvc-->>SDK: {action: 'reuse', resource: existing}
    else Manual Resolution Required
        ConflictSvc->>Logger: logConflict(details)
        ConflictSvc-->>SDK: ConflictException(requiresManualResolution)
    end
```

## Summary

The Clubify checkout system follows a structured initialization and configuration flow:

1. **Super Admin Initialization** (3-5s): Authenticate and establish super admin context
2. **Organization Setup** (10-20s): Create/find organization, setup tenant, provision credentials
3. **Context Switching** (<1s): Switch to tenant context for operations
4. **Product Creation** (2-5s): Create or reuse products with validation
5. **Offer Configuration** (5-15s): Create offers with products, flows, themes, and layouts
6. **Final Integration** (1-3s): Link all components and generate checkout URLs

The system is designed with **resilience** and **idempotency** in mind, allowing repeated executions without creating duplicate resources. Conflict resolution handles common scenarios automatically, while providing detailed logging for manual intervention when required.

Total setup time for a complete organization with checkout ready: **20-45 seconds** depending on complexity and conflicts.