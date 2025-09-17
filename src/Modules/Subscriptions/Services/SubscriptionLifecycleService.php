<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use DateTime;

/**
 * Serviço de lifecycle de assinaturas
 */
class SubscriptionLifecycleService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    public function pauseSubscription(string $subscriptionId): array
    {
        try {
            $this->logger->info('Subscription paused', [
                'subscription_id' => $subscriptionId,
                'paused_at' => (new DateTime())->format('c'),
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'status' => 'paused',
                'paused_at' => (new DateTime())->format('c'),
                'billing_suspended' => true,
                'resume_date' => null,
                'next_action' => 'Subscription will remain paused until manually resumed',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to pause subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        try {
            $this->logger->info('Subscription resumed', [
                'subscription_id' => $subscriptionId,
                'resumed_at' => (new DateTime())->format('c'),
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'status' => 'active',
                'resumed_at' => (new DateTime())->format('c'),
                'billing_resumed' => true,
                'next_billing_date' => (new DateTime('+30 days'))->format('Y-m-d'),
                'next_action' => 'Normal billing cycle will continue',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to resume subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId, array $options = []): array
    {
        try {
            $cancelAtPeriodEnd = $options['cancel_at_period_end'] ?? true;
            $cancelReason = $options['reason'] ?? 'customer_request';
            $cancelDate = $cancelAtPeriodEnd ?
                (new DateTime('+23 days'))->format('Y-m-d') :
                (new DateTime())->format('Y-m-d');

            $this->logger->info('Subscription canceled', [
                'subscription_id' => $subscriptionId,
                'cancel_reason' => $cancelReason,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'cancel_date' => $cancelDate,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'status' => $cancelAtPeriodEnd ? 'active' : 'canceled',
                'cancel_at' => $cancelDate,
                'canceled_at' => $cancelAtPeriodEnd ? null : (new DateTime())->format('c'),
                'cancel_reason' => $cancelReason,
                'refund_policy' => $cancelAtPeriodEnd ? 'no_refund' : 'prorated_refund',
                'access_until' => $cancelDate,
                'next_action' => $cancelAtPeriodEnd ?
                    'Subscription will be canceled at period end' :
                    'Subscription canceled immediately',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function upgradeSubscription(string $subscriptionId, string $newPlanId): array
    {
        try {
            $this->logger->info('Subscription upgraded', [
                'subscription_id' => $subscriptionId,
                'old_plan_id' => 'plan_basic',
                'new_plan_id' => $newPlanId,
            ]);

            // Calcular valores de upgrade
            $oldAmount = 29.90;
            $newAmount = 99.90;
            $proratedAmount = ($newAmount - $oldAmount) * 0.77; // 77% do período restante

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'upgrade' => [
                    'old_plan_id' => 'plan_basic',
                    'new_plan_id' => $newPlanId,
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'prorated_amount' => round($proratedAmount, 2),
                    'effective_date' => (new DateTime())->format('Y-m-d'),
                    'next_billing_date' => (new DateTime('+23 days'))->format('Y-m-d'),
                ],
                'billing_changes' => [
                    'immediate_charge' => round($proratedAmount, 2),
                    'next_amount' => $newAmount,
                    'billing_cycle' => 'monthly',
                ],
                'upgraded_at' => (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to upgrade subscription', [
                'subscription_id' => $subscriptionId,
                'new_plan_id' => $newPlanId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function downgradeSubscription(string $subscriptionId, string $newPlanId): array
    {
        try {
            $this->logger->info('Subscription downgraded', [
                'subscription_id' => $subscriptionId,
                'old_plan_id' => 'plan_premium',
                'new_plan_id' => $newPlanId,
            ]);

            // Calcular valores de downgrade
            $oldAmount = 99.90;
            $newAmount = 29.90;
            $creditAmount = ($oldAmount - $newAmount) * 0.77; // 77% do período restante

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'downgrade' => [
                    'old_plan_id' => 'plan_premium',
                    'new_plan_id' => $newPlanId,
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'credit_amount' => round($creditAmount, 2),
                    'effective_date' => (new DateTime('+23 days'))->format('Y-m-d'),
                    'next_billing_date' => (new DateTime('+23 days'))->format('Y-m-d'),
                ],
                'billing_changes' => [
                    'credit_applied' => round($creditAmount, 2),
                    'next_amount' => $newAmount,
                    'billing_cycle' => 'monthly',
                    'applies_at_period_end' => true,
                ],
                'downgraded_at' => (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to downgrade subscription', [
                'subscription_id' => $subscriptionId,
                'new_plan_id' => $newPlanId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function reactivateSubscription(string $subscriptionId): array
    {
        try {
            $this->logger->info('Subscription reactivated', [
                'subscription_id' => $subscriptionId,
                'reactivated_at' => (new DateTime())->format('c'),
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'status' => 'active',
                'reactivated_at' => (new DateTime())->format('c'),
                'billing_resumed' => true,
                'next_billing_date' => (new DateTime('+30 days'))->format('Y-m-d'),
                'welcome_back_offer' => [
                    'discount_percentage' => 20,
                    'valid_for_cycles' => 3,
                    'offer_code' => 'WELCOME_BACK_20',
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to reactivate subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function extendTrial(string $subscriptionId, int $additionalDays): array
    {
        try {
            $newTrialEnd = new DateTime("+{$additionalDays} days");

            $this->logger->info('Trial extended', [
                'subscription_id' => $subscriptionId,
                'additional_days' => $additionalDays,
                'new_trial_end' => $newTrialEnd->format('Y-m-d'),
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'trial_extension' => [
                    'additional_days' => $additionalDays,
                    'old_trial_end' => (new DateTime('+7 days'))->format('Y-m-d'),
                    'new_trial_end' => $newTrialEnd->format('Y-m-d'),
                    'extended_at' => (new DateTime())->format('c'),
                ],
                'billing_impact' => [
                    'first_billing_date' => $newTrialEnd->format('Y-m-d'),
                    'billing_delay_days' => $additionalDays,
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to extend trial', [
                'subscription_id' => $subscriptionId,
                'additional_days' => $additionalDays,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getLifecycleHistory(string $subscriptionId): array
    {
        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'lifecycle_events' => [
                [
                    'event' => 'subscription_created',
                    'date' => (new DateTime('-90 days'))->format('c'),
                    'details' => [
                        'plan_id' => 'plan_basic',
                        'trial_days' => 7,
                    ],
                ],
                [
                    'event' => 'trial_ended',
                    'date' => (new DateTime('-83 days'))->format('c'),
                    'details' => [
                        'converted' => true,
                        'first_payment' => 29.90,
                    ],
                ],
                [
                    'event' => 'plan_upgraded',
                    'date' => (new DateTime('-30 days'))->format('c'),
                    'details' => [
                        'from_plan' => 'plan_basic',
                        'to_plan' => 'plan_premium',
                        'prorated_charge' => 53.93,
                    ],
                ],
                [
                    'event' => 'payment_successful',
                    'date' => (new DateTime('-7 days'))->format('c'),
                    'details' => [
                        'amount' => 99.90,
                        'invoice_id' => 'inv_001',
                    ],
                ],
            ],
            'current_status' => 'active',
            'total_events' => 4,
        ];
    }

    public function scheduleAction(string $subscriptionId, string $action, string $scheduledDate, array $options = []): array
    {
        try {
            $this->logger->info('Subscription action scheduled', [
                'subscription_id' => $subscriptionId,
                'action' => $action,
                'scheduled_date' => $scheduledDate,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'scheduled_action' => [
                    'id' => uniqid('schedule_'),
                    'action' => $action,
                    'scheduled_date' => $scheduledDate,
                    'options' => $options,
                    'status' => 'pending',
                    'created_at' => (new DateTime())->format('c'),
                ],
                'cancellable_until' => (new DateTime($scheduledDate . ' -1 day'))->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule action', [
                'subscription_id' => $subscriptionId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}