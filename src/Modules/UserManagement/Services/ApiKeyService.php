<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;

class ApiKeyService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    public function createApiKey(string $userId, array $keyData): array
    {
        return ['success' => true, 'api_key' => 'ak_' . uniqid(), 'key_id' => uniqid('key_')];
    }

    public function revokeApiKey(string $keyId): array
    {
        return ['success' => true, 'key_id' => $keyId, 'revoked' => true];
    }
}
