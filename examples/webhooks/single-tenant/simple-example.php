<?php

/**
 * Exemplo de Webhook Single-Tenant (Simples)
 *
 * Este exemplo mostra a configuração tradicional de webhooks para aplicações
 * com uma única organização (single-tenant).
 *
 * Cenário: Aplicação com apenas uma organização/loja
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "\n=== EXEMPLO: Webhook Single-Tenant (Simples) ===\n";

/**
 * PASSO 1: Configuração do SDK
 */

echo "\n--- Configuração (config/clubify-checkout.php) ---\n\n";

echo <<<'PHP'
<?php

return [
    // Outras configurações...

    'webhook' => [
        // Secret global único para toda a aplicação
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];

PHP;

echo "\n✓ Configuração definida\n";

/**
 * PASSO 2: Variáveis de Ambiente
 */

echo "\n--- Variáveis de Ambiente (.env) ---\n\n";

echo <<<'ENV'
# Single-tenant (secret global)
CLUBIFY_CHECKOUT_WEBHOOK_SECRET=whsec_seu_secret_super_seguro_aqui_32_chars

ENV;

echo "\n✓ Variáveis configuradas\n";

/**
 * PASSO 3: Como Funciona
 */

echo "\n--- Como o SDK Funciona ---\n\n";

echo <<<'TEXT'
1. Webhook recebido em: POST /api/webhooks/clubify-checkout

2. Middleware ValidateWebhook busca secret da config:
   $secret = config('clubify-checkout.webhook.secret');

3. SDK valida assinatura HMAC usando o secret global

4. Se válido, request prossegue para controller

TEXT;

echo "\n✓ Fluxo automático\n";

/**
 * PASSO 4: Simulação Prática
 */

echo "\n--- Simulação de Webhook ---\n";

// Configuração
$webhookSecret = 'whsec_seu_secret_super_seguro_aqui_32_chars';

// Payload do webhook
$payload = json_encode([
    'event' => 'order.paid',
    'data' => [
        'order_id' => 'order_123',
        'amount' => 9900,
        'currency' => 'BRL',
        'customer' => [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ],
    ],
]);

echo "\nPayload: {$payload}\n";

// Gerar assinatura (como o Clubify faz)
$signature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

echo "Signature: {$signature}\n";

// Validar assinatura (como o SDK faz)
$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);
$isValid = hash_equals($expectedSignature, $signature);

echo $isValid ? "\n✅ Assinatura válida!\n" : "\n❌ Assinatura inválida!\n";

/**
 * PASSO 5: Endpoint Laravel
 */

echo "\n--- Controller Laravel ---\n\n";

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
use App\Events\OrderPaid;
use App\Models\Order;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Middleware ValidateWebhook já validou a assinatura

        $event = $request->json('event');
        $data = $request->json('data');

        Log::info('Webhook recebido', [
            'event' => $event,
            'data' => $data,
        ]);

        // Processar evento
        match($event) {
            'order.paid' => $this->handleOrderPaid($data),
            'order.created' => $this->handleOrderCreated($data),
            'payment.approved' => $this->handlePaymentApproved($data),
            default => Log::info("Evento não tratado: {$event}"),
        };

        return response('OK', 200);
    }

    private function handleOrderPaid(array $data): void
    {
        $order = Order::where('external_id', $data['order_id'])->first();

        if ($order) {
            $order->update(['status' => 'paid']);
            event(new OrderPaid($order));

            Log::info('Pedido marcado como pago', ['order_id' => $order->id]);
        }
    }

    private function handleOrderCreated(array $data): void
    {
        $order = Order::create([
            'external_id' => $data['order_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'BRL',
            'status' => 'pending',
        ]);

        Log::info('Pedido criado', ['order_id' => $order->id]);
    }

    private function handlePaymentApproved(array $data): void
    {
        Log::info('Pagamento aprovado', ['payment_id' => $data['payment_id']]);
    }
}

PHP;

/**
 * PASSO 6: Testando com curl
 */

echo "\n--- Teste Manual (curl) ---\n\n";

echo <<<'BASH'
#!/bin/bash

# Gerar assinatura
PAYLOAD='{"event":"order.paid","data":{"order_id":"123","amount":9900}}'
SECRET='whsec_seu_secret_super_seguro_aqui_32_chars'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

# Enviar webhook
curl -X POST http://localhost:8000/api/webhooks/clubify-checkout \
  -H "Content-Type: application/json" \
  -H "X-Clubify-Signature: sha256=$SIGNATURE" \
  -d "$PAYLOAD"

BASH;

/**
 * PASSO 7: Testes Automatizados
 */

echo "\n--- Testes Automatizados (PHPUnit) ---\n\n";

echo <<<'PHP'
<?php
// tests/Feature/WebhookTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_validates_with_correct_signature()
    {
        // Arrange
        config(['clubify-checkout.webhook.secret' => 'test_secret']);

        $payload = json_encode([
            'event' => 'order.paid',
            'data' => ['order_id' => '123'],
        ]);

        $signature = hash_hmac('sha256', $payload, 'test_secret');

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true),
            ['X-Clubify-Signature' => "sha256={$signature}"]
        );

        // Assert
        $response->assertStatus(200);
    }

    public function test_webhook_fails_with_invalid_signature()
    {
        // Arrange
        config(['clubify-checkout.webhook.secret' => 'test_secret']);

        $payload = json_encode(['event' => 'test']);

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true),
            ['X-Clubify-Signature' => 'sha256=invalid_signature']
        );

        // Assert
        $response->assertStatus(401);
    }

    public function test_webhook_fails_without_signature()
    {
        // Arrange
        $payload = json_encode(['event' => 'test']);

        // Act
        $response = $this->postJson('/api/webhooks/clubify-checkout',
            json_decode($payload, true)
        );

        // Assert
        $response->assertStatus(401);
    }
}

PHP;

/**
 * PASSO 8: Gerar Secret Seguro
 */

echo "\n--- Gerando Secret Seguro ---\n\n";

echo <<<'PHP'
<?php

// Gerar secret seguro de 32 bytes (64 caracteres hex)
$secret = 'whsec_' . bin2hex(random_bytes(32));

echo "Secret gerado: {$secret}\n";
echo "Adicione ao .env: CLUBIFY_CHECKOUT_WEBHOOK_SECRET={$secret}\n";

PHP;

// Gerar secret real
$generatedSecret = 'whsec_' . bin2hex(random_bytes(32));
echo "\n🔐 Secret gerado para você:\n";
echo "   {$generatedSecret}\n\n";
echo "   Adicione ao .env:\n";
echo "   CLUBIFY_CHECKOUT_WEBHOOK_SECRET={$generatedSecret}\n";

/**
 * PASSO 9: Debugging
 */

echo "\n--- Debugging de Webhooks ---\n\n";

echo <<<'PHP'
<?php
// Endpoint temporário para debug

Route::post('/debug-webhook', function(\Illuminate\Http\Request $request) {
    \Log::info('Webhook Debug', [
        'headers' => $request->headers->all(),
        'body' => $request->getContent(),
        'json' => $request->json()->all(),
    ]);

    return response('Logged', 200);
});

// Ou usar webhook.site para inspecionar
// https://webhook.site/

PHP;

echo "\n✅ Exemplo concluído!\n\n";

echo "📝 Resumo:\n";
echo "  ✓ Configuração simples com secret global\n";
echo "  ✓ Middleware valida assinatura automaticamente\n";
echo "  ✓ Ideal para aplicações single-tenant\n";
echo "  ✓ Fácil de testar e debugar\n\n";

echo "🔄 Migração para Multi-Tenant:\n";
echo "  Se você precisar migrar para multi-tenant no futuro,\n";
echo "  é 100% retrocompatível. Veja: docs/webhooks/multi-tenant-setup.md\n\n";

echo "📚 Mais informações:\n";
echo "  - docs/WEBHOOK_EVENTS_GUIDE.md\n";
echo "  - docs/WEBHOOK_TESTING.md\n";
echo "  - examples/webhooks/multi-tenant/\n";
