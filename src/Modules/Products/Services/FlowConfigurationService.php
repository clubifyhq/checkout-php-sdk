<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de configuração de flows
 *
 * Responsável pela configuração avançada de flows de vendas:
 * - Templates de flow pré-configurados
 * - Configurações de navegação condicional
 * - Integração com ferramentas externas
 * - Configurações de tracking e analytics
 * - Automações e gatilhos
 * - Configurações de performance
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas configurações de flow
 * - O: Open/Closed - Extensível via tipos de configuração
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de configuração
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowConfigurationService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'flow_configuration';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Aplica template pré-configurado ao flow
     */
    public function applyTemplate(string $flowId, string $templateName, array $overrides = []): array
    {
        return $this->executeWithMetrics('apply_flow_template', function () use ($flowId, $templateName, $overrides) {
            $this->validateTemplateName($templateName);

            $response = $this->httpClient->post("/sales-flows/{$flowId}/apply-template", [
                'template_name' => $templateName,
                'overrides' => $overrides
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.template_applied', [
                'flow_id' => $flowId,
                'template_name' => $templateName,
                'overrides_count' => count($overrides)
            ]);

            $this->logger->info('Flow template applied successfully', [
                'flow_id' => $flowId,
                'template_name' => $templateName
            ]);

            return $flow;
        });
    }

    /**
     * Configura navegação condicional entre steps
     */
    public function configureConditionalNavigation(string $flowId, array $rules): array
    {
        return $this->executeWithMetrics('configure_conditional_navigation', function () use ($flowId, $rules) {
            $this->validateConditionalRules($rules);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/conditional-navigation", [
                'rules' => $rules
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.conditional_navigation_configured', [
                'flow_id' => $flowId,
                'rules_count' => count($rules)
            ]);

            return $flow;
        });
    }

    /**
     * Configura integrações externas
     */
    public function configureExternalIntegrations(string $flowId, array $integrations): array
    {
        return $this->executeWithMetrics('configure_external_integrations', function () use ($flowId, $integrations) {
            $this->validateIntegrations($integrations);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/integrations", [
                'integrations' => $integrations
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.integrations_configured', [
                'flow_id' => $flowId,
                'integrations_count' => count($integrations)
            ]);

            return $flow;
        });
    }

    /**
     * Configura tracking avançado
     */
    public function configureAdvancedTracking(string $flowId, array $trackingConfig): array
    {
        return $this->executeWithMetrics('configure_advanced_tracking', function () use ($flowId, $trackingConfig) {
            $this->validateTrackingConfig($trackingConfig);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/tracking", [
                'tracking_config' => $trackingConfig
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.tracking_configured', [
                'flow_id' => $flowId,
                'tracking_providers' => array_keys($trackingConfig)
            ]);

            return $flow;
        });
    }

    /**
     * Configura automações e gatilhos
     */
    public function configureAutomations(string $flowId, array $automations): array
    {
        return $this->executeWithMetrics('configure_flow_automations', function () use ($flowId, $automations) {
            $this->validateAutomations($automations);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/automations", [
                'automations' => $automations
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.automations_configured', [
                'flow_id' => $flowId,
                'automations_count' => count($automations)
            ]);

            return $flow;
        });
    }

    /**
     * Configura otimizações de performance
     */
    public function configurePerformanceOptimizations(string $flowId, array $optimizations): array
    {
        return $this->executeWithMetrics('configure_performance_optimizations', function () use ($flowId, $optimizations) {
            $this->validatePerformanceConfig($optimizations);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/performance", [
                'optimizations' => $optimizations
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.performance_configured', [
                'flow_id' => $flowId,
                'optimizations' => array_keys($optimizations)
            ]);

            return $flow;
        });
    }

    /**
     * Configura split testing avançado
     */
    public function configureSplitTesting(string $flowId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_split_testing', function () use ($flowId, $testConfig) {
            $this->validateSplitTestConfig($testConfig);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/split-testing", [
                'test_config' => $testConfig
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.split_testing_configured', [
                'flow_id' => $flowId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $flow;
        });
    }

    /**
     * Configura personalizações dinâmicas
     */
    public function configureDynamicPersonalization(string $flowId, array $personalizationRules): array
    {
        return $this->executeWithMetrics('configure_dynamic_personalization', function () use ($flowId, $personalizationRules) {
            $this->validatePersonalizationRules($personalizationRules);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/personalization", [
                'personalization_rules' => $personalizationRules
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.personalization_configured', [
                'flow_id' => $flowId,
                'rules_count' => count($personalizationRules)
            ]);

            return $flow;
        });
    }

    /**
     * Configura fallback strategies
     */
    public function configureFallbackStrategies(string $flowId, array $fallbackStrategies): array
    {
        return $this->executeWithMetrics('configure_fallback_strategies', function () use ($flowId, $fallbackStrategies) {
            $this->validateFallbackStrategies($fallbackStrategies);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/fallback-strategies", [
                'fallback_strategies' => $fallbackStrategies
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.fallback_strategies_configured', [
                'flow_id' => $flowId,
                'strategies_count' => count($fallbackStrategies)
            ]);

            return $flow;
        });
    }

    /**
     * Lista templates disponíveis
     */
    public function listAvailableTemplates(): array
    {
        return $this->executeWithMetrics('list_flow_templates', function () {
            $response = $this->httpClient->get('/sales-flows/templates');
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém configuração completa do flow
     */
    public function getCompleteConfiguration(string $flowId): array
    {
        return $this->executeWithMetrics('get_complete_flow_configuration', function () use ($flowId) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/complete-configuration");
            return $response->getData() ?? [];
        });
    }

    /**
     * Exporta configuração do flow
     */
    public function exportConfiguration(string $flowId): array
    {
        return $this->executeWithMetrics('export_flow_configuration', function () use ($flowId) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/export-configuration");
            return $response->getData() ?? [];
        });
    }

    /**
     * Importa configuração para um flow
     */
    public function importConfiguration(string $flowId, array $configuration): array
    {
        return $this->executeWithMetrics('import_flow_configuration', function () use ($flowId, $configuration) {
            $this->validateImportConfiguration($configuration);

            $response = $this->httpClient->post("/sales-flows/{$flowId}/import-configuration", [
                'configuration' => $configuration
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.configuration_imported', [
                'flow_id' => $flowId,
                'configuration_keys' => array_keys($configuration)
            ]);

            return $flow;
        });
    }

    /**
     * Clona configuração de um flow para outro
     */
    public function cloneConfiguration(string $sourceFlowId, string $targetFlowId, array $overrides = []): array
    {
        return $this->executeWithMetrics('clone_flow_configuration', function () use ($sourceFlowId, $targetFlowId, $overrides) {
            $response = $this->httpClient->post("/sales-flows/{$targetFlowId}/clone-configuration", [
                'source_flow_id' => $sourceFlowId,
                'overrides' => $overrides
            ]);

            $flow = $response->getData();

            // Invalidar cache dos flows afetados
            $this->invalidateFlowCache($sourceFlowId);
            $this->invalidateFlowCache($targetFlowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.configuration_cloned', [
                'source_flow_id' => $sourceFlowId,
                'target_flow_id' => $targetFlowId,
                'overrides_count' => count($overrides)
            ]);

            return $flow;
        });
    }

    /**
     * Reseta configuração do flow para padrões
     */
    public function resetToDefaults(string $flowId, array $preserveSettings = []): array
    {
        return $this->executeWithMetrics('reset_flow_to_defaults', function () use ($flowId, $preserveSettings) {
            $response = $this->httpClient->post("/sales-flows/{$flowId}/reset-to-defaults", [
                'preserve_settings' => $preserveSettings
            ]);

            $flow = $response->getData();

            // Invalidar cache do flow
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('flow_configuration.reset_to_defaults', [
                'flow_id' => $flowId,
                'preserved_settings' => $preserveSettings
            ]);

            return $flow;
        });
    }

    /**
     * Invalida cache do flow
     */
    private function invalidateFlowCache(string $flowId): void
    {
        $this->cache->delete($this->getCacheKey("sales_flow:{$flowId}"));
        $this->cache->delete($this->getCacheKey("flow_configuration:{$flowId}"));
    }

    /**
     * Valida nome do template
     */
    private function validateTemplateName(string $templateName): void
    {
        $allowedTemplates = [
            'lead_generation', 'product_sales', 'subscription', 'webinar_registration',
            'course_enrollment', 'consultation_booking', 'event_registration', 'survey_completion'
        ];

        if (!in_array($templateName, $allowedTemplates)) {
            throw new ValidationException("Invalid template name: {$templateName}");
        }
    }

    /**
     * Valida regras condicionais
     */
    private function validateConditionalRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['condition']) || !isset($rule['action'])) {
                throw new ValidationException('Invalid conditional rule format');
            }

            $allowedConditions = [
                'user_segment', 'purchase_history', 'time_on_page', 'device_type',
                'traffic_source', 'geographic_location', 'custom_variable'
            ];

            if (!in_array($rule['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid condition: {$rule['condition']}");
            }

            $allowedActions = [
                'redirect_to_step', 'skip_step', 'show_popup', 'apply_discount',
                'send_notification', 'trigger_automation'
            ];

            if (!in_array($rule['action'], $allowedActions)) {
                throw new ValidationException("Invalid action: {$rule['action']}");
            }
        }
    }

    /**
     * Valida configurações de integração
     */
    private function validateIntegrations(array $integrations): void
    {
        $allowedIntegrations = [
            'google_analytics', 'facebook_pixel', 'google_ads', 'mailchimp',
            'hubspot', 'zapier', 'webhook', 'custom_api'
        ];

        foreach ($integrations as $name => $config) {
            if (!in_array($name, $allowedIntegrations)) {
                throw new ValidationException("Invalid integration: {$name}");
            }

            if (!is_array($config)) {
                throw new ValidationException("Integration config must be an array for: {$name}");
            }
        }
    }

    /**
     * Valida configurações de tracking
     */
    private function validateTrackingConfig(array $config): void
    {
        $allowedProviders = [
            'google_analytics', 'facebook_pixel', 'google_tag_manager',
            'hotjar', 'mixpanel', 'amplitude', 'custom_tracking'
        ];

        foreach ($config as $provider => $settings) {
            if (!in_array($provider, $allowedProviders)) {
                throw new ValidationException("Invalid tracking provider: {$provider}");
            }

            if (!is_array($settings)) {
                throw new ValidationException("Tracking settings must be an array for: {$provider}");
            }
        }
    }

    /**
     * Valida configurações de automação
     */
    private function validateAutomations(array $automations): void
    {
        foreach ($automations as $automation) {
            if (!is_array($automation) || !isset($automation['trigger']) || !isset($automation['action'])) {
                throw new ValidationException('Invalid automation format');
            }

            $allowedTriggers = [
                'step_enter', 'step_exit', 'form_submit', 'time_based',
                'scroll_percentage', 'exit_intent', 'inactivity'
            ];

            if (!in_array($automation['trigger'], $allowedTriggers)) {
                throw new ValidationException("Invalid automation trigger: {$automation['trigger']}");
            }
        }
    }

    /**
     * Valida configurações de performance
     */
    private function validatePerformanceConfig(array $config): void
    {
        $allowedOptimizations = [
            'lazy_loading', 'image_compression', 'css_minification',
            'js_minification', 'caching_strategy', 'cdn_optimization'
        ];

        foreach ($config as $optimization => $settings) {
            if (!in_array($optimization, $allowedOptimizations)) {
                throw new ValidationException("Invalid performance optimization: {$optimization}");
            }
        }
    }

    /**
     * Valida configurações de split testing
     */
    private function validateSplitTestConfig(array $config): void
    {
        $required = ['name', 'variants', 'traffic_allocation'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for split test configuration");
            }
        }

        if (!is_array($config['variants']) || count($config['variants']) < 2) {
            throw new ValidationException('Split test must have at least 2 variants');
        }

        if (!is_array($config['traffic_allocation']) || array_sum($config['traffic_allocation']) !== 100) {
            throw new ValidationException('Traffic allocation must sum to 100%');
        }
    }

    /**
     * Valida regras de personalização
     */
    private function validatePersonalizationRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['criteria']) || !isset($rule['personalization'])) {
                throw new ValidationException('Invalid personalization rule format');
            }

            $allowedCriteria = [
                'user_behavior', 'purchase_history', 'demographic',
                'geographic', 'device', 'traffic_source'
            ];

            if (!in_array($rule['criteria'], $allowedCriteria)) {
                throw new ValidationException("Invalid personalization criteria: {$rule['criteria']}");
            }
        }
    }

    /**
     * Valida estratégias de fallback
     */
    private function validateFallbackStrategies(array $strategies): void
    {
        foreach ($strategies as $strategy) {
            if (!is_array($strategy) || !isset($strategy['condition']) || !isset($strategy['fallback_action'])) {
                throw new ValidationException('Invalid fallback strategy format');
            }

            $allowedConditions = [
                'payment_failure', 'form_error', 'api_timeout',
                'validation_error', 'external_service_down'
            ];

            if (!in_array($strategy['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid fallback condition: {$strategy['condition']}");
            }
        }
    }

    /**
     * Valida configuração de importação
     */
    private function validateImportConfiguration(array $configuration): void
    {
        $allowedSections = [
            'navigation', 'integrations', 'tracking', 'automations',
            'performance', 'personalization', 'fallback_strategies'
        ];

        foreach ($configuration as $section => $config) {
            if (!in_array($section, $allowedSections)) {
                throw new ValidationException("Invalid configuration section: {$section}");
            }

            if (!is_array($config)) {
                throw new ValidationException("Configuration section '{$section}' must be an array");
            }
        }
    }
}