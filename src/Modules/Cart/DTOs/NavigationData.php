<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO para dados de Navegação de Fluxos
 *
 * Representa uma sessão de navegação de fluxo de ofertas,
 * incluindo progresso, steps, configurações e analytics.
 *
 * Funcionalidades:
 * - Controle de progresso de navegação
 * - Gerenciamento de steps/etapas
 * - Dados de contexto e configuração
 * - Analytics de fluxo
 * - Controle de tempo e expiração
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de navegação
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Interface específica de navegação
 * - D: Dependency Inversion - Depende de abstrações
 */
class NavigationData extends BaseData
{
    // Identificadores
    public ?string $navigation_id = null;
    public ?string $offer_id = null;
    public ?string $session_id = null;
    public ?string $customer_id = null;
    public ?string $cart_id = null;

    // Dados de progresso
    public ?int $current_step = null;
    public ?int $total_steps = null;
    public ?string $status = null;
    public ?bool $is_complete = null;

    // Configuração de fluxo
    public ?array $flow_config = null;
    public ?array $steps_config = null;
    public ?array $context = null;

    // Histórico e dados de steps
    public ?array $step_history = null;
    public ?array $current_step_data = null;
    public ?array $completed_steps = null;

    // Analytics e métricas
    public ?array $analytics_data = null;
    public ?int $total_time = null;
    public ?array $step_timings = null;

    // Dados de conversão
    public ?float $conversion_rate = null;
    public ?float $revenue = null;
    public ?string $conversion_result = null;

    // Controle de abandono
    public ?string $abandon_reason = null;
    public ?int $abandoned_at_step = null;

    // Metadados
    public ?array $metadata = null;
    public ?array $user_data = null;

    // Timestamps
    public ?string $started_at = null;
    public ?string $last_activity_at = null;
    public ?string $completed_at = null;
    public ?string $abandoned_at = null;
    public ?string $expires_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'navigation_id' => ['required', 'string'],
            'offer_id' => ['required', 'string'],
            'session_id' => ['required', 'string'],
            'customer_id' => ['string'],
            'cart_id' => ['string'],
            'current_step' => ['integer', ['min', 1]],
            'total_steps' => ['integer', ['min', 1]],
            'status' => ['string', ['in', ['active', 'completed', 'abandoned', 'expired', 'paused']]],
            'is_complete' => ['boolean'],
            'flow_config' => ['array'],
            'steps_config' => ['array'],
            'context' => ['array'],
            'step_history' => ['array'],
            'current_step_data' => ['array'],
            'completed_steps' => ['array'],
            'analytics_data' => ['array'],
            'total_time' => ['integer', ['min', 0]],
            'step_timings' => ['array'],
            'conversion_rate' => ['numeric', ['min', 0], ['max', 100]],
            'revenue' => ['numeric', ['min', 0]],
            'conversion_result' => ['string'],
            'abandon_reason' => ['string'],
            'abandoned_at_step' => ['integer', ['min', 1]],
            'metadata' => ['array'],
            'user_data' => ['array'],
            'started_at' => ['date'],
            'last_activity_at' => ['date'],
            'completed_at' => ['date'],
            'abandoned_at' => ['date'],
            'expires_at' => ['date']
        ];
    }

    // ===========================================
    // MÉTODOS DE PROGRESSO E NAVEGAÇÃO
    // ===========================================

    /**
     * Obtém progresso da navegação em percentual
     */
    public function getProgressPercentage(): float
    {
        if (($this->total_steps ?? 0) <= 0) {
            return 0.0;
        }

        $currentStep = $this->current_step ?? 1;
        return min(100.0, ($currentStep / $this->total_steps) * 100);
    }

    /**
     * Obtém steps completados
     */
    public function getCompletedSteps(): array
    {
        return $this->completed_steps ?? [];
    }

    /**
     * Obtém steps restantes
     */
    public function getRemainingSteps(): int
    {
        $currentStep = $this->current_step ?? 1;
        $totalSteps = $this->total_steps ?? 0;

        return max(0, $totalSteps - $currentStep + 1);
    }

    /**
     * Verifica se pode prosseguir para próximo step
     */
    public function canProceedToNext(): bool
    {
        if ($this->is_complete) {
            return false;
        }

        if ($this->status !== 'active') {
            return false;
        }

        $currentStep = $this->current_step ?? 1;
        $totalSteps = $this->total_steps ?? 0;

        return $currentStep < $totalSteps;
    }

    /**
     * Verifica se navegação pode ser completada
     */
    public function canComplete(): bool
    {
        $currentStep = $this->current_step ?? 1;
        $totalSteps = $this->total_steps ?? 0;

        return $currentStep >= $totalSteps && $this->status === 'active';
    }

    /**
     * Avança para próximo step
     */
    public function advanceToNextStep(): self
    {
        if (!$this->canProceedToNext()) {
            throw new \InvalidStateException('Cannot proceed to next step');
        }

        // Adiciona step atual ao histórico
        $this->addStepToHistory($this->current_step, $this->current_step_data ?? []);

        // Avança step
        $this->current_step = ($this->current_step ?? 1) + 1;
        $this->last_activity_at = date('Y-m-d H:i:s');

        // Atualiza dados
        $this->data['current_step'] = $this->current_step;
        $this->data['last_activity_at'] = $this->last_activity_at;

        // Verifica se completou
        if ($this->current_step >= ($this->total_steps ?? 0)) {
            $this->markAsCompleted();
        }

        return $this;
    }

    /**
     * Retorna para step específico
     */
    public function resetToStep(int $stepNumber): array
    {
        if ($stepNumber < 1 || $stepNumber > ($this->total_steps ?? 0)) {
            throw new \InvalidArgumentException('Invalid step number');
        }

        $this->current_step = $stepNumber;
        $this->is_complete = false;
        $this->status = 'active';
        $this->last_activity_at = date('Y-m-d H:i:s');

        // Remove steps posteriores do histórico
        if (is_array($this->step_history)) {
            $this->step_history = array_filter($this->step_history, function ($historyItem) use ($stepNumber) {
                return ($historyItem['step_number'] ?? 0) < $stepNumber;
            });
        }

        // Atualiza dados
        $this->data = array_merge($this->data, [
            'current_step' => $this->current_step,
            'is_complete' => $this->is_complete,
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at,
            'step_history' => $this->step_history
        ]);

        return $this->toArray();
    }

    // ===========================================
    // MÉTODOS DE STEPS E HISTÓRICO
    // ===========================================

    /**
     * Obtém dados do step atual
     */
    public function getCurrentStepData(): array
    {
        return $this->current_step_data ?? [];
    }

    /**
     * Define dados do step atual
     */
    public function setCurrentStepData(array $stepData): self
    {
        $this->current_step_data = $stepData;
        $this->data['current_step_data'] = $stepData;
        return $this;
    }

    /**
     * Obtém dados do próximo step
     */
    public function getNextStepData(): ?array
    {
        if (!$this->canProceedToNext()) {
            return null;
        }

        $nextStep = ($this->current_step ?? 1) + 1;
        $stepsConfig = $this->steps_config ?? [];

        return $stepsConfig[$nextStep] ?? null;
    }

    /**
     * Obtém configuração de step específico
     */
    public function getStepConfig(int $stepNumber): ?array
    {
        $stepsConfig = $this->steps_config ?? [];
        return $stepsConfig[$stepNumber] ?? null;
    }

    /**
     * Adiciona step ao histórico
     */
    public function addStepToHistory(int $stepNumber, array $stepData): self
    {
        if (!is_array($this->step_history)) {
            $this->step_history = [];
        }

        $this->step_history[] = [
            'step_number' => $stepNumber,
            'step_data' => $stepData,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s')
        ];

        $this->data['step_history'] = $this->step_history;

        return $this;
    }

    /**
     * Obtém histórico de steps
     */
    public function getStepHistory(): array
    {
        return $this->step_history ?? [];
    }

    // ===========================================
    // MÉTODOS DE TIMING E ANALYTICS
    // ===========================================

    /**
     * Obtém tempo total de navegação em segundos
     */
    public function getTotalTime(): int
    {
        if ($this->total_time !== null) {
            return $this->total_time;
        }

        if (!$this->started_at) {
            return 0;
        }

        $startTime = strtotime($this->started_at);
        $endTime = $this->completed_at ? strtotime($this->completed_at) : time();

        return $endTime - $startTime;
    }

    /**
     * Obtém timings por step
     */
    public function getStepTimings(): array
    {
        return $this->step_timings ?? [];
    }

    /**
     * Calcula timing médio por step
     */
    public function getAverageStepTime(): float
    {
        $timings = $this->getStepTimings();

        if (empty($timings)) {
            return 0.0;
        }

        $total = array_sum($timings);
        return $total / count($timings);
    }

    /**
     * Obtém step com maior tempo
     */
    public function getSlowestStep(): ?array
    {
        $timings = $this->getStepTimings();

        if (empty($timings)) {
            return null;
        }

        $slowestStep = array_keys($timings, max($timings))[0];

        return [
            'step_number' => $slowestStep,
            'time' => $timings[$slowestStep]
        ];
    }

    // ===========================================
    // MÉTODOS DE STATUS E CONTROLE
    // ===========================================

    /**
     * Marca navegação como completada
     */
    public function markAsCompleted(): self
    {
        $this->is_complete = true;
        $this->status = 'completed';
        $this->completed_at = date('Y-m-d H:i:s');

        if (!$this->total_time) {
            $this->total_time = $this->getTotalTime();
        }

        $this->data = array_merge($this->data, [
            'is_complete' => $this->is_complete,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'total_time' => $this->total_time
        ]);

        return $this;
    }

    /**
     * Marca navegação como abandonada
     */
    public function markAsAbandoned(string $reason = null): self
    {
        $this->status = 'abandoned';
        $this->is_complete = false;
        $this->abandoned_at = date('Y-m-d H:i:s');
        $this->abandon_reason = $reason;
        $this->abandoned_at_step = $this->current_step;

        $this->data = array_merge($this->data, [
            'status' => $this->status,
            'is_complete' => $this->is_complete,
            'abandoned_at' => $this->abandoned_at,
            'abandon_reason' => $this->abandon_reason,
            'abandoned_at_step' => $this->abandoned_at_step
        ]);

        return $this;
    }

    /**
     * Pausa navegação
     */
    public function pause(): self
    {
        $this->status = 'paused';
        $this->last_activity_at = date('Y-m-d H:i:s');

        $this->data = array_merge($this->data, [
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at
        ]);

        return $this;
    }

    /**
     * Resume navegação pausada
     */
    public function resume(): self
    {
        if ($this->status !== 'paused') {
            throw new \InvalidStateException('Navigation is not paused');
        }

        $this->status = 'active';
        $this->last_activity_at = date('Y-m-d H:i:s');

        $this->data = array_merge($this->data, [
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at
        ]);

        return $this;
    }

    /**
     * Verifica se navegação está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se navegação foi abandonada
     */
    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    /**
     * Verifica se navegação está pausada
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    // ===========================================
    // MÉTODOS DE CONTEXTO E DADOS
    // ===========================================

    /**
     * Obtém contexto da navegação
     */
    public function getContext(): array
    {
        return $this->context ?? [];
    }

    /**
     * Define contexto da navegação
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        $this->data['context'] = $context;
        return $this;
    }

    /**
     * Obtém valor do contexto
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Define valor no contexto
     */
    public function setContextValue(string $key, mixed $value): self
    {
        if (!is_array($this->context)) {
            $this->context = [];
        }

        $this->context[$key] = $value;
        $this->data['context'] = $this->context;

        return $this;
    }

    /**
     * Obtém dados do usuário
     */
    public function getUserData(): array
    {
        return $this->user_data ?? [];
    }

    /**
     * Define dados do usuário
     */
    public function setUserData(array $userData): self
    {
        $this->user_data = $userData;
        $this->data['user_data'] = $userData;
        return $this;
    }

    // ===========================================
    // MÉTODOS DE ANALYTICS E CONVERSÃO
    // ===========================================

    /**
     * Obtém dados de analytics
     */
    public function getAnalyticsData(): array
    {
        return $this->analytics_data ?? [];
    }

    /**
     * Define dados de conversão
     */
    public function setConversionData(float $revenue, float $conversionRate, string $result): self
    {
        $this->revenue = $revenue;
        $this->conversion_rate = $conversionRate;
        $this->conversion_result = $result;

        $this->data = array_merge($this->data, [
            'revenue' => $this->revenue,
            'conversion_rate' => $this->conversion_rate,
            'conversion_result' => $this->conversion_result
        ]);

        return $this;
    }

    /**
     * Verifica se houve conversão
     */
    public function hasConversion(): bool
    {
        return !empty($this->conversion_result) && $this->revenue > 0;
    }

    // ===========================================
    // MÉTODOS DE RESUMO E RELATÓRIO
    // ===========================================

    /**
     * Obtém resumo da navegação
     */
    public function getSummary(): array
    {
        return [
            'navigation_id' => $this->navigation_id,
            'offer_id' => $this->offer_id,
            'status' => $this->status,
            'progress' => [
                'current_step' => $this->current_step,
                'total_steps' => $this->total_steps,
                'percentage' => $this->getProgressPercentage(),
                'is_complete' => $this->is_complete
            ],
            'timing' => [
                'started_at' => $this->started_at,
                'total_time' => $this->getTotalTime(),
                'average_step_time' => $this->getAverageStepTime(),
                'completed_at' => $this->completed_at
            ],
            'conversion' => [
                'has_conversion' => $this->hasConversion(),
                'revenue' => $this->revenue,
                'conversion_rate' => $this->conversion_rate,
                'result' => $this->conversion_result
            ],
            'abandonment' => [
                'is_abandoned' => $this->isAbandoned(),
                'abandoned_at_step' => $this->abandoned_at_step,
                'reason' => $this->abandon_reason
            ]
        ];
    }

    /**
     * Cria instância para nova navegação
     */
    public static function forNewNavigation(string $offerId, string $sessionId, array $flowConfig): self
    {
        return new self([
            'navigation_id' => uniqid('nav_'),
            'offer_id' => $offerId,
            'session_id' => $sessionId,
            'current_step' => 1,
            'total_steps' => count($flowConfig['steps'] ?? []),
            'status' => 'active',
            'is_complete' => false,
            'flow_config' => $flowConfig,
            'steps_config' => $flowConfig['steps'] ?? [],
            'context' => $flowConfig['context'] ?? [],
            'step_history' => [],
            'analytics_data' => [],
            'started_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s')
        ]);
    }
}