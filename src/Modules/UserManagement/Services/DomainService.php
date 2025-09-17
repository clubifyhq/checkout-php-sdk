<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;

class DomainService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    public function configureDomain(string $tenantId, array $domainData): array
    {
        return ['success' => true, 'domain_id' => uniqid('domain_'), 'status' => 'pending_verification'];
    }

    public function verifyDomain(string $domainId): array
    {
        return ['success' => true, 'domain_id' => $domainId, 'verified' => true];
    }
}
