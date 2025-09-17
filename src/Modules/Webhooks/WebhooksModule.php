<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;

class WebhooksModule implements ModuleInterface
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
    }

    public function isHealthy(): bool
    {
        return true;
    }

    public function getStats(): array
    {
        return [
            'module' => 'webhooks',
            'status' => 'active'
        ];
    }
}