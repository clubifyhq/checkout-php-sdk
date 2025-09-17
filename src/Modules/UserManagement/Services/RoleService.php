<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;

class RoleService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    public function assignRole(string $userId, string $role): array
    {
        return ['success' => true, 'user_id' => $userId, 'role' => $role];
    }

    public function checkPermission(string $userId, string $permission): array
    {
        return ['success' => true, 'user_id' => $userId, 'has_permission' => true];
    }
}
