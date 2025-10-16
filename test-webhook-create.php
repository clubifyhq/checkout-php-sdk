<?php

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

function loadEnvFile(string $path): void
{
    if (!file_exists($path)) {
        throw new \RuntimeException("Arquivo .env não encontrado: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/.env');

$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'api_key' => getenv('CLUBIFY_API_KEY'),
        'tenant_id' => getenv('CLUBIFY_TENANT_ID'),
        'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
        'environment' => 'live'
    ],
    'endpoints' => [
        'base_url' => 'https://checkout.svelve.com/api/v1'
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug'
    ],
    'debug' => true
]);

$sdk->initialize();

echo "Testing webhook create with notification-service...\n\n";

$webhookData = [
    'partnerId' => getenv('CLUBIFY_ORGANIZATION_ID'),
    'name' => 'Test Webhook from PHP SDK',
    'endpoints' => [
        [
            'url' => 'https://webhook.site/test-php-sdk',
            'eventType' => 'subscription.created',
            'secret' => 'test_secret_12345678'
        ]
    ]
];

echo "Data to send:\n";
print_r($webhookData);
echo "\n";

try {
    $result = $sdk->notifications()->createWebhookConfig($webhookData);
    echo "✅ SUCCESS!\n";
    print_r($result);
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
