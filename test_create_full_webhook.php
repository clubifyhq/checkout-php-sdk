<?php
require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Carregar env
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value, '"\''));
        }
    }
}

$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'api_key' => getenv('CLUBIFY_API_KEY'),
        'tenant_id' => getenv('CLUBIFY_TENANT_ID'),
        'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
        'environment' => 'live'
    ],
    'endpoints' => ['base_url' => 'https://checkout.svelve.com/api/v1']
]);

$sdk->initialize();

echo "✅ SDK initialized\n\n";

// PASSO 1: Verificar se já existe configuração
echo "🔍 Checking existing webhook configuration...\n";
$orgId = getenv('CLUBIFY_ORGANIZATION_ID');
$existing = $sdk->notifications()->getWebhookConfigByPartnerId($orgId);

if ($existing) {
    echo "⚠️  Webhook configuration already exists (ID: {$existing['_id']})\n";
    echo "   Deleting before creating new one...\n";
    // Aqui você precisaria implementar o delete
    echo "   (Delete not implemented yet - skipping creation)\n\n";
    echo "📋 Existing configuration:\n";
    echo "   Partner ID: {$existing['partnerId']}\n";
    echo "   Name: {$existing['name']}\n";
    echo "   Endpoints: " . count($existing['endpoints']) . "\n";
    foreach ($existing['endpoints'] as $ep) {
        echo "     - {$ep['eventType']}: {$ep['url']}\n";
    }
    exit(0);
}

echo "➕ No existing configuration found\n\n";

// PASSO 2: Criar nova configuração
echo "📝 Creating webhook configuration with ALL events...\n\n";

$webhookData = [
    'url' => 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks',
    'events' => [
        // Subscription events
        'subscription.created',
        'subscription.updated',
        'subscription.cancelled',
        // Payment events
        'payment.authorized',
        'payment.paid',
        'payment.failed',
        // Order events  
        'order.created',
        'order.paid',
        'order.completed',
    ],
    'description' => 'Complete Webhook Configuration - PHP SDK Test',
    'active' => true,
    'organization_id' => $orgId,
];

try {
    $result = $sdk->webhooks()->createWebhook($webhookData);
    echo "✅ Webhook created successfully!\n\n";
    echo "📋 Result:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}
