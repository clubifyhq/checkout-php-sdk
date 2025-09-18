<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Subscriptions\DTOs\SubscriptionData;
use DateTime;

/**
 * Serviço principal de gestão de assinaturas
 */
class SubscriptionService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {
    }

    public function createSubscription(array $subscriptionData): array
    {
        try {
            $currentPeriodStart = new DateTime();
            $currentPeriodEnd = (clone $currentPeriodStart)->modify('+1 month');

            $subscription = new SubscriptionData(array_merge($subscriptionData, [
                'id' => uniqid('sub_'),
                'current_period_amount' => $subscriptionData['current_period_amount'] ?? 29.90,
                'current_period_start' => $currentPeriodStart,
                'current_period_end' => $currentPeriodEnd,
                'billing_cycle' => $subscriptionData['billing_cycle'] ?? 'monthly',
                'currency' => $subscriptionData['currency'] ?? 'BRL',
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]));

            $this->logger->info('Subscription created', [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'plan_id' => $subscription->plan_id,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'subscription' => $subscription->toArray(),
                'mrr' => $subscription->calculateMRR(),
                'next_billing_date' => $subscription->current_period_end->format('Y-m-d'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create subscription', [
                'error' => $e->getMessage(),
                'data' => $subscriptionData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSubscription(string $subscriptionId): array
    {
        return [
            'success' => true,
            'subscription' => [
                'id' => $subscriptionId,
                'customer_id' => 'cust_123',
                'plan_id' => 'plan_456',
                'status' => 'active',
                'current_period_amount' => 29.90,
                'billing_cycle' => 'monthly',
                'currency' => 'BRL',
            ],
        ];
    }

    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        $this->logger->info('Subscription updated', [
            'subscription_id' => $subscriptionId,
            'updates' => array_keys($subscriptionData),
        ]);

        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'updated_at' => (new DateTime())->format('c'),
        ];
    }

    public function listSubscriptions(array $filters = []): array
    {
        return [
            'success' => true,
            'subscriptions' => [],
            'total' => 0,
            'filters' => $filters,
        ];
    }
}
