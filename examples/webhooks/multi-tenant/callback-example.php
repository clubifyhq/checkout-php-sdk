<?php

/**
 * Exemplo de Webhook Multi-Tenant usando Callback Customizado
 *
 * Este exemplo mostra como configurar webhooks multi-tenant usando um callback
 * customizado que oferece máxima flexibilidade para resolver o secret.
 *
 * Cenário: SaaS com múltiplos tenants, cada um com seu próprio webhook secret
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Illuminate\Http\Request;

/**
 * PASSO 1: Configurar SDK com callback customizado
 *
 * O callback será chamado automaticamente pelo middleware ValidateWebhook
 * para cada webhook recebido.
 */

// Simulação de configuração (em produção isso estaria em config/clubify-checkout.php)
$config = [
    'api_key' => getenv('CLUBIFY_API_KEY'),
    'tenant_id' => getenv('CLUBIFY_TENANT_ID'),
    'environment' => 'staging',

    'webhook' => [
        // Callback customizado para resolver secret
        'secret_resolver' => function(Request $request): ?string {
            echo "\n🔍 Resolvendo webhook secret...\n";

            // 1. Obter organization_id do header (prioridade)
            $orgId = $request->header('X-Organization-ID');

            if (!$orgId) {
                // 2. Fallback: buscar do payload JSON
                $payload = json_decode($request->getContent(), true);
                $orgId = $payload['organization_id']
                    ?? $payload['data']['organization_id']
                    ?? null;
            }

            if (!$orgId) {
                echo "❌ Organization ID não encontrado\n";
                return null;
            }

            echo "✓ Organization ID: {$orgId}\n";

            // 3. Buscar organização do banco (simulado aqui com array)
            $organizations = [
                '1' => [
                    'id' => 1,
                    'name' => 'Acme Corp',
                    'settings' => [
                        'clubify_checkout_webhook_secret' => 'whsec_acme_corp_secret_123',
                    ],
                ],
                '2' => [
                    'id' => 2,
                    'name' => 'Beta Industries',
                    'settings' => [
                        'clubify_checkout_webhook_secret' => 'whsec_beta_industries_secret_456',
                    ],
                ],
            ];

            $org = $organizations[$orgId] ?? null;

            if (!$org) {
                echo "❌ Organization não encontrada: {$orgId}\n";
                return null;
            }

            echo "✓ Organization: {$org['name']}\n";

            // 4. Retornar secret
            $secret = $org['settings']['clubify_checkout_webhook_secret'] ?? null;

            if (!$secret) {
                echo "❌ Secret não configurado para organization {$orgId}\n";
                return null;
            }

            echo "✓ Secret encontrado: " . substr($secret, 0, 15) . "...\n";

            return $secret;
        },

        // Fallback global (opcional, para compatibilidade)
        'secret' => getenv('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];

/**
 * PASSO 2: Simular recebimento de webhook
 */

echo "\n=== EXEMPLO: Webhook Multi-Tenant com Callback ===\n";

// Simular request de webhook da Organization 1
echo "\n--- Teste 1: Webhook da Acme Corp (Organization 1) ---\n";

$payload1 = json_encode([
    'event' => 'order.paid',
    'organization_id' => '1',
    'data' => [
        'order_id' => 'order_123',
        'amount' => 9900,
        'currency' => 'BRL',
    ],
]);

$secret1 = 'whsec_acme_corp_secret_123';
$signature1 = 'sha256=' . hash_hmac('sha256', $payload1, $secret1);

echo "Payload: {$payload1}\n";
echo "Signature: {$signature1}\n";

// Simular Request
$request1 = Request::create('/webhook', 'POST', [], [], [], [
    'HTTP_X_ORGANIZATION_ID' => '1',
    'HTTP_X_CLUBIFY_SIGNATURE' => $signature1,
    'CONTENT_TYPE' => 'application/json',
], $payload1);

// Executar callback manualmente para demonstração
$callback = $config['webhook']['secret_resolver'];
$resolvedSecret1 = $callback($request1);

echo "\n✅ Secret resolvido com sucesso!\n";
echo "Secret usado para validação: " . substr($resolvedSecret1, 0, 15) . "...\n";

// Validar assinatura
$expectedSignature1 = 'sha256=' . hash_hmac('sha256', $payload1, $resolvedSecret1);
$isValid1 = hash_equals($expectedSignature1, $signature1);

echo $isValid1 ? "✅ Assinatura válida!\n" : "❌ Assinatura inválida!\n";

// ---

// Simular request de webhook da Organization 2
echo "\n--- Teste 2: Webhook da Beta Industries (Organization 2) ---\n";

$payload2 = json_encode([
    'event' => 'payment.approved',
    'organization_id' => '2',
    'data' => [
        'payment_id' => 'pay_456',
        'amount' => 14900,
        'currency' => 'BRL',
    ],
]);

$secret2 = 'whsec_beta_industries_secret_456';
$signature2 = 'sha256=' . hash_hmac('sha256', $payload2, $secret2);

echo "Payload: {$payload2}\n";
echo "Signature: {$signature2}\n";

$request2 = Request::create('/webhook', 'POST', [], [], [], [
    'HTTP_X_ORGANIZATION_ID' => '2',
    'HTTP_X_CLUBIFY_SIGNATURE' => $signature2,
    'CONTENT_TYPE' => 'application/json',
], $payload2);

$resolvedSecret2 = $callback($request2);

echo "\n✅ Secret resolvido com sucesso!\n";
echo "Secret usado para validação: " . substr($resolvedSecret2, 0, 15) . "...\n";

$expectedSignature2 = 'sha256=' . hash_hmac('sha256', $payload2, $resolvedSecret2);
$isValid2 = hash_equals($expectedSignature2, $signature2);

echo $isValid2 ? "✅ Assinatura válida!\n" : "❌ Assinatura inválida!\n";

// ---

// Simular request sem organization_id (deve falhar)
echo "\n--- Teste 3: Webhook sem Organization ID (deve falhar) ---\n";

$payload3 = json_encode([
    'event' => 'test',
    'data' => ['test' => true],
]);

$request3 = Request::create('/webhook', 'POST', [], [], [], [
    'HTTP_X_CLUBIFY_SIGNATURE' => 'sha256=invalid',
    'CONTENT_TYPE' => 'application/json',
], $payload3);

$resolvedSecret3 = $callback($request3);

if (!$resolvedSecret3) {
    echo "✅ Callback retornou null corretamente (organization_id ausente)\n";
    echo "⚠️  Sistema cairá no fallback global se configurado\n";
}

/**
 * PASSO 3: Integração com Laravel
 */

echo "\n\n=== INTEGRAÇÃO COM LARAVEL ===\n\n";

echo <<<'PHP'
// config/clubify-checkout.php
return [
    'webhook' => [
        'secret_resolver' => function(\Illuminate\Http\Request $request): ?string {
            $orgId = $request->header('X-Organization-ID');

            if (!$orgId) {
                $payload = json_decode($request->getContent(), true);
                $orgId = $payload['organization_id'] ?? $payload['data']['organization_id'] ?? null;
            }

            if (!$orgId) {
                \Log::warning('Webhook sem organization_id', [
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                ]);
                return null;
            }

            // Buscar do banco usando Eloquent
            $org = \App\Models\Organization::find($orgId);

            if (!$org) {
                \Log::error('Organization não encontrada', ['org_id' => $orgId]);
                return null;
            }

            // Retornar secret do settings JSON
            return $org->settings['clubify_checkout_webhook_secret'] ?? null;
        },
    ],
];

// routes/api.php
Route::post('/webhooks/clubify-checkout', [WebhookController::class, 'handle'])
    ->middleware('clubify.webhook'); // Middleware ValidateWebhook

// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Middleware já validou assinatura usando secret correto

        $event = $request->json('event');
        $data = $request->json('data');
        $orgId = $request->header('X-Organization-ID');

        \Log::info('Webhook recebido', [
            'event' => $event,
            'organization_id' => $orgId,
        ]);

        // Processar evento para a organização específica
        match($event) {
            'order.paid' => $this->handleOrderPaid($data, $orgId),
            'payment.approved' => $this->handlePaymentApproved($data, $orgId),
            default => \Log::info("Evento não tratado: {$event}"),
        };

        return response('OK', 200);
    }

    private function handleOrderPaid(array $data, string $orgId): void
    {
        // Processar pedido pago para a organização específica
        $order = Order::where('organization_id', $orgId)
            ->where('external_id', $data['order_id'])
            ->first();

        if ($order) {
            $order->markAsPaid();
            event(new OrderPaid($order));
        }
    }
}

PHP;

echo "\n✅ Exemplo concluído!\n\n";

echo "📚 Documentação completa: docs/webhooks/multi-tenant-setup.md\n";
echo "🔧 Mais exemplos: examples/webhooks/\n";
