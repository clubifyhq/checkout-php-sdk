# Webhooks Multi-Tenant

Este SDK suporta múltiplas formas de configurar webhook secrets, incluindo suporte nativo a ambientes multi-tenant.

## Índice

- [Configuração Single-Tenant (Simples)](#configuração-single-tenant-simples)
- [Configuração Multi-Tenant](#configuração-multi-tenant)
  - [Opção 1: Callback Customizado](#opção-1-callback-customizado-máxima-flexibilidade)
  - [Opção 2: Configuração Automática via Model](#opção-2-configuração-automática-via-model-recomendada)
- [Como o SDK Busca o Secret](#como-o-sdk-busca-o-secret-ordem-de-prioridade)
- [Enviando Organization ID nos Webhooks](#enviando-organization-id-nos-webhooks)
- [Troubleshooting](#troubleshooting)
- [Migração de Versões Anteriores](#migração-de-versões-anteriores)
- [Exemplos Completos](#exemplos-completos)

---

## Configuração Single-Tenant (Simples)

Para aplicações com uma única organização, você pode configurar um secret global:

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];
```

```env
# .env
CLUBIFY_CHECKOUT_WEBHOOK_SECRET=whsec_abc123...
```

---

## Configuração Multi-Tenant

### Opção 1: Callback Customizado (Máxima Flexibilidade)

Esta abordagem oferece controle total sobre como o secret é resolvido. Ideal para lógicas complexas ou múltiplos modelos.

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
            // 1. Obter organization_id do header ou payload
            $orgId = $request->header('X-Organization-ID');

            if (!$orgId) {
                // Fallback: buscar do payload JSON
                $payload = json_decode($request->getContent(), true);
                $orgId = $payload['organization_id'] ?? $payload['data']['organization_id'] ?? null;
            }

            if (!$orgId) {
                return null;
            }

            // 2. Buscar organização do seu model
            $org = \App\Models\Organization::find($orgId);

            // 3. Retornar o secret da organização
            return $org?->settings['clubify_checkout_webhook_secret'] ?? null;
        },
    ],
];
```

**Vantagens:**
- Controle total sobre a lógica de resolução
- Suporta qualquer estrutura de dados
- Permite lógica condicional complexa
- Fácil debugging (adicione logs dentro do callback)

**Exemplo com Múltiplos Modelos:**

```php
'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
    $orgId = $request->header('X-Organization-ID');

    // Tentar diferentes modelos
    $org = \App\Models\Organization::find($orgId)
        ?? \App\Models\Tenant::find($orgId)
        ?? \App\Models\Company::find($orgId);

    if (!$org) {
        \Log::warning("Organization não encontrada para webhook", ['org_id' => $orgId]);
        return null;
    }

    // Suportar diferentes campos
    return $org->webhook_secret
        ?? $org->settings['webhook_secret']
        ?? $org->clubify_webhook_secret;
},
```

---

### Opção 2: Configuração Automática via Model (Recomendada)

Se você seguir a convenção do SDK, não precisa escrever código customizado. O SDK buscará automaticamente o secret do model Organization.

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        // Model que contém as organizações (padrão: \App\Models\Organization)
        'organization_model' => env('CLUBIFY_ORGANIZATION_MODEL', '\\App\\Models\\Organization'),

        // Nome do campo onde está o secret (padrão: clubify_checkout_webhook_secret)
        'organization_secret_key' => env('CLUBIFY_WEBHOOK_SECRET_KEY', 'clubify_checkout_webhook_secret'),
    ],
];
```

```env
# .env (opcional - apenas se precisar customizar)
CLUBIFY_ORGANIZATION_MODEL=\App\Models\Organization
CLUBIFY_WEBHOOK_SECRET_KEY=clubify_checkout_webhook_secret
```

**Estrutura Esperada do Model:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array', // JSON field
    ];
}
```

**Estrutura do Banco de Dados:**

```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Exemplo de settings JSON:
-- {
--   "clubify_checkout_webhook_secret": "whsec_abc123...",
--   "other_config": "..."
-- }
```

**Exemplo de Migração Laravel:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'settings')) {
                $table->json('settings')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
```

**Salvando o Secret:**

```php
<?php

use App\Models\Organization;

$organization = Organization::find(1);

// Opção 1: Atualizar settings (recomendado)
$settings = $organization->settings ?? [];
$settings['clubify_checkout_webhook_secret'] = 'whsec_abc123...';
$organization->settings = $settings;
$organization->save();

// Opção 2: Merge com settings existentes
$organization->update([
    'settings' => array_merge($organization->settings ?? [], [
        'clubify_checkout_webhook_secret' => 'whsec_abc123...',
    ]),
]);
```

---

## Como o SDK Busca o Secret (Ordem de Prioridade)

O middleware `ValidateWebhook` segue esta ordem para resolver o webhook secret:

```
1. Callback Customizado (webhook.secret_resolver)
   ↓ (se não configurado ou retornar null)

2. Organization Model Automático
   - Busca organization_id do header X-Organization-ID
   - Fallback: busca organization_id do payload JSON
   - Busca secret em: $organization->settings['clubify_checkout_webhook_secret']
   ↓ (se organization_id não encontrado ou secret não existe)

3. Config Global (webhook.secret) - Fallback Single-Tenant
   ↓ (se não configurado)

4. ERRO: "Webhook secret não configurado"
```

**Código Fonte (Referência):**

```php
// src/Laravel/Middleware/ValidateWebhook.php

private function getWebhookSecret(): string
{
    // 1. Callback customizado
    $callback = $this->sdk->getConfig()->get('webhook.secret_resolver');
    if (is_callable($callback)) {
        $secret = $callback(request());
        if ($secret && is_string($secret)) {
            return $secret;
        }
    }

    // 2. Organization Model
    $organizationId = $this->getOrganizationId();
    if ($organizationId) {
        $secret = $this->getOrganizationSecret($organizationId);
        if ($secret) {
            return $secret;
        }
    }

    // 3. Config global (fallback)
    $webhookSecret = $this->sdk->getConfig()->get('webhook.secret');
    if ($webhookSecret) {
        return $webhookSecret;
    }

    // 4. Erro
    throw new \InvalidArgumentException('Webhook secret não configurado');
}
```

---

## Enviando Organization ID nos Webhooks

### Via Header (Recomendado para Testes Manuais)

Quando você enviar webhooks manualmente ou via ferramentas como Postman/curl:

```bash
curl -X POST https://seu-app.com/api/webhooks/clubify-checkout \
  -H "X-Organization-ID: 123" \
  -H "X-Clubify-Signature: sha256=..." \
  -H "Content-Type: application/json" \
  -d '{"event": "order.paid", "data": {...}}'
```

### Via Payload (Automático em Produção)

O Clubify Checkout **já envia automaticamente** o `organization_id` no payload:

```json
{
  "id": "evt_abc123",
  "event": "order.paid",
  "organization_id": "68e6dac949eac4a77cf59a9f",
  "timestamp": 1697472000,
  "data": {
    "order_id": "order_123",
    "organization_id": 1,
    "amount": 9900,
    "currency": "BRL"
  }
}
```

O SDK busca `organization_id` em múltiplos locais do payload:

```php
// Tenta em ordem:
1. $payload['organization_id']           // Raiz do evento
2. $payload['data']['organization_id']   // Dentro de data
3. $payload['organizationId']            // CamelCase alternativo
4. $payload['data']['organizationId']    // CamelCase em data
```

---

## Troubleshooting

### Erro: "Webhook secret não configurado"

**Causa:** SDK não conseguiu encontrar o secret através de nenhum dos métodos.

**Soluções:**

1. **Verificar se organization_id está sendo enviado:**

```bash
# Testar com curl incluindo header
curl -X POST https://seu-app.local/api/webhooks/clubify-checkout \
  -H "X-Organization-ID: 1" \
  -H "X-Clubify-Signature: sha256=test" \
  -H "Content-Type: application/json" \
  -d '{"event": "test", "data": {"test": true}}'
```

2. **Verificar se Organization existe no banco:**

```php
// php artisan tinker
$org = \App\Models\Organization::find(1);
dd($org); // Deve retornar o model
```

3. **Verificar se secret está salvo corretamente:**

```php
// php artisan tinker
$org = \App\Models\Organization::find(1);
dd($org->settings['clubify_checkout_webhook_secret']); // Deve retornar string
```

4. **Verificar configuração do SDK:**

```php
// php artisan tinker
$sdk = app(\Clubify\Checkout\ClubifyCheckoutSDK::class);

// Verificar callback
dd($sdk->getConfig()->get('webhook.secret_resolver')); // callable ou null

// Verificar model
dd($sdk->getConfig()->get('webhook.organization_model')); // string do namespace

// Verificar secret key
dd($sdk->getConfig()->get('webhook.organization_secret_key')); // nome do campo

// Verificar fallback global
dd($sdk->getConfig()->get('webhook.secret')); // string ou null
```

5. **Adicionar debug temporário no callback:**

```php
// config/clubify-checkout.php
'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
    \Log::info('Webhook Debug', [
        'headers' => $request->headers->all(),
        'payload' => $request->getContent(),
        'org_id_header' => $request->header('X-Organization-ID'),
    ]);

    $orgId = $request->header('X-Organization-ID');
    $org = \App\Models\Organization::find($orgId);

    \Log::info('Organization Found', [
        'org_id' => $orgId,
        'org_exists' => $org !== null,
        'secret_exists' => $org?->settings['clubify_checkout_webhook_secret'] ?? 'NOT FOUND',
    ]);

    return $org?->settings['clubify_checkout_webhook_secret'] ?? null;
},
```

---

### Erro: "Organization ID não encontrado"

**Causa:** Webhook não inclui `organization_id` em nenhum local esperado.

**Soluções:**

1. **Adicionar header manualmente (para testes):**

```php
// Em testes ou requests manuais
$response = $this->postJson('/api/webhooks/clubify-checkout',
    ['event' => 'test'],
    ['X-Organization-ID' => '1']
);
```

2. **Verificar payload JSON do Clubify:**

```php
// Criar endpoint temporário para inspecionar
Route::post('/debug-webhook', function(\Illuminate\Http\Request $request) {
    \Log::info('Webhook Received', [
        'headers' => $request->headers->all(),
        'body' => $request->getContent(),
        'json' => $request->json()->all(),
    ]);
    return response('OK', 200);
});
```

3. **Configurar fallback single-tenant (temporário):**

```php
// config/clubify-checkout.php
'webhook' => [
    'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'), // Fallback
    'secret_resolver' => function($request): ?string {
        // Sua lógica multi-tenant aqui
    },
],
```

---

### Validar Assinatura Está Falhando

**Causa:** Secret incorreto ou assinatura mal formatada.

**Solução - Testar Assinatura Manualmente:**

```php
<?php
// test-webhook-signature.php

$payload = '{"event":"order.paid","data":{"order_id":"123"}}';
$secret = 'whsec_abc123...';

// Gerar assinatura correta
$signature = hash_hmac('sha256', $payload, $secret);

echo "Payload: {$payload}\n";
echo "Secret: {$secret}\n";
echo "Signature: sha256={$signature}\n";

// Testar webhook
system("curl -X POST http://localhost:8000/api/webhooks/clubify-checkout \
  -H 'X-Organization-ID: 1' \
  -H 'X-Clubify-Signature: sha256={$signature}' \
  -H 'Content-Type: application/json' \
  -d '{$payload}'");
```

---

### Verificar Configuração Completa

Use este script para diagnosticar toda a configuração:

```php
<?php
// diagnose-webhook-config.php

require __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Illuminate\Http\Request;

echo "=== DIAGNÓSTICO DE CONFIGURAÇÃO WEBHOOK ===\n\n";

// 1. Carregar SDK
$sdk = app(ClubifyCheckoutSDK::class);

// 2. Verificar configurações
echo "1. CONFIGURAÇÕES\n";
echo "   - Callback: " . (is_callable($sdk->getConfig()->get('webhook.secret_resolver')) ? 'CONFIGURADO' : 'NÃO CONFIGURADO') . "\n";
echo "   - Organization Model: " . ($sdk->getConfig()->get('webhook.organization_model') ?? 'NÃO CONFIGURADO') . "\n";
echo "   - Secret Key Field: " . ($sdk->getConfig()->get('webhook.organization_secret_key') ?? 'NÃO CONFIGURADO') . "\n";
echo "   - Global Secret: " . ($sdk->getConfig()->get('webhook.secret') ? 'CONFIGURADO' : 'NÃO CONFIGURADO') . "\n\n";

// 3. Verificar Model Organization
echo "2. ORGANIZATION MODEL\n";
$orgModel = $sdk->getConfig()->get('webhook.organization_model', '\\App\\Models\\Organization');
if (class_exists($orgModel)) {
    echo "   - Model existe: ✓ {$orgModel}\n";

    $org = $orgModel::first();
    if ($org) {
        echo "   - Registro encontrado: ✓ ID {$org->id}\n";

        $secretKey = $sdk->getConfig()->get('webhook.organization_secret_key', 'clubify_checkout_webhook_secret');
        $secret = $org->settings[$secretKey] ?? null;

        if ($secret) {
            echo "   - Secret configurado: ✓ {$secretKey}\n";
            echo "   - Secret value: " . substr($secret, 0, 10) . "...\n";
        } else {
            echo "   - Secret configurado: ✗ Campo '{$secretKey}' não encontrado\n";
        }
    } else {
        echo "   - Registro encontrado: ✗ Nenhuma organization no banco\n";
    }
} else {
    echo "   - Model existe: ✗ {$orgModel}\n";
}
echo "\n";

// 4. Testar resolução de secret
echo "3. TESTE DE RESOLUÇÃO\n";
try {
    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_ORGANIZATION_ID' => '1',
    ], json_encode(['event' => 'test']));

    $callback = $sdk->getConfig()->get('webhook.secret_resolver');
    if (is_callable($callback)) {
        $secret = $callback($request);
        echo "   - Callback retornou: " . ($secret ? substr($secret, 0, 10) . "..." : 'NULL') . "\n";
    } else {
        echo "   - Callback não configurado\n";
    }
} catch (\Exception $e) {
    echo "   - Erro ao resolver: {$e->getMessage()}\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
```

Execute com:

```bash
php diagnose-webhook-config.php
```

---

## Migração de Versões Anteriores

Se você já usa o SDK e quer migrar para multi-tenant:

### Breaking Changes

**Nenhum!** A implementação é 100% retrocompatível.

A configuração antiga continua funcionando:

```php
// config/clubify-checkout.php (versão antiga)
return [
    'webhook' => [
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'), // ✓ Ainda funciona!
    ],
];
```

### Passos de Migração (Opcional)

Se você quiser adicionar suporte multi-tenant:

#### 1. Adicionar Campo ao Model Organization

```bash
php artisan make:migration add_webhook_secret_to_organizations
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'settings')) {
                $table->json('settings')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
```

```bash
php artisan migrate
```

#### 2. Configurar Model no Config

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        // Manter fallback para compatibilidade
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),

        // Adicionar suporte multi-tenant
        'organization_model' => '\\App\\Models\\Organization',
        'organization_secret_key' => 'clubify_checkout_webhook_secret',
    ],
];
```

#### 3. Migrar Secrets para Organizations

```php
<?php
// migrate-webhook-secrets.php

use App\Models\Organization;

$globalSecret = config('clubify-checkout.webhook.secret');

Organization::chunk(100, function ($organizations) use ($globalSecret) {
    foreach ($organizations as $org) {
        // Gerar secret único para cada org, ou usar global temporariamente
        $secret = 'whsec_' . bin2hex(random_bytes(32));

        $settings = $org->settings ?? [];
        $settings['clubify_checkout_webhook_secret'] = $secret;
        $org->settings = $settings;
        $org->save();

        echo "Organization {$org->id}: {$secret}\n";
    }
});

echo "\nMigração concluída!\n";
echo "IMPORTANTE: Atualize os webhook secrets no Clubify Checkout Dashboard.\n";
```

#### 4. Testar com Webhook de Teste

```bash
curl -X POST http://localhost:8000/api/webhooks/clubify-checkout \
  -H "X-Organization-ID: 1" \
  -H "X-Clubify-Signature: sha256=..." \
  -H "Content-Type: application/json" \
  -d '{"event": "test", "data": {"test": true}}'
```

#### 5. Remover Global Secret (Opcional)

Após confirmar que multi-tenant funciona:

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        // 'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'), // Remover
        'organization_model' => '\\App\\Models\\Organization',
        'organization_secret_key' => 'clubify_checkout_webhook_secret',
    ],
];
```

---

## Exemplos Completos

### Exemplo 1: E-commerce Multi-Tenant Simples

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        'organization_model' => '\\App\\Models\\Store',
        'organization_secret_key' => 'clubify_checkout_webhook_secret',
    ],
];
```

```php
// database/migrations/xxxx_add_webhook_to_stores.php
Schema::table('stores', function (Blueprint $table) {
    $table->json('settings')->nullable();
});
```

```php
// app/Models/Store.php
class Store extends Model
{
    protected $casts = [
        'settings' => 'array',
    ];
}
```

```php
// app/Http/Controllers/StoreController.php
public function setupWebhook(Store $store)
{
    $secret = 'whsec_' . bin2hex(random_bytes(32));

    $settings = $store->settings ?? [];
    $settings['clubify_checkout_webhook_secret'] = $secret;
    $store->settings = $settings;
    $store->save();

    // Registrar no Clubify Checkout
    // ...

    return response()->json([
        'message' => 'Webhook configurado',
        'secret' => $secret,
    ]);
}
```

---

### Exemplo 2: SaaS com Múltiplos Tenants (Callback Customizado)

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
            // Buscar tenant do header ou subdomain
            $tenantId = $request->header('X-Tenant-ID')
                ?? $request->header('X-Organization-ID');

            if (!$tenantId) {
                // Fallback: extrair de subdomain
                $host = $request->getHost();
                if (preg_match('/^(.+)\.app\.com$/', $host, $matches)) {
                    $subdomain = $matches[1];
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    $tenantId = $tenant?->id;
                }
            }

            if (!$tenantId) {
                \Log::warning('Webhook sem tenant_id', [
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                ]);
                return null;
            }

            // Buscar secret do tenant
            $tenant = \App\Models\Tenant::find($tenantId);

            if (!$tenant) {
                \Log::error('Tenant não encontrado para webhook', ['tenant_id' => $tenantId]);
                return null;
            }

            // Suportar diferentes fontes de secret
            $secret = $tenant->clubify_webhook_secret
                ?? $tenant->integrations['clubify']['webhook_secret']
                ?? null;

            if (!$secret) {
                \Log::error('Secret não configurado para tenant', ['tenant_id' => $tenantId]);
            }

            return $secret;
        },
    ],
];
```

---

### Exemplo 3: Marketplace com Organizations e Sellers

```php
// config/clubify-checkout.php
return [
    'webhook' => [
        'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
            $orgId = $request->header('X-Organization-ID');

            // Primeiro: tentar como Organization
            $organization = \App\Models\Organization::find($orgId);
            if ($organization) {
                return $organization->settings['webhook_secret'] ?? null;
            }

            // Fallback: tentar como Seller
            $seller = \App\Models\Seller::find($orgId);
            if ($seller) {
                return $seller->clubify_webhook_secret;
            }

            // Fallback: secret global da plataforma
            return config('clubify-checkout.webhook.secret');
        },
    ],
];
```

---

### Exemplo 4: Teste Automatizado

```php
<?php
// tests/Feature/WebhookMultiTenantTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_validates_with_organization_secret()
    {
        // Arrange
        $organization = Organization::factory()->create([
            'settings' => [
                'clubify_checkout_webhook_secret' => 'test_secret_123',
            ],
        ]);

        $payload = json_encode([
            'event' => 'order.paid',
            'organization_id' => $organization->id,
            'data' => ['order_id' => '123'],
        ]);

        $signature = hash_hmac('sha256', $payload, 'test_secret_123');

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true),
            [
                'X-Organization-ID' => $organization->id,
                'X-Clubify-Signature' => "sha256={$signature}",
            ]
        );

        // Assert
        $response->assertStatus(200);
    }

    public function test_webhook_fails_with_wrong_secret()
    {
        // Arrange
        $organization = Organization::factory()->create([
            'settings' => [
                'clubify_checkout_webhook_secret' => 'correct_secret',
            ],
        ]);

        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, 'wrong_secret');

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true),
            [
                'X-Organization-ID' => $organization->id,
                'X-Clubify-Signature' => "sha256={$signature}",
            ]
        );

        // Assert
        $response->assertStatus(401);
    }
}
```

---

## Suporte

Para problemas relacionados a webhooks multi-tenant:

- **Documentação:** [https://docs.clubify.com/sdk/php/webhooks](https://docs.clubify.com/sdk/php/webhooks)
- **Exemplos:** `examples/webhooks/multi-tenant/`
- **Testes:** `tests/Feature/Webhook/`
- **Issues:** [https://github.com/clubify/checkout-sdk-php/issues](https://github.com/clubify/checkout-sdk-php/issues)

---

**Última Atualização:** 16 de Outubro de 2025
**Versão do SDK:** 1.0.0+
