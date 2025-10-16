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

echo "Buscando webhook configuration...\n";
$orgId = getenv('CLUBIFY_ORGANIZATION_ID');
$config = $sdk->notifications()->getWebhookConfigByPartnerId($orgId);
var_dump($config);
