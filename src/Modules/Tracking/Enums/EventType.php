<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\Enums;

/**
 * Enum para tipos de eventos de tracking
 *
 * Define todos os tipos de eventos suportados pelo sistema de tracking,
 * organizados por categoria para facilitar análise e segmentação.
 *
 * Categorias:
 * - Navigation: Eventos de navegação
 * - Engagement: Eventos de engajamento
 * - Conversion: Eventos de conversão
 * - Commerce: Eventos de comércio
 * - User: Eventos de usuário
 * - System: Eventos de sistema
 */
enum EventType: string
{
    // Navigation Events
    case PAGE_VIEW = 'page_view';
    case PAGE_LOAD = 'page_load';
    case PAGE_EXIT = 'page_exit';
    case NAVIGATION_CLICK = 'navigation_click';
    case EXTERNAL_LINK_CLICK = 'external_link_click';
    case SCROLL_DEPTH = 'scroll_depth';
    case TIME_ON_PAGE = 'time_on_page';

    // Engagement Events
    case BUTTON_CLICK = 'button_click';
    case FORM_START = 'form_start';
    case FORM_SUBMIT = 'form_submit';
    case FORM_ABANDON = 'form_abandon';
    case FORM_ERROR = 'form_error';
    case FORM_VALIDATION_ERROR = 'form_validation_error';
    case MODAL_OPEN = 'modal_open';
    case MODAL_CLOSE = 'modal_close';
    case TAB_SWITCH = 'tab_switch';
    case ACCORDION_TOGGLE = 'accordion_toggle';
    case VIDEO_PLAY = 'video_play';
    case VIDEO_PAUSE = 'video_pause';
    case VIDEO_COMPLETE = 'video_complete';
    case DOWNLOAD_START = 'download_start';
    case DOWNLOAD_COMPLETE = 'download_complete';
    case SEARCH_PERFORMED = 'search_performed';
    case FILTER_APPLIED = 'filter_applied';
    case SORT_APPLIED = 'sort_applied';

    // Conversion Events
    case LEAD_GENERATED = 'lead_generated';
    case SIGNUP_STARTED = 'signup_started';
    case SIGNUP_COMPLETED = 'signup_completed';
    case TRIAL_STARTED = 'trial_started';
    case TRIAL_COMPLETED = 'trial_completed';
    case SUBSCRIPTION_CREATED = 'subscription_created';
    case SUBSCRIPTION_UPGRADED = 'subscription_upgraded';
    case SUBSCRIPTION_DOWNGRADED = 'subscription_downgraded';
    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    case PURCHASE_INITIATED = 'purchase_initiated';
    case PURCHASE_COMPLETED = 'purchase_completed';
    case PURCHASE_FAILED = 'purchase_failed';
    case CHECKOUT_STARTED = 'checkout_started';
    case CHECKOUT_COMPLETED = 'checkout_completed';
    case CHECKOUT_ABANDONED = 'checkout_abandoned';

    // Commerce Events
    case PRODUCT_VIEW = 'product_view';
    case PRODUCT_LIST_VIEW = 'product_list_view';
    case ADD_TO_CART = 'add_to_cart';
    case REMOVE_FROM_CART = 'remove_from_cart';
    case CART_VIEW = 'cart_view';
    case WISHLIST_ADD = 'wishlist_add';
    case WISHLIST_REMOVE = 'wishlist_remove';
    case PRICE_COMPARISON = 'price_comparison';
    case COUPON_APPLIED = 'coupon_applied';
    case COUPON_REMOVED = 'coupon_removed';
    case SHIPPING_SELECTED = 'shipping_selected';
    case PAYMENT_METHOD_SELECTED = 'payment_method_selected';
    case ORDER_BUMP_VIEW = 'order_bump_view';
    case ORDER_BUMP_ACCEPTED = 'order_bump_accepted';
    case ORDER_BUMP_DECLINED = 'order_bump_declined';
    case UPSELL_VIEW = 'upsell_view';
    case UPSELL_ACCEPTED = 'upsell_accepted';
    case UPSELL_DECLINED = 'upsell_declined';

    // User Events
    case USER_LOGIN = 'user_login';
    case USER_LOGOUT = 'user_logout';
    case USER_REGISTRATION = 'user_registration';
    case PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    case PASSWORD_RESET_COMPLETED = 'password_reset_completed';
    case PROFILE_UPDATE = 'profile_update';
    case PREFERENCES_UPDATE = 'preferences_update';
    case ACCOUNT_DELETION = 'account_deletion';
    case SESSION_START = 'session_start';
    case SESSION_END = 'session_end';
    case SESSION_TIMEOUT = 'session_timeout';

    // System Events
    case ERROR_OCCURRED = 'error_occurred';
    case API_REQUEST = 'api_request';
    case API_ERROR = 'api_error';
    case PERFORMANCE_METRIC = 'performance_metric';
    case AB_TEST_VIEW = 'ab_test_view';
    case AB_TEST_CONVERSION = 'ab_test_conversion';
    case FEATURE_FLAG_ENABLED = 'feature_flag_enabled';
    case NOTIFICATION_SENT = 'notification_sent';
    case NOTIFICATION_CLICKED = 'notification_clicked';
    case EMAIL_SENT = 'email_sent';
    case EMAIL_OPENED = 'email_opened';
    case EMAIL_CLICKED = 'email_clicked';

    // Custom Events
    case CUSTOM_EVENT = 'custom_event';
    case BEACON_EVENT = 'beacon_event';

    /**
     * Obtém todos os tipos de eventos
     */
    public static function all(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Obtém eventos de navegação
     */
    public static function navigationEvents(): array
    {
        return [
            self::PAGE_VIEW->value,
            self::PAGE_LOAD->value,
            self::PAGE_EXIT->value,
            self::NAVIGATION_CLICK->value,
            self::EXTERNAL_LINK_CLICK->value,
            self::SCROLL_DEPTH->value,
            self::TIME_ON_PAGE->value,
        ];
    }

    /**
     * Obtém eventos de engajamento
     */
    public static function engagementEvents(): array
    {
        return [
            self::BUTTON_CLICK->value,
            self::FORM_START->value,
            self::FORM_SUBMIT->value,
            self::FORM_ABANDON->value,
            self::FORM_ERROR->value,
            self::FORM_VALIDATION_ERROR->value,
            self::MODAL_OPEN->value,
            self::MODAL_CLOSE->value,
            self::TAB_SWITCH->value,
            self::ACCORDION_TOGGLE->value,
            self::VIDEO_PLAY->value,
            self::VIDEO_PAUSE->value,
            self::VIDEO_COMPLETE->value,
            self::DOWNLOAD_START->value,
            self::DOWNLOAD_COMPLETE->value,
            self::SEARCH_PERFORMED->value,
            self::FILTER_APPLIED->value,
            self::SORT_APPLIED->value,
        ];
    }

    /**
     * Obtém eventos de conversão
     */
    public static function conversionEvents(): array
    {
        return [
            self::LEAD_GENERATED->value,
            self::SIGNUP_STARTED->value,
            self::SIGNUP_COMPLETED->value,
            self::TRIAL_STARTED->value,
            self::TRIAL_COMPLETED->value,
            self::SUBSCRIPTION_CREATED->value,
            self::SUBSCRIPTION_UPGRADED->value,
            self::PURCHASE_INITIATED->value,
            self::PURCHASE_COMPLETED->value,
            self::CHECKOUT_STARTED->value,
            self::CHECKOUT_COMPLETED->value,
        ];
    }

    /**
     * Obtém eventos de comércio
     */
    public static function commerceEvents(): array
    {
        return [
            self::PRODUCT_VIEW->value,
            self::PRODUCT_LIST_VIEW->value,
            self::ADD_TO_CART->value,
            self::REMOVE_FROM_CART->value,
            self::CART_VIEW->value,
            self::WISHLIST_ADD->value,
            self::WISHLIST_REMOVE->value,
            self::PRICE_COMPARISON->value,
            self::COUPON_APPLIED->value,
            self::COUPON_REMOVED->value,
            self::SHIPPING_SELECTED->value,
            self::PAYMENT_METHOD_SELECTED->value,
            self::ORDER_BUMP_VIEW->value,
            self::ORDER_BUMP_ACCEPTED->value,
            self::ORDER_BUMP_DECLINED->value,
            self::UPSELL_VIEW->value,
            self::UPSELL_ACCEPTED->value,
            self::UPSELL_DECLINED->value,
        ];
    }

    /**
     * Obtém eventos de usuário
     */
    public static function userEvents(): array
    {
        return [
            self::USER_LOGIN->value,
            self::USER_LOGOUT->value,
            self::USER_REGISTRATION->value,
            self::PASSWORD_RESET_REQUESTED->value,
            self::PASSWORD_RESET_COMPLETED->value,
            self::PROFILE_UPDATE->value,
            self::PREFERENCES_UPDATE->value,
            self::ACCOUNT_DELETION->value,
            self::SESSION_START->value,
            self::SESSION_END->value,
            self::SESSION_TIMEOUT->value,
        ];
    }

    /**
     * Obtém eventos de sistema
     */
    public static function systemEvents(): array
    {
        return [
            self::ERROR_OCCURRED->value,
            self::API_REQUEST->value,
            self::API_ERROR->value,
            self::PERFORMANCE_METRIC->value,
            self::AB_TEST_VIEW->value,
            self::AB_TEST_CONVERSION->value,
            self::FEATURE_FLAG_ENABLED->value,
            self::NOTIFICATION_SENT->value,
            self::NOTIFICATION_CLICKED->value,
            self::EMAIL_SENT->value,
            self::EMAIL_OPENED->value,
            self::EMAIL_CLICKED->value,
        ];
    }

    /**
     * Verifica se é um evento de conversão
     */
    public function isConversionEvent(): bool
    {
        return in_array($this->value, self::conversionEvents());
    }

    /**
     * Verifica se é um evento de engajamento
     */
    public function isEngagementEvent(): bool
    {
        return in_array($this->value, self::engagementEvents());
    }

    /**
     * Verifica se é um evento de comércio
     */
    public function isCommerceEvent(): bool
    {
        return in_array($this->value, self::commerceEvents());
    }

    /**
     * Verifica se é um evento de navegação
     */
    public function isNavigationEvent(): bool
    {
        return in_array($this->value, self::navigationEvents());
    }

    /**
     * Verifica se é um evento de usuário
     */
    public function isUserEvent(): bool
    {
        return in_array($this->value, self::userEvents());
    }

    /**
     * Verifica se é um evento de sistema
     */
    public function isSystemEvent(): bool
    {
        return in_array($this->value, self::systemEvents());
    }

    /**
     * Obtém categoria do evento
     */
    public function getCategory(): string
    {
        if ($this->isNavigationEvent()) {
            return 'navigation';
        } elseif ($this->isEngagementEvent()) {
            return 'engagement';
        } elseif ($this->isConversionEvent()) {
            return 'conversion';
        } elseif ($this->isCommerceEvent()) {
            return 'commerce';
        } elseif ($this->isUserEvent()) {
            return 'user';
        } elseif ($this->isSystemEvent()) {
            return 'system';
        } else {
            return 'custom';
        }
    }

    /**
     * Obtém peso do evento para scoring
     */
    public function getWeight(): float
    {
        return match ($this->getCategory()) {
            'conversion' => 10.0,
            'commerce' => 7.5,
            'engagement' => 5.0,
            'user' => 3.0,
            'navigation' => 1.0,
            'system' => 0.5,
            default => 1.0,
        };
    }

    /**
     * Verifica se o evento é válido
     */
    public static function isValid(string $eventType): bool
    {
        return in_array($eventType, self::all());
    }

    /**
     * Cria instância a partir de string
     */
    public static function fromString(string $eventType): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $eventType) {
                return $case;
            }
        }
        return null;
    }

    /**
     * Obtém descrição amigável do evento
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PAGE_VIEW => 'Visualização de página',
            self::BUTTON_CLICK => 'Clique em botão',
            self::FORM_SUBMIT => 'Envio de formulário',
            self::PURCHASE_COMPLETED => 'Compra concluída',
            self::ADD_TO_CART => 'Produto adicionado ao carrinho',
            self::USER_LOGIN => 'Login de usuário',
            self::ERROR_OCCURRED => 'Erro no sistema',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }
}
