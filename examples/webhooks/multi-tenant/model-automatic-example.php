<?php

/**
 * Exemplo de Webhook Multi-Tenant usando Model Automático
 *
 * Este exemplo mostra como configurar webhooks multi-tenant usando a resolução
 * automática via Model Organization, sem necessidade de callback customizado.
 *
 * Cenário: E-commerce multi-loja onde cada loja é uma Organization
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "\n=== EXEMPLO: Webhook Multi-Tenant com Model Automático ===\n";

/**
 * PASSO 1: Estrutura do Banco de Dados
 */

echo "\n--- Estrutura do Banco (Simulação) ---\n\n";

echo <<<'SQL'
-- Migration: create_organizations_table
CREATE TABLE organizations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Inserir organizações de exemplo
INSERT INTO organizations (id, name, slug, settings) VALUES
(1, 'Loja Acme', 'acme', '{"clubify_checkout_webhook_secret": "whsec_acme_123"}'),
(2, 'Loja Beta', 'beta', '{"clubify_checkout_webhook_secret": "whsec_beta_456"}'),
(3, 'Loja Gamma', 'gamma', '{"clubify_checkout_webhook_secret": "whsec_gamma_789"}');

SQL;

echo "\n✓ Estrutura criada (simulação)\n";

/**
 * PASSO 2: Model Organization (Laravel)
 */

echo "\n--- Model Organization ---\n\n";

echo <<<'PHP'
<?php
// app/Models/Organization.php

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
        'settings' => 'array', // Cast JSON para array
    ];

    /**
     * Helper para obter webhook secret
     */
    public function getWebhookSecret(): ?string
    {
        return $this->settings['clubify_checkout_webhook_secret'] ?? null;
    }

    /**
     * Helper para definir webhook secret
     */
    public function setWebhookSecret(string $secret): void
    {
        $settings = $this->settings ?? [];
        $settings['clubify_checkout_webhook_secret'] = $secret;
        $this->settings = $settings;
        $this->save();
    }
}

PHP;

echo "\n✓ Model criado\n";

/**
 * PASSO 3: Configuração do SDK
 */

echo "\n--- Configuração (config/clubify-checkout.php) ---\n\n";

echo <<<'PHP'
<?php

return [
    // Outras configurações...

    'webhook' => [
        // Model que contém as organizações
        'organization_model' => env('CLUBIFY_ORGANIZATION_MODEL', '\\App\\Models\\Organization'),

        // Nome do campo onde está o secret
        'organization_secret_key' => env('CLUBIFY_WEBHOOK_SECRET_KEY', 'clubify_checkout_webhook_secret'),

        // Fallback global (opcional)
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];

PHP;

echo "\n✓ Configuração definida\n";

/**
 * PASSO 4: Variáveis de Ambiente (.env)
 */

echo "\n--- Variáveis de Ambiente (.env) ---\n\n";

echo <<<'ENV'
# Multi-tenant (model automático)
CLUBIFY_ORGANIZATION_MODEL=\\App\\Models\\Organization
CLUBIFY_WEBHOOK_SECRET_KEY=clubify_checkout_webhook_secret

# Fallback global (opcional)
CLUBIFY_CHECKOUT_WEBHOOK_SECRET=whsec_global_fallback

ENV;

echo "\n✓ Variáveis configuradas\n";

/**
 * PASSO 5: Como o SDK Resolve o Secret Automaticamente
 */

echo "\n--- Como o SDK Funciona (Internamente) ---\n\n";

echo <<<'TEXT'
1. Webhook recebido em: POST /api/webhooks/clubify-checkout

2. Middleware ValidateWebhook extrai organization_id:
   - Header: X-Organization-ID
   - Payload: organization_id ou data.organization_id

3. SDK busca Organization no banco:
   $org = \App\Models\Organization::find($organizationId);

4. SDK extrai secret do settings:
   $secret = $org->settings['clubify_checkout_webhook_secret'];

5. SDK valida assinatura HMAC usando o secret encontrado

6. Se válido, request prossegue para controller

TEXT;

echo "\n✓ Fluxo automático\n";

/**
 * PASSO 6: Simulação Prática
 */

echo "\n--- Simulação de Webhooks ---\n";

// Simulação de banco de dados
$organizationsDB = [
    1 => [
        'id' => 1,
        'name' => 'Loja Acme',
        'slug' => 'acme',
        'settings' => [
            'clubify_checkout_webhook_secret' => 'whsec_acme_123',
        ],
    ],
    2 => [
        'id' => 2,
        'name' => 'Loja Beta',
        'slug' => 'beta',
        'settings' => [
            'clubify_checkout_webhook_secret' => 'whsec_beta_456',
        ],
    ],
];

// Simular resolução automática do SDK
function resolveWebhookSecret(int $organizationId, array $db): ?string
{
    echo "\n🔍 SDK buscando secret para Organization {$organizationId}...\n";

    if (!isset($db[$organizationId])) {
        echo "❌ Organization não encontrada\n";
        return null;
    }

    $org = $db[$organizationId];
    echo "✓ Organization encontrada: {$org['name']}\n";

    $secret = $org['settings']['clubify_checkout_webhook_secret'] ?? null;

    if (!$secret) {
        echo "❌ Secret não configurado\n";
        return null;
    }

    echo "✓ Secret encontrado: " . substr($secret, 0, 15) . "...\n";

    return $secret;
}

// Teste 1: Webhook da Loja Acme
echo "\n--- Teste 1: Webhook da Loja Acme ---\n";

$payload1 = json_encode([
    'event' => 'order.paid',
    'organization_id' => 1,
    'data' => [
        'order_id' => 'order_acme_123',
        'amount' => 9900,
    ],
]);

$secret1 = resolveWebhookSecret(1, $organizationsDB);
$signature1 = 'sha256=' . hash_hmac('sha256', $payload1, $secret1);

echo "\nPayload: {$payload1}\n";
echo "Signature: {$signature1}\n";

// Validar
$expectedSignature1 = 'sha256=' . hash_hmac('sha256', $payload1, $secret1);
echo hash_equals($expectedSignature1, $signature1) ? "✅ Assinatura válida!\n" : "❌ Assinatura inválida!\n";

// Teste 2: Webhook da Loja Beta
echo "\n--- Teste 2: Webhook da Loja Beta ---\n";

$payload2 = json_encode([
    'event' => 'payment.approved',
    'organization_id' => 2,
    'data' => [
        'payment_id' => 'pay_beta_456',
        'amount' => 14900,
    ],
]);

$secret2 = resolveWebhookSecret(2, $organizationsDB);
$signature2 = 'sha256=' . hash_hmac('sha256', $payload2, $secret2);

echo "\nPayload: {$payload2}\n";
echo "Signature: {$signature2}\n";

$expectedSignature2 = 'sha256=' . hash_hmac('sha256', $payload2, $secret2);
echo hash_equals($expectedSignature2, $signature2) ? "✅ Assinatura válida!\n" : "❌ Assinatura inválida!\n";

/**
 * PASSO 7: Gerenciar Webhook Secrets
 */

echo "\n--- Gerenciamento de Secrets ---\n\n";

echo <<<'PHP'
<?php
// Gerar novo secret para organização

use App\Models\Organization;

// Gerar secret seguro
$secret = 'whsec_' . bin2hex(random_bytes(32));

// Salvar na organização
$org = Organization::find(1);
$org->setWebhookSecret($secret);

echo "Secret gerado: {$secret}\n";

// Ou manualmente:
$org = Organization::find(1);
$settings = $org->settings ?? [];
$settings['clubify_checkout_webhook_secret'] = $secret;
$org->settings = $settings;
$org->save();

PHP;

/**
 * PASSO 8: Endpoint Laravel Completo
 */

echo "\n--- Controller Laravel Completo ---\n\n";

echo <<<'PHP'
<?php
// routes/api.php

use App\Http\Controllers\WebhookController;

Route::post('/webhooks/clubify-checkout', [WebhookController::class, 'handle'])
    ->middleware('clubify.webhook'); // Middleware já valida assinatura

// app/Http/Controllers/WebhookController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Organization;
use App\Events\OrderPaid;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Middleware ValidateWebhook já validou a assinatura usando o secret correto
        // da Organization identificada pelo organization_id

        $event = $request->json('event');
        $data = $request->json('data');

        // Organization ID pode vir do header ou payload
        $orgId = $request->header('X-Organization-ID')
            ?? $request->json('organization_id')
            ?? $request->json('data.organization_id');

        Log::info('Webhook recebido', [
            'event' => $event,
            'organization_id' => $orgId,
            'data' => $data,
        ]);

        // Buscar organização
        $organization = Organization::find($orgId);

        if (!$organization) {
            Log::error('Organization não encontrada', ['org_id' => $orgId]);
            return response('Organization not found', 404);
        }

        // Processar evento para a organização específica
        $this->processEventForOrganization($event, $data, $organization);

        return response('OK', 200);
    }

    private function processEventForOrganization(string $event, array $data, Organization $organization): void
    {
        match($event) {
            'order.paid' => $this->handleOrderPaid($data, $organization),
            'order.created' => $this->handleOrderCreated($data, $organization),
            'payment.approved' => $this->handlePaymentApproved($data, $organization),
            default => Log::info("Evento não tratado: {$event}", ['org' => $organization->name]),
        };
    }

    private function handleOrderPaid(array $data, Organization $organization): void
    {
        // Lógica específica para order.paid da organização
        $order = $organization->orders()
            ->where('external_id', $data['order_id'])
            ->first();

        if ($order) {
            $order->markAsPaid();
            event(new OrderPaid($order, $organization));

            Log::info('Pedido marcado como pago', [
                'order_id' => $order->id,
                'organization' => $organization->name,
            ]);
        }
    }

    private function handleOrderCreated(array $data, Organization $organization): void
    {
        // Criar pedido na organização específica
        $order = $organization->orders()->create([
            'external_id' => $data['order_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'BRL',
            'status' => 'pending',
        ]);

        Log::info('Pedido criado', [
            'order_id' => $order->id,
            'organization' => $organization->name,
        ]);
    }

    private function handlePaymentApproved(array $data, Organization $organization): void
    {
        // Processar pagamento aprovado
        Log::info('Pagamento aprovado', [
            'payment_id' => $data['payment_id'],
            'organization' => $organization->name,
        ]);
    }
}

PHP;

/**
 * PASSO 9: Testes Automatizados
 */

echo "\n--- Testes Automatizados (PHPUnit) ---\n\n";

echo <<<'PHP'
<?php
// tests/Feature/WebhookMultiTenantTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_validates_with_correct_organization_secret()
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

    public function test_webhook_fails_with_wrong_organization_secret()
    {
        // Arrange
        $organization = Organization::factory()->create([
            'settings' => [
                'clubify_checkout_webhook_secret' => 'correct_secret',
            ],
        ]);

        $payload = json_encode(['event' => 'test']);

        // Sign with wrong secret
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

    public function test_webhook_fails_when_organization_not_found()
    {
        // Arrange
        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, 'any_secret');

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true),
            [
                'X-Organization-ID' => 999, // Non-existent
                'X-Clubify-Signature' => "sha256={$signature}",
            ]
        );

        // Assert
        $response->assertStatus(401);
    }
}

PHP;

echo "\n✅ Exemplo concluído!\n\n";

echo "📝 Resumo:\n";
echo "  ✓ Model Organization configurado\n";
echo "  ✓ SDK busca secret automaticamente via organization_id\n";
echo "  ✓ Nenhum código customizado necessário\n";
echo "  ✓ 100% retrocompatível com single-tenant\n\n";

echo "📚 Documentação: docs/webhooks/multi-tenant-setup.md\n";
echo "🔧 Mais exemplos: examples/webhooks/\n";
