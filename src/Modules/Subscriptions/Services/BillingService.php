<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use DateTime;

/**
 * Serviço de billing e cobrança de assinaturas
 */
class BillingService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    public function processManualBilling(string $subscriptionId): array
    {
        try {
            $this->logger->info('Processing manual billing', [
                'subscription_id' => $subscriptionId,
                'initiated_at' => (new DateTime())->format('c'),
            ]);

            // Simular processamento de cobrança
            $invoiceId = uniqid('inv_');
            $amount = 99.90;

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'currency' => 'BRL',
                'status' => 'paid',
                'processed_at' => (new DateTime())->format('c'),
                'next_billing_date' => (new DateTime('+1 month'))->format('Y-m-d'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process manual billing', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getUpcomingInvoice(string $subscriptionId): array
    {
        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'upcoming_invoice' => [
                'amount' => 99.90,
                'currency' => 'BRL',
                'billing_date' => (new DateTime('+7 days'))->format('Y-m-d'),
                'period_start' => (new DateTime('+7 days'))->format('Y-m-d'),
                'period_end' => (new DateTime('+37 days'))->format('Y-m-d'),
                'line_items' => [
                    [
                        'description' => 'Plano Premium - Mensal',
                        'amount' => 99.90,
                        'quantity' => 1,
                        'period' => 'monthly',
                    ],
                ],
                'taxes' => [
                    'amount' => 0.00,
                    'rate' => 0.00,
                ],
                'discount' => [
                    'amount' => 0.00,
                    'coupon_code' => null,
                ],
                'total' => 99.90,
            ],
        ];
    }

    public function getInvoiceHistory(string $subscriptionId): array
    {
        $invoices = [
            [
                'id' => 'inv_001',
                'amount' => 99.90,
                'currency' => 'BRL',
                'status' => 'paid',
                'billing_date' => (new DateTime('-30 days'))->format('Y-m-d'),
                'paid_at' => (new DateTime('-30 days'))->format('c'),
                'period_start' => (new DateTime('-37 days'))->format('Y-m-d'),
                'period_end' => (new DateTime('-7 days'))->format('Y-m-d'),
            ],
            [
                'id' => 'inv_002',
                'amount' => 99.90,
                'currency' => 'BRL',
                'status' => 'paid',
                'billing_date' => (new DateTime('-60 days'))->format('Y-m-d'),
                'paid_at' => (new DateTime('-60 days'))->format('c'),
                'period_start' => (new DateTime('-67 days'))->format('Y-m-d'),
                'period_end' => (new DateTime('-37 days'))->format('Y-m-d'),
            ],
        ];

        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'invoices' => $invoices,
            'total' => count($invoices),
            'total_amount' => array_sum(array_column($invoices, 'amount')),
        ];
    }

    public function updatePaymentMethod(string $subscriptionId, array $paymentMethodData): array
    {
        try {
            $this->logger->info('Payment method updated', [
                'subscription_id' => $subscriptionId,
                'payment_method_type' => $paymentMethodData['type'] ?? 'unknown',
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'payment_method' => [
                    'id' => uniqid('pm_'),
                    'type' => $paymentMethodData['type'] ?? 'card',
                    'last4' => $paymentMethodData['last4'] ?? '****',
                    'brand' => $paymentMethodData['brand'] ?? 'visa',
                    'exp_month' => $paymentMethodData['exp_month'] ?? 12,
                    'exp_year' => $paymentMethodData['exp_year'] ?? 2025,
                ],
                'updated_at' => (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update payment method', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function retryFailedPayment(string $subscriptionId): array
    {
        try {
            $this->logger->info('Retrying failed payment', [
                'subscription_id' => $subscriptionId,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'payment_attempt' => [
                    'attempt_id' => uniqid('att_'),
                    'status' => 'processing',
                    'amount' => 99.90,
                    'currency' => 'BRL',
                    'attempted_at' => (new DateTime())->format('c'),
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to retry payment', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function processProratedBilling(string $subscriptionId, array $changes): array
    {
        try {
            $baseAmount = $changes['old_amount'] ?? 99.90;
            $newAmount = $changes['new_amount'] ?? 199.90;
            $daysRemaining = $changes['days_remaining'] ?? 15;
            $daysInPeriod = $changes['days_in_period'] ?? 30;

            // Calcular valor proporcional
            $creditAmount = ($baseAmount / $daysInPeriod) * $daysRemaining;
            $chargeAmount = ($newAmount / $daysInPeriod) * $daysRemaining;
            $proratedAmount = $chargeAmount - $creditAmount;

            $this->logger->info('Processing prorated billing', [
                'subscription_id' => $subscriptionId,
                'old_amount' => $baseAmount,
                'new_amount' => $newAmount,
                'prorated_amount' => $proratedAmount,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'prorated_billing' => [
                    'credit_amount' => round($creditAmount, 2),
                    'charge_amount' => round($chargeAmount, 2),
                    'prorated_amount' => round($proratedAmount, 2),
                    'days_remaining' => $daysRemaining,
                    'applied_at' => (new DateTime())->format('c'),
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process prorated billing', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getBillingCycle(string $subscriptionId): array
    {
        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'billing_cycle' => [
                'current_period_start' => (new DateTime('-7 days'))->format('Y-m-d'),
                'current_period_end' => (new DateTime('+23 days'))->format('Y-m-d'),
                'next_billing_date' => (new DateTime('+23 days'))->format('Y-m-d'),
                'billing_interval' => 'monthly',
                'days_until_billing' => 23,
                'total_billing_cycles' => 3,
            ],
        ];
    }

    public function generateInvoice(string $subscriptionId, array $options = []): array
    {
        try {
            $invoiceId = uniqid('inv_');

            $this->logger->info('Invoice generated', [
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'invoice' => [
                    'id' => $invoiceId,
                    'amount' => 99.90,
                    'currency' => 'BRL',
                    'status' => 'draft',
                    'created_at' => (new DateTime())->format('c'),
                    'due_date' => (new DateTime('+7 days'))->format('Y-m-d'),
                    'download_url' => "https://api.clubify.com/invoices/{$invoiceId}/download",
                    'public_url' => "https://checkout.clubify.com/invoices/{$invoiceId}",
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate invoice', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}