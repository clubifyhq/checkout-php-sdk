<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Enums;

/**
 * Enum para tipos de notificação
 *
 * Define todos os tipos de notificação suportados pelo sistema:
 * - Eventos de checkout e pagamento
 * - Eventos de pedidos e entregas
 * - Eventos de usuários e autenticação
 * - Eventos de sistema e administrativos
 * - Eventos de marketing e campanhas
 *
 * Categorias principais:
 * - CHECKOUT: Eventos relacionados ao processo de checkout
 * - PAYMENT: Eventos de processamento de pagamentos
 * - ORDER: Eventos de gestão de pedidos
 * - USER: Eventos de usuários e autenticação
 * - SYSTEM: Eventos de sistema e administrativos
 * - MARKETING: Eventos de marketing e campanhas
 */
enum NotificationType: string
{
    // === CHECKOUT EVENTS ===
    case CHECKOUT_STARTED = 'checkout.started';
    case CHECKOUT_ABANDONED = 'checkout.abandoned';
    case CHECKOUT_COMPLETED = 'checkout.completed';
    case CHECKOUT_FAILED = 'checkout.failed';
    case CHECKOUT_STEP_COMPLETED = 'checkout.step_completed';

    // === PAYMENT EVENTS ===
    case PAYMENT_INITIATED = 'payment.initiated';
    case PAYMENT_PROCESSING = 'payment.processing';
    case PAYMENT_COMPLETED = 'payment.completed';
    case PAYMENT_FAILED = 'payment.failed';
    case PAYMENT_REFUNDED = 'payment.refunded';
    case PAYMENT_PARTIALLY_REFUNDED = 'payment.partially_refunded';
    case PAYMENT_CHARGEBACK = 'payment.chargeback';
    case PAYMENT_DISPUTED = 'payment.disputed';

    // === ORDER EVENTS ===
    case ORDER_CREATED = 'order.created';
    case ORDER_CONFIRMED = 'order.confirmed';
    case ORDER_PROCESSING = 'order.processing';
    case ORDER_SHIPPED = 'order.shipped';
    case ORDER_DELIVERED = 'order.delivered';
    case ORDER_CANCELLED = 'order.cancelled';
    case ORDER_RETURNED = 'order.returned';
    case ORDER_REFUNDED = 'order.refunded';

    // === SUBSCRIPTION EVENTS ===
    case SUBSCRIPTION_CREATED = 'subscription.created';
    case SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    case SUBSCRIPTION_RENEWED = 'subscription.renewed';
    case SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    case SUBSCRIPTION_EXPIRED = 'subscription.expired';
    case SUBSCRIPTION_PAYMENT_FAILED = 'subscription.payment_failed';
    case SUBSCRIPTION_TRIAL_ENDING = 'subscription.trial_ending';

    // === USER EVENTS ===
    case USER_REGISTERED = 'user.registered';
    case USER_LOGIN = 'user.login';
    case USER_LOGOUT = 'user.logout';
    case USER_PASSWORD_RESET = 'user.password_reset';
    case USER_EMAIL_VERIFIED = 'user.email_verified';
    case USER_PROFILE_UPDATED = 'user.profile_updated';
    case USER_ACCOUNT_SUSPENDED = 'user.account_suspended';
    case USER_ACCOUNT_REACTIVATED = 'user.account_reactivated';

    // === SYSTEM EVENTS ===
    case SYSTEM_MAINTENANCE = 'system.maintenance';
    case SYSTEM_BACKUP_COMPLETED = 'system.backup_completed';
    case SYSTEM_BACKUP_FAILED = 'system.backup_failed';
    case SYSTEM_SECURITY_ALERT = 'system.security_alert';
    case SYSTEM_PERFORMANCE_ALERT = 'system.performance_alert';
    case SYSTEM_ERROR = 'system.error';
    case SYSTEM_WEBHOOK_FAILED = 'system.webhook_failed';

    // === MARKETING EVENTS ===
    case MARKETING_CAMPAIGN_STARTED = 'marketing.campaign_started';
    case MARKETING_CAMPAIGN_ENDED = 'marketing.campaign_ended';
    case MARKETING_EMAIL_OPENED = 'marketing.email_opened';
    case MARKETING_EMAIL_CLICKED = 'marketing.email_clicked';
    case MARKETING_EMAIL_BOUNCED = 'marketing.email_bounced';
    case MARKETING_EMAIL_UNSUBSCRIBED = 'marketing.email_unsubscribed';

    // === INVENTORY EVENTS ===
    case INVENTORY_LOW_STOCK = 'inventory.low_stock';
    case INVENTORY_OUT_OF_STOCK = 'inventory.out_of_stock';
    case INVENTORY_RESTOCKED = 'inventory.restocked';

    // === SUPPORT EVENTS ===
    case SUPPORT_TICKET_CREATED = 'support.ticket_created';
    case SUPPORT_TICKET_UPDATED = 'support.ticket_updated';
    case SUPPORT_TICKET_RESOLVED = 'support.ticket_resolved';
    case SUPPORT_TICKET_CLOSED = 'support.ticket_closed';

    // === COMPLIANCE EVENTS ===
    case COMPLIANCE_GDPR_REQUEST = 'compliance.gdpr_request';
    case COMPLIANCE_DATA_EXPORT = 'compliance.data_export';
    case COMPLIANCE_DATA_DELETION = 'compliance.data_deletion';
    case COMPLIANCE_AUDIT_LOG = 'compliance.audit_log';

    // === WEBHOOK TEST EVENTS ===
    case WEBHOOK_TEST = 'webhook.test';
    case WEBHOOK_PING = 'webhook.ping';

    /**
     * Obtém todos os tipos de notificação
     */
    public static function all(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Obtém tipos por categoria
     */
    public static function getByCategory(string $category): array
    {
        $categoryMap = [
            'checkout' => [
                self::CHECKOUT_STARTED,
                self::CHECKOUT_ABANDONED,
                self::CHECKOUT_COMPLETED,
                self::CHECKOUT_FAILED,
                self::CHECKOUT_STEP_COMPLETED
            ],
            'payment' => [
                self::PAYMENT_INITIATED,
                self::PAYMENT_PROCESSING,
                self::PAYMENT_COMPLETED,
                self::PAYMENT_FAILED,
                self::PAYMENT_REFUNDED,
                self::PAYMENT_PARTIALLY_REFUNDED,
                self::PAYMENT_CHARGEBACK,
                self::PAYMENT_DISPUTED
            ],
            'order' => [
                self::ORDER_CREATED,
                self::ORDER_CONFIRMED,
                self::ORDER_PROCESSING,
                self::ORDER_SHIPPED,
                self::ORDER_DELIVERED,
                self::ORDER_CANCELLED,
                self::ORDER_RETURNED,
                self::ORDER_REFUNDED
            ],
            'subscription' => [
                self::SUBSCRIPTION_CREATED,
                self::SUBSCRIPTION_ACTIVATED,
                self::SUBSCRIPTION_RENEWED,
                self::SUBSCRIPTION_CANCELLED,
                self::SUBSCRIPTION_EXPIRED,
                self::SUBSCRIPTION_PAYMENT_FAILED,
                self::SUBSCRIPTION_TRIAL_ENDING
            ],
            'user' => [
                self::USER_REGISTERED,
                self::USER_LOGIN,
                self::USER_LOGOUT,
                self::USER_PASSWORD_RESET,
                self::USER_EMAIL_VERIFIED,
                self::USER_PROFILE_UPDATED,
                self::USER_ACCOUNT_SUSPENDED,
                self::USER_ACCOUNT_REACTIVATED
            ],
            'system' => [
                self::SYSTEM_MAINTENANCE,
                self::SYSTEM_BACKUP_COMPLETED,
                self::SYSTEM_BACKUP_FAILED,
                self::SYSTEM_SECURITY_ALERT,
                self::SYSTEM_PERFORMANCE_ALERT,
                self::SYSTEM_ERROR,
                self::SYSTEM_WEBHOOK_FAILED
            ],
            'marketing' => [
                self::MARKETING_CAMPAIGN_STARTED,
                self::MARKETING_CAMPAIGN_ENDED,
                self::MARKETING_EMAIL_OPENED,
                self::MARKETING_EMAIL_CLICKED,
                self::MARKETING_EMAIL_BOUNCED,
                self::MARKETING_EMAIL_UNSUBSCRIBED
            ],
            'inventory' => [
                self::INVENTORY_LOW_STOCK,
                self::INVENTORY_OUT_OF_STOCK,
                self::INVENTORY_RESTOCKED
            ],
            'support' => [
                self::SUPPORT_TICKET_CREATED,
                self::SUPPORT_TICKET_UPDATED,
                self::SUPPORT_TICKET_RESOLVED,
                self::SUPPORT_TICKET_CLOSED
            ],
            'compliance' => [
                self::COMPLIANCE_GDPR_REQUEST,
                self::COMPLIANCE_DATA_EXPORT,
                self::COMPLIANCE_DATA_DELETION,
                self::COMPLIANCE_AUDIT_LOG
            ],
            'webhook' => [
                self::WEBHOOK_TEST,
                self::WEBHOOK_PING
            ]
        ];

        return array_map(
            fn ($case) => $case->value,
            $categoryMap[strtolower($category)] ?? []
        );
    }

    /**
     * Obtém categoria do tipo
     */
    public function getCategory(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * Obtém ação do tipo
     */
    public function getAction(): string
    {
        $parts = explode('.', $this->value);
        return $parts[1] ?? '';
    }

    /**
     * Verifica se é evento crítico
     */
    public function isCritical(): bool
    {
        return in_array($this, [
            self::PAYMENT_FAILED,
            self::PAYMENT_CHARGEBACK,
            self::PAYMENT_DISPUTED,
            self::ORDER_CANCELLED,
            self::SYSTEM_SECURITY_ALERT,
            self::SYSTEM_ERROR,
            self::SYSTEM_WEBHOOK_FAILED,
            self::USER_ACCOUNT_SUSPENDED,
            self::SUBSCRIPTION_PAYMENT_FAILED
        ]);
    }

    /**
     * Verifica se é evento de sucesso
     */
    public function isSuccess(): bool
    {
        return in_array($this, [
            self::CHECKOUT_COMPLETED,
            self::PAYMENT_COMPLETED,
            self::ORDER_DELIVERED,
            self::SUBSCRIPTION_ACTIVATED,
            self::USER_REGISTERED,
            self::USER_EMAIL_VERIFIED
        ]);
    }

    /**
     * Verifica se é evento de teste
     */
    public function isTest(): bool
    {
        return in_array($this, [
            self::WEBHOOK_TEST,
            self::WEBHOOK_PING
        ]);
    }

    /**
     * Verifica se requer ação imediata
     */
    public function requiresImmediateAction(): bool
    {
        return in_array($this, [
            self::PAYMENT_FAILED,
            self::PAYMENT_CHARGEBACK,
            self::SYSTEM_SECURITY_ALERT,
            self::SYSTEM_ERROR,
            self::INVENTORY_OUT_OF_STOCK,
            self::SUBSCRIPTION_PAYMENT_FAILED
        ]);
    }

    /**
     * Obtém prioridade do evento (1-5, sendo 1 mais alta)
     */
    public function getPriority(): int
    {
        if ($this->requiresImmediateAction()) {
            return 1;
        }

        if ($this->isCritical()) {
            return 2;
        }

        if ($this->isSuccess()) {
            return 3;
        }

        if ($this->isTest()) {
            return 5;
        }

        return 4; // Prioridade padrão
    }

    /**
     * Obtém método de entrega recomendado
     */
    public function getRecommendedDeliveryMethod(): string
    {
        if ($this->requiresImmediateAction()) {
            return 'webhook'; // Para sistemas
        }

        if ($this->isCritical()) {
            return 'email'; // Para usuários
        }

        return 'webhook'; // Padrão
    }

    /**
     * Obtém template padrão para o tipo
     */
    public function getDefaultTemplate(): string
    {
        return match($this->getCategory()) {
            'checkout' => 'checkout_notification',
            'payment' => 'payment_notification',
            'order' => 'order_notification',
            'subscription' => 'subscription_notification',
            'user' => 'user_notification',
            'system' => 'system_alert',
            'marketing' => 'marketing_email',
            'inventory' => 'inventory_alert',
            'support' => 'support_notification',
            'compliance' => 'compliance_notification',
            'webhook' => 'webhook_test',
            default => 'generic_notification'
        };
    }

    /**
     * Obtém timeout recomendado (em segundos)
     */
    public function getRecommendedTimeout(): int
    {
        if ($this->requiresImmediateAction()) {
            return 10; // Timeout mais curto para eventos críticos
        }

        if ($this->isCritical()) {
            return 20;
        }

        return 30; // Timeout padrão
    }

    /**
     * Obtém máximo de retries recomendado
     */
    public function getRecommendedMaxRetries(): int
    {
        if ($this->requiresImmediateAction() || $this->isCritical()) {
            return 5; // Mais retries para eventos importantes
        }

        if ($this->isTest()) {
            return 1; // Apenas um retry para testes
        }

        return 3; // Padrão
    }

    /**
     * Verifica se o tipo é válido
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all());
    }

    /**
     * Cria instância a partir de string
     */
    public static function fromString(string $type): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $type) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Obtém descrição amigável do tipo
     */
    public function getDescription(): string
    {
        return match($this) {
            self::CHECKOUT_STARTED => 'Checkout iniciado',
            self::CHECKOUT_COMPLETED => 'Checkout concluído',
            self::CHECKOUT_ABANDONED => 'Checkout abandonado',
            self::PAYMENT_COMPLETED => 'Pagamento aprovado',
            self::PAYMENT_FAILED => 'Pagamento rejeitado',
            self::ORDER_CREATED => 'Pedido criado',
            self::ORDER_DELIVERED => 'Pedido entregue',
            self::USER_REGISTERED => 'Usuário registrado',
            self::SYSTEM_ERROR => 'Erro do sistema',
            default => 'Evento do sistema'
        };
    }

    /**
     * Obtém ícone para o tipo
     */
    public function getIcon(): string
    {
        return match($this->getCategory()) {
            'checkout' => '🛒',
            'payment' => '💳',
            'order' => '📦',
            'subscription' => '🔄',
            'user' => '👤',
            'system' => '⚙️',
            'marketing' => '📧',
            'inventory' => '📋',
            'support' => '🎧',
            'compliance' => '🔒',
            'webhook' => '🔗',
            default => '📄'
        };
    }
}
