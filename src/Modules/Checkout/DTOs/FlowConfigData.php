<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para configuração de flow de checkout
 *
 * Representa a configuração completa de um flow de checkout:
 * - Estrutura de steps e navegação
 * - Configurações de validação
 * - Regras condicionais e branching
 * - Analytics e tracking
 * - Personalização visual
 * - Otimizações multi-dispositivo
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas configuração de flow
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowConfigData extends BaseData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly array $steps,
        public readonly bool $active = true,
        public readonly ?string $description = null,
        public readonly ?array $config = null,
        public readonly ?array $validationRules = null,
        public readonly ?array $conditionalSteps = null,
        public readonly ?array $skipRules = null,
        public readonly ?array $branchingLogic = null,
        public readonly ?array $analytics = null,
        public readonly ?array $uiConfig = null,
        public readonly ?array $deviceOptimization = null,
        public readonly ?array $abTestConfig = null,
        public readonly ?string $version = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $offerId = null,
        public readonly ?array $metadata = null,
        public readonly ?\DateTime $createdAt = null,
        public readonly ?\DateTime $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para configuração de flow
     */
    protected function rules(): array
    {
        return [
            'id' => 'required|string|min:1|max:100',
            'name' => 'required|string|min:1|max:255',
            'type' => 'required|string|in:standard,express,custom,mobile,funnel',
            'steps' => 'required|array|min:3',
            'version' => 'string|max:20',
        ];
    }

    /**
     * Converte para array completo
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'steps' => $this->steps,
            'active' => $this->active,
            'description' => $this->description,
            'config' => $this->config,
            'validation_rules' => $this->validationRules,
            'conditional_steps' => $this->conditionalSteps,
            'skip_rules' => $this->skipRules,
            'branching_logic' => $this->branchingLogic,
            'analytics' => $this->analytics,
            'ui_config' => $this->uiConfig,
            'device_optimization' => $this->deviceOptimization,
            'ab_test_config' => $this->abTestConfig,
            'version' => $this->version,
            'organization_id' => $this->organizationId,
            'offer_id' => $this->offerId,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            type: $data['type'] ?? 'standard',
            steps: $data['steps'] ?? [],
            active: (bool)($data['active'] ?? true),
            description: $data['description'] ?? null,
            config: $data['config'] ?? null,
            validationRules: $data['validation_rules'] ?? null,
            conditionalSteps: $data['conditional_steps'] ?? null,
            skipRules: $data['skip_rules'] ?? null,
            branchingLogic: $data['branching_logic'] ?? null,
            analytics: $data['analytics'] ?? null,
            uiConfig: $data['ui_config'] ?? null,
            deviceOptimization: $data['device_optimization'] ?? null,
            abTestConfig: $data['ab_test_config'] ?? null,
            version: $data['version'] ?? null,
            organizationId: $data['organization_id'] ?? null,
            offerId: $data['offer_id'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }

    /**
     * Verifica se o flow está ativo
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Verifica se é flow mobile otimizado
     */
    public function isMobileOptimized(): bool
    {
        return $this->type === 'mobile' ||
               ($this->deviceOptimization !== null &&
                ($this->deviceOptimization['mobile_first'] ?? false));
    }

    /**
     * Verifica se é flow express
     */
    public function isExpress(): bool
    {
        return $this->type === 'express' || count($this->steps) <= 3;
    }

    /**
     * Verifica se tem A/B testing configurado
     */
    public function hasAbTesting(): bool
    {
        return $this->abTestConfig !== null &&
               ($this->abTestConfig['enabled'] ?? false);
    }

    /**
     * Verifica se tem analytics habilitado
     */
    public function hasAnalytics(): bool
    {
        return $this->analytics !== null &&
               ($this->analytics['enabled'] ?? true);
    }

    /**
     * Verifica se tem lógica de branching
     */
    public function hasBranching(): bool
    {
        return $this->branchingLogic !== null && !empty($this->branchingLogic);
    }

    /**
     * Verifica se tem steps condicionais
     */
    public function hasConditionalSteps(): bool
    {
        return $this->conditionalSteps !== null && !empty($this->conditionalSteps);
    }

    /**
     * Obtém step por índice
     */
    public function getStep(int $index): ?array
    {
        return $this->steps[$index] ?? null;
    }

    /**
     * Obtém step por nome
     */
    public function getStepByName(string $stepName): ?array
    {
        foreach ($this->steps as $step) {
            if (($step['name'] ?? '') === $stepName) {
                return $step;
            }
        }
        return null;
    }

    /**
     * Obtém total de steps
     */
    public function getStepCount(): int
    {
        return count($this->steps);
    }

    /**
     * Verifica se step pode ser pulado
     */
    public function canSkipStep(string $stepName): bool
    {
        if ($this->skipRules === null) {
            return false;
        }

        return in_array($stepName, $this->skipRules['allowed_skips'] ?? []);
    }

    /**
     * Obtém regras de validação para step
     */
    public function getStepValidationRules(string $stepName): array
    {
        if ($this->validationRules === null) {
            return [];
        }

        return $this->validationRules[$stepName] ?? [];
    }

    /**
     * Obtém configuração de UI
     */
    public function getUiConfig(): array
    {
        return $this->uiConfig ?? [
            'theme' => 'default',
            'layout' => 'single-column',
            'animations' => true,
            'progress_bar' => true,
            'step_indicators' => true
        ];
    }

    /**
     * Obtém configuração de analytics
     */
    public function getAnalyticsConfig(): array
    {
        if ($this->analytics === null) {
            return [
                'enabled' => true,
                'track_steps' => true,
                'track_errors' => true,
                'track_timing' => true,
                'track_abandonment' => true
            ];
        }

        return $this->analytics;
    }

    /**
     * Obtém configuração de A/B testing
     */
    public function getAbTestConfig(): array
    {
        return $this->abTestConfig ?? [
            'enabled' => false,
            'variants' => [],
            'traffic_split' => 50
        ];
    }

    /**
     * Obtém otimizações por dispositivo
     */
    public function getDeviceOptimization(): array
    {
        return $this->deviceOptimization ?? [
            'mobile_first' => true,
            'desktop_enhancements' => [],
            'tablet_optimizations' => [],
            'responsive_breakpoints' => [
                'mobile' => '768px',
                'tablet' => '1024px',
                'desktop' => '1200px'
            ]
        ];
    }

    /**
     * Verifica se flow está pronto para produção
     */
    public function isProductionReady(): bool
    {
        // Verificações básicas
        if (!$this->active || empty($this->steps)) {
            return false;
        }

        // Verifica se tem steps obrigatórios
        $requiredSteps = ['customer_info', 'payment_info', 'order_confirmation'];
        $stepNames = array_column($this->steps, 'name');

        foreach ($requiredSteps as $required) {
            if (!in_array($required, $stepNames)) {
                return false;
            }
        }

        // Verifica se validações estão configuradas
        if ($this->validationRules === null) {
            return false;
        }

        return true;
    }

    /**
     * Calcula complexidade do flow
     */
    public function getComplexityScore(): int
    {
        $score = 0;

        // Base: número de steps
        $score += count($this->steps) * 2;

        // Adiciona por features avançadas
        if ($this->hasBranching()) {
            $score += 10;
        }

        if ($this->hasConditionalSteps()) {
            $score += 8;
        }

        if ($this->hasAbTesting()) {
            $score += 5;
        }

        if ($this->validationRules !== null) {
            $score += count($this->validationRules) * 2;
        }

        return $score;
    }

    /**
     * Obtém nível de complexidade
     */
    public function getComplexityLevel(): string
    {
        $score = $this->getComplexityScore();

        if ($score <= 20) {
            return 'simple';
        } elseif ($score <= 40) {
            return 'moderate';
        } elseif ($score <= 60) {
            return 'complex';
        } else {
            return 'very_complex';
        }
    }

    /**
     * Obtém recomendações de otimização
     */
    public function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        if (count($this->steps) > 7) {
            $recommendations[] = 'Consider reducing the number of steps for better conversion';
        }

        if (!$this->isMobileOptimized()) {
            $recommendations[] = 'Enable mobile optimization for better mobile experience';
        }

        if (!$this->hasAnalytics()) {
            $recommendations[] = 'Enable analytics to track performance and conversions';
        }

        if ($this->getComplexityLevel() === 'very_complex') {
            $recommendations[] = 'Flow is very complex, consider simplifying for better UX';
        }

        return $recommendations;
    }

    /**
     * Valida compatibilidade com tipo
     */
    public function validateTypeCompatibility(): array
    {
        $errors = [];

        switch ($this->type) {
            case 'express':
                if (count($this->steps) > 3) {
                    $errors[] = 'Express flows should have 3 or fewer steps';
                }
                break;

            case 'mobile':
                if (!$this->isMobileOptimized()) {
                    $errors[] = 'Mobile flows must have mobile optimization enabled';
                }
                break;

            case 'funnel':
                if (!$this->hasAnalytics()) {
                    $errors[] = 'Funnel flows require analytics to be enabled';
                }
                break;
        }

        return $errors;
    }

    /**
     * Exporta configuração para JSON
     */
    public function exportConfig(): array
    {
        return [
            'version' => $this->version ?? '1.0.0',
            'metadata' => [
                'name' => $this->name,
                'type' => $this->type,
                'description' => $this->description,
                'created_at' => $this->createdAt?->format('c'),
                'complexity_level' => $this->getComplexityLevel()
            ],
            'flow' => [
                'steps' => $this->steps,
                'config' => $this->config,
                'validation_rules' => $this->validationRules,
                'conditional_steps' => $this->conditionalSteps,
                'skip_rules' => $this->skipRules,
                'branching_logic' => $this->branchingLogic
            ],
            'features' => [
                'analytics' => $this->getAnalyticsConfig(),
                'ui_config' => $this->getUiConfig(),
                'device_optimization' => $this->getDeviceOptimization(),
                'ab_testing' => $this->getAbTestConfig()
            ]
        ];
    }

    /**
     * Clona flow com modificações
     */
    public function clone(array $modifications = []): self
    {
        $data = array_merge($this->toArray(), $modifications);

        // Gera novo ID se não especificado
        if (!isset($modifications['id'])) {
            $data['id'] = $this->id . '_copy_' . time();
        }

        return self::fromArray($data);
    }
}
