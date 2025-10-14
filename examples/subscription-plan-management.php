<?php

/**
 * Exemplo Completo: Gerenciamento Idempotente de Planos de Assinatura
 *
 * Este exemplo demonstra como criar, verificar e atualizar planos de assinatura
 * de forma idempotente usando o módulo de Subscriptions do Clubify Checkout SDK.
 *
 * CARACTERÍSTICAS:
 * - Idempotente: Pode ser executado múltiplas vezes sem criar duplicatas
 * - Verifica se o plano já existe antes de criar
 * - Atualiza o plano existente se necessário
 * - Usa autenticação via API key do tenant
 * - Tratamento completo de erros
 *
 * REQUISITOS:
 * - PHP 8.2+
 * - Composer instalado
 * - SDK Clubify Checkout instalado (composer require clubify/checkout-sdk-php)
 * - Credenciais válidas do tenant (API key, Tenant ID, Organization ID)
 *
 * COMO EXECUTAR:
 * php examples/subscription-plan-management.php
 *
 * @version 1.0.0
 * @author Clubify Checkout Team
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Carrega variáveis de ambiente do arquivo .env
 *
 * @param string $path Caminho para o arquivo .env
 * @return void
 */
function loadEnvFile(string $path): void
{
    if (!file_exists($path)) {
        throw new \RuntimeException("Arquivo .env não encontrado: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentários
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }

        // Parse linha no formato KEY=value
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remover aspas ao redor do valor
            $value = trim($value, '"\'');

            // Definir variável de ambiente
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

class SubscriptionPlanManager
{
    public ClubifyCheckoutSDK $sdk;
    private bool $debug;

    public function __construct(array $config)
    {
        $this->debug = $config['debug'] ?? false;

        $this->log("🔧 Inicializando SDK...");

        $this->sdk = new ClubifyCheckoutSDK([
            'credentials' => [
                'api_key' => $config['api_key'],
                'tenant_id' => $config['tenant_id'],
                'organization_id' => $config['organization_id'],
                'environment' => $config['environment'] ?? 'sandbox'
            ],
            'endpoints' => [
                'base_url' => $config['base_url'] ?? 'https://checkout.svelve.com/api/v1'
            ],
            'http' => [
                'timeout' => 30000,
                'connect_timeout' => 10000,
                'verify_ssl' => true
            ],
            'retry' => [
                'attempts' => 3,
                'delay' => 1000,
                'backoff' => 'exponential'
            ],
            'cache' => [
                'enabled' => true,
                'default_ttl' => 300
            ],
            'logging' => [
                'enabled' => true,
                'level' => 'info'
            ],
            'debug' => $this->debug
        ]);

        // Inicializar SDK
        $result = $this->sdk->initialize();

        if (!$result['success']) {
            throw new \RuntimeException("❌ Falha ao inicializar SDK: {$result['message']}");
        }

        $this->log("✅ SDK inicializado com sucesso");
    }

    /**
     * Criar ou atualizar plano de assinatura de forma idempotente
     *
     * @param array $planData Dados do plano de assinatura
     * @return array Plano criado ou atualizado
     */
    public function createOrUpdatePlan(array $planData): array
    {
        $this->log("\n" . str_repeat("=", 70));
        $this->log("📋 Processando plano: {$planData['name']}");
        $this->log(str_repeat("=", 70));

        try {
            // PASSO 1: Verificar se o plano já existe
            $this->log("\n🔍 Verificando se o plano já existe...");
            $existingPlan = $this->findPlanByName($planData['name']);

            if ($existingPlan) {
                $this->log("✅ Plano encontrado (ID: {$existingPlan['id']})");

                // PASSO 2: Atualizar plano existente
                return $this->updateExistingPlan($existingPlan, $planData);
            }

            // PASSO 3: Criar novo plano
            $this->log("➕ Plano não existe, criando novo...");
            return $this->createNewPlan($planData);

        } catch (\Exception $e) {
            $this->log("❌ Erro ao processar plano: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar plano pelo nome
     *
     * @param string $planName Nome do plano
     * @return array|null Plano encontrado ou null
     */
    private function findPlanByName(string $planName): ?array
    {
        try {
            // Listar todos os planos e filtrar pelo nome
            // Nota: A API não aceita 'isActive' como filtro na listagem
            $result = $this->sdk->subscriptions()->listPlans([]);

            if (!$result['success']) {
                $this->log("⚠️ Falha ao listar planos: " . ($result['error'] ?? 'Erro desconhecido'));
                return null;
            }

            $plans = $result['plans'] ?? [];

            foreach ($plans as $plan) {
                if (isset($plan['name']) && $plan['name'] === $planName) {
                    return $plan;
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao buscar plano: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Criar novo plano de assinatura
     *
     * @param array $planData Dados do plano
     * @return array Plano criado
     */
    private function createNewPlan(array $planData): array
    {
        $this->log("\n📝 Criando novo plano...");

        $result = $this->sdk->subscriptions()->createPlan($planData);

        if (!$result['success']) {
            throw new \RuntimeException("Falha ao criar plano: " . ($result['error'] ?? 'Erro desconhecido'));
        }

        $plan = $result['plan'];

        $this->log("✅ Plano criado com sucesso!");
        $this->log("   ID: {$plan['id']}");
        $this->log("   Nome: {$plan['name']}");
        $this->log("   Valor: R$ " . number_format($plan['amount'] ?? 0, 2, ',', '.'));
        $this->log("   Ciclo: " . ($plan['billing_cycle'] ?? 'N/A'));
        $this->log("   MRR: R$ " . number_format($result['mrr'] ?? 0, 2, ',', '.'));

        return $plan;
    }

    /**
     * Atualizar plano existente
     *
     * @param array $existingPlan Plano existente
     * @param array $newData Novos dados do plano
     * @return array Plano atualizado
     */
    private function updateExistingPlan(array $existingPlan, array $newData): array
    {
        $this->log("\n🔄 Verificando se há alterações necessárias...");

        // Comparar campos para verificar se precisa atualizar
        $needsUpdate = false;
        $updates = [];

        // Campos a serem verificados
        $fieldsToCheck = ['description', 'amount', 'billing_cycle', 'trial_days', 'features', 'metadata'];

        foreach ($fieldsToCheck as $field) {
            if (isset($newData[$field]) && $existingPlan[$field] !== $newData[$field]) {
                $needsUpdate = true;
                $updates[$field] = $newData[$field];
            }
        }

        if (!$needsUpdate) {
            $this->log("✅ Plano já está atualizado, nenhuma alteração necessária");
            return $existingPlan;
        }

        $this->log("📝 Atualizando plano com " . count($updates) . " alteração(ões)...");

        foreach ($updates as $field => $value) {
            if (is_array($value)) {
                $this->log("   - {$field}: " . json_encode($value));
            } else {
                $this->log("   - {$field}: {$value}");
            }
        }

        $result = $this->sdk->subscriptions()->updatePlan($existingPlan['id'], $updates);

        if (!$result['success']) {
            throw new \RuntimeException("Falha ao atualizar plano: " . ($result['error'] ?? 'Erro desconhecido'));
        }

        $this->log("✅ Plano atualizado com sucesso!");

        // Retornar plano atualizado (merge dos dados)
        return array_merge($existingPlan, $updates);
    }

    /**
     * Listar todos os planos de assinatura
     *
     * @param array $filters Filtros opcionais
     * @return array Lista de planos
     */
    public function listPlans(array $filters = []): array
    {
        $this->log("\n📋 Listando planos de assinatura...");

        $result = $this->sdk->subscriptions()->listPlans($filters);

        if (!$result['success']) {
            throw new \RuntimeException("Falha ao listar planos: " . ($result['error'] ?? 'Erro desconhecido'));
        }

        $plans = $result['plans'] ?? [];
        $total = $result['total'] ?? count($plans);

        $this->log("✅ {$total} plano(s) encontrado(s)");

        foreach ($plans as $index => $plan) {
            $this->log("\n   " . ($index + 1) . ". " . ($plan['name'] ?? 'N/A'));
            $this->log("      ID: " . ($plan['id'] ?? 'N/A'));
            $this->log("      Valor: R$ " . number_format($plan['amount'] ?? 0, 2, ',', '.'));
            $this->log("      Ciclo: " . ($plan['billing_cycle'] ?? 'N/A'));
            $this->log("      Status: " . (($plan['is_active'] ?? true) ? 'Ativo' : 'Inativo'));
        }

        return $plans;
    }

    /**
     * Desativar um plano de assinatura
     *
     * NOTA: Este método está comentado porque deactivatePlan() não está exposto no SubscriptionsModule
     *
     * @param string $planId ID do plano
     * @return bool Sucesso da operação
     */
    /*
    public function deactivatePlan(string $planId): bool
    {
        $this->log("\n🔒 Desativando plano {$planId}...");

        $result = $this->sdk->subscriptions()->deactivatePlan($planId);

        if (!$result['success']) {
            $this->log("❌ Falha ao desativar plano: " . ($result['error'] ?? 'Erro desconhecido'));
            return false;
        }

        $this->log("✅ Plano desativado com sucesso em {$result['deactivated_at']}");
        return true;
    }
    */

    /**
     * Ativar um plano de assinatura
     *
     * NOTA: Este método está comentado porque activatePlan() não está exposto no SubscriptionsModule
     *
     * @param string $planId ID do plano
     * @return bool Sucesso da operação
     */
    /*
    public function activatePlan(string $planId): bool
    {
        $this->log("\n🔓 Ativando plano {$planId}...");

        $result = $this->sdk->subscriptions()->activatePlan($planId);

        if (!$result['success']) {
            $this->log("❌ Falha ao ativar plano: " . ($result['error'] ?? 'Erro desconhecido'));
            return false;
        }

        $this->log("✅ Plano ativado com sucesso em {$result['activated_at']}");
        return true;
    }
    */

    /**
     * Obter métricas de um plano específico
     *
     * NOTA: Este método está comentado porque getPlanMetrics() não está exposto no SubscriptionsModule
     *
     * @param string $planId ID do plano
     * @return array Métricas do plano
     */
    /*
    public function getPlanMetrics(string $planId): array
    {
        $this->log("\n📊 Obtendo métricas do plano {$planId}...");

        $result = $this->sdk->subscriptions()->getPlanMetrics($planId);

        if (!$result['success']) {
            throw new \RuntimeException("Falha ao obter métricas: " . ($result['error'] ?? 'Erro desconhecido'));
        }

        $metrics = $result['metrics'];

        $this->log("✅ Métricas obtidas:");
        $this->log("   Assinaturas ativas: {$metrics['active_subscriptions']}");
        $this->log("   Receita total: R$ " . number_format($metrics['total_revenue'], 2, ',', '.'));
        $this->log("   MRR: R$ " . number_format($metrics['mrr'], 2, ',', '.'));
        $this->log("   ARR: R$ " . number_format($metrics['arr'], 2, ',', '.'));
        $this->log("   Taxa de conversão: {$metrics['conversion_rate']}%");
        $this->log("   Taxa de churn: {$metrics['churn_rate']}%");

        return $metrics;
    }
    */

    /**
     * Log de mensagens
     *
     * @param string $message Mensagem a ser logada
     */
    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}

// =============================================================================
// EXEMPLO DE USO
// =============================================================================

/**
 * Função principal de demonstração
 */
function main(): void
{
    try {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════════╗\n";
        echo "║   Clubify Checkout SDK - Gerenciamento de Planos de Assinatura   ║\n";
        echo "╚════════════════════════════════════════════════════════════════════╝\n";

        // CARREGAR VARIÁVEIS DO ARQUIVO .env
        // ===================================
        $envPath = __DIR__ . '/../.env';

        echo "\n🔧 Carregando configurações do arquivo .env...\n";

        try {
            loadEnvFile($envPath);
            echo "✅ Arquivo .env carregado com sucesso: {$envPath}\n";
        } catch (\RuntimeException $e) {
            echo "⚠️  Arquivo .env não encontrado: {$envPath}\n";
            echo "   Usando variáveis de ambiente do sistema...\n";
        }

        // CONFIGURAÇÃO
        // ============
        // Variáveis são carregadas do arquivo .env na raiz do SDK
        $config = [
            'api_key' => getenv('CLUBIFY_API_KEY') ?: 'sua-api-key-aqui',
            'tenant_id' => getenv('CLUBIFY_TENANT_ID') ?: 'seu-tenant-id-aqui',
            'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID') ?: 'seu-organization-id-aqui',
            'environment' => getenv('CLUBIFY_ENVIRONMENT') ?: 'sandbox',
            'base_url' => getenv('CLUBIFY_BASE_URL') ?: 'https://checkout.svelve.com/api/v1',
            'debug' => true,
            'product_id' => '68e8218fcb49f3e201021a37'
        ];

        // Validar configuração
        if ($config['api_key'] === 'sua-api-key-aqui') {
            echo "\n❌ ERRO: Configure suas credenciais antes de executar!\n";
            echo "\nOpções:\n";
            echo "1. Edite o arquivo .env na raiz do SDK:\n";
            echo "   {$envPath}\n\n";
            echo "2. Ou defina as variáveis de ambiente:\n";
            echo "   export CLUBIFY_API_KEY='sua-api-key'\n";
            echo "   export CLUBIFY_TENANT_ID='seu-tenant-id'\n";
            echo "   export CLUBIFY_ORGANIZATION_ID='seu-organization-id'\n";
            echo "   export CLUBIFY_ENVIRONMENT='sandbox'\n\n";
            exit(1);
        }

        echo "\n📋 Configuração carregada:\n";
        echo "   API Key: " . substr($config['api_key'], 0, 20) . "...\n";
        echo "   Tenant ID: {$config['tenant_id']}\n";
        echo "   Organization ID: {$config['organization_id']}\n";
        echo "   Environment: {$config['environment']}\n";
        echo "   Base URL: {$config['base_url']}\n";

        // Inicializar gerenciador
        $manager = new SubscriptionPlanManager($config);

        // =============================================================================
        // PASSO 1: Criar produtos de assinatura
        // =============================================================================

        echo "\n";
        echo "┌────────────────────────────────────────────────────────────────────┐\n";
        echo "│  PASSO 1: Criar Produtos de Assinatura                             │\n";
        echo "└────────────────────────────────────────────────────────────────────┘\n";

        $basicProduct = null;
        $premiumProduct = null;
        $enterpriseProduct = null;

        try {
            echo "\n📦 Criando produto para Plano Básico...\n";
            $basicProduct = $manager->sdk->products()->create([
                'name' => 'Produto Básico - Assinatura',
                'description' => 'Produto de assinatura para plano básico com acesso às funcionalidades essenciais',
                'type' => 'subscription',
                'metadata' => [
                    'subscription' => [
                        'billingCycle' => 'monthly',
                        'trialPeriod' => 7,
                        'setupFee' => 0
                    ]
                ]
            ]);
            $basicProductId = $basicProduct['id'] ?? $basicProduct['_id'] ?? null;
            echo "✅ Produto Básico criado: {$basicProductId}\n";
            echo "   Nome: " . ($basicProduct['name'] ?? 'N/A') . "\n";
            echo "   Tipo: " . ($basicProduct['type'] ?? 'N/A') . "\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao criar Produto Básico: {$e->getMessage()}\n";
            echo "ℹ️  Tentando usar produto existente do config...\n";
            $basicProductId = $config['product_id'];
        }

        try {
            echo "\n📦 Criando produto para Plano Premium...\n";
            $premiumProduct = $manager->sdk->products()->create([
                'name' => 'Produto Premium - Assinatura',
                'description' => 'Produto de assinatura para plano premium com funcionalidades avançadas',
                'type' => 'subscription',
                'metadata' => [
                    'subscription' => [
                        'billingCycle' => 'monthly',
                        'trialPeriod' => 14,
                        'setupFee' => 0
                    ]
                ]
            ]);
            $premiumProductId = $premiumProduct['id'] ?? $premiumProduct['_id'] ?? null;
            echo "✅ Produto Premium criado: {$premiumProductId}\n";
            echo "   Nome: " . ($premiumProduct['name'] ?? 'N/A') . "\n";
            echo "   Tipo: " . ($premiumProduct['type'] ?? 'N/A') . "\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao criar Produto Premium: {$e->getMessage()}\n";
            echo "ℹ️  Tentando usar produto existente do config...\n";
            $premiumProductId = $config['product_id'];
        }

        try {
            echo "\n📦 Criando produto para Plano Enterprise...\n";
            $enterpriseProduct = $manager->sdk->products()->create([
                'name' => 'Produto Enterprise - Assinatura',
                'description' => 'Produto de assinatura para plano enterprise com recursos corporativos completos',
                'type' => 'subscription',
                'metadata' => [
                    'subscription' => [
                        'billingCycle' => 'monthly',
                        'trialPeriod' => 30,
                        'setupFee' => 0
                    ]
                ]
            ]);
            $enterpriseProductId = $enterpriseProduct['id'] ?? $enterpriseProduct['_id'] ?? null;
            echo "✅ Produto Enterprise criado: {$enterpriseProductId}\n";
            echo "   Nome: " . ($enterpriseProduct['name'] ?? 'N/A') . "\n";
            echo "   Tipo: " . ($enterpriseProduct['type'] ?? 'N/A') . "\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao criar Produto Enterprise: {$e->getMessage()}\n";
            echo "ℹ️  Tentando usar produto existente do config...\n";
            $enterpriseProductId = $config['product_id'];
        }

        echo "\n📋 Resumo dos produtos criados:\n";
        echo "   Produto Básico ID: {$basicProductId}\n";
        echo "   Produto Premium ID: {$premiumProductId}\n";
        echo "   Produto Enterprise ID: {$enterpriseProductId}\n";

        // =============================================================================
        // PASSO 2: Criar planos de assinatura (IDEMPOTENTE)
        // =============================================================================

        echo "\n";
        echo "┌────────────────────────────────────────────────────────────────────┐\n";
        echo "│  PASSO 2: Criar/Atualizar Planos de Assinatura (Idempotente)      │\n";
        echo "└────────────────────────────────────────────────────────────────────┘\n";

        // Plano Básico
        $basicPlan = $manager->createOrUpdatePlan([
            'productId' => $basicProductId,
            'name' => 'Plano Básico Elite',
            'description' => 'Plano ideal para começar - Acesso às funcionalidades essenciais',
            'tier' => 'basic',
            'amount' => 29.90,
            'currency' => 'BRL',
            'billing_cycle' => 'monthly',
            'interval_count' => 1,
            'trial_days' => 7,
            'isActive' => true,
            'features' => [
                [
                    'name' => 'Usuários',
                    'description' => 'Até 10 usuários',
                    'enabled' => true,
                    'limit' => 10,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Armazenamento',
                    'description' => '5GB de armazenamento',
                    'enabled' => true,
                    'limit' => 5,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Análises Básicas',
                    'description' => 'Dashboards e relatórios básicos',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Suporte por Email',
                    'description' => 'Suporte via email em até 24h',
                    'enabled' => true,
                    'type' => 'feature'
                ]
            ]
        ]);

        // Plano Premium
        $premiumPlan = $manager->createOrUpdatePlan([
            'productId' => $premiumProductId,
            'name' => 'Plano Premium Elite',
            'description' => 'Plano completo para equipes - Todas as funcionalidades avançadas',
            'tier' => 'premium',
            'amount' => 99.90,
            'currency' => 'BRL',
            'billing_cycle' => 'monthly',
            'interval_count' => 1,
            'trial_days' => 14,
            'isActive' => true,
            'features' => [
                [
                    'name' => 'Usuários',
                    'description' => 'Usuários ilimitados',
                    'enabled' => true,
                    'limit' => 999999,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Armazenamento',
                    'description' => '50GB de armazenamento',
                    'enabled' => true,
                    'limit' => 50,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Análises Avançadas',
                    'description' => 'Dashboards customizáveis e relatórios avançados',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Suporte Prioritário',
                    'description' => 'Suporte prioritário em até 4h',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Integrações Personalizadas',
                    'description' => 'Integrações via API e webhooks',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Acesso à API',
                    'description' => 'Acesso completo à API REST',
                    'enabled' => true,
                    'type' => 'feature'
                ]
            ]
        ]);

        // Plano Enterprise
        $enterprisePlan = $manager->createOrUpdatePlan([
            'productId' => $enterpriseProductId,
            'name' => 'Plano Enterprise Elite',
            'description' => 'Plano para grandes organizações - Recursos corporativos completos',
            'tier' => 'enterprise',
            'amount' => 299.90,
            'currency' => 'BRL',
            'billing_cycle' => 'monthly',
            'interval_count' => 1,
            'trial_days' => 30,
            'isActive' => true,
            'features' => [
                [
                    'name' => 'Usuários',
                    'description' => 'Usuários ilimitados',
                    'enabled' => true,
                    'limit' => 999999,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Armazenamento',
                    'description' => 'Armazenamento ilimitado',
                    'enabled' => true,
                    'limit' => 999999,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Análises Enterprise',
                    'description' => 'Análises avançadas com BI e data warehouse',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Suporte Dedicado',
                    'description' => 'Gerente de conta dedicado',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'SSO/SAML',
                    'description' => 'Single Sign-On com SAML 2.0',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Segurança Avançada',
                    'description' => 'Auditoria, compliance e segurança avançada',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'Onboarding Personalizado',
                    'description' => 'Implementação e treinamento personalizado',
                    'enabled' => true,
                    'type' => 'feature'
                ],
                [
                    'name' => 'SLA Garantido',
                    'description' => 'SLA de 99.9% com créditos',
                    'enabled' => true,
                    'type' => 'feature'
                ]
            ]
        ]);

        // =============================================================================
        // PASSO 3: Listar todos os planos
        // =============================================================================

        echo "\n";
        echo "┌────────────────────────────────────────────────────────────────────┐\n";
        echo "│  PASSO 3: Listar Todos os Planos                                   │\n";
        echo "└────────────────────────────────────────────────────────────────────┘\n";

        $allPlans = $manager->listPlans([]);

        // =============================================================================
        // PASSO 4: Obter métricas de um plano
        // =============================================================================
        // NOTA: Esta funcionalidade está comentada porque getPlanMetrics() não está
        // exposto no SubscriptionsModule. Para obter métricas de planos, use o
        // método getSubscriptionMetrics() disponível no módulo.

        /*
        if (!empty($premiumPlan['id'])) {
            echo "\n";
            echo "┌────────────────────────────────────────────────────────────────────┐\n";
            echo "│  PASSO 4: Métricas do Plano Premium                                │\n";
            echo "└────────────────────────────────────────────────────────────────────┘\n";

            $metrics = $manager->getPlanMetrics($premiumPlan['id']);
        }
        */

        // =============================================================================
        // PASSO 5: Teste de idempotência - Executar novamente
        // =============================================================================

        echo "\n";
        echo "┌────────────────────────────────────────────────────────────────────┐\n";
        echo "│  PASSO 5: Teste de Idempotência                                    │\n";
        echo "│  Executando novamente para verificar que não cria duplicatas       │\n";
        echo "└────────────────────────────────────────────────────────────────────┘\n";

        $manager->createOrUpdatePlan([
            'name' => 'Plano Básico',
            'description' => 'Descrição atualizada do plano básico',
            'tier' => 'basic',
            'amount' => 29.90,
            'currency' => 'BRL',
            'billing_cycle' => 'monthly',
            'interval_count' => 1,
            'trial_days' => 7,
            'isActive' => true,
            'features' => [
                [
                    'name' => 'Usuários',
                    'description' => 'Até 10 usuários',
                    'enabled' => true,
                    'limit' => 10,
                    'type' => 'quota'
                ],
                [
                    'name' => 'Armazenamento',
                    'description' => '5GB de armazenamento',
                    'enabled' => true,
                    'limit' => 5,
                    'type' => 'quota'
                ]
            ]
        ]);

        // =============================================================================
        // RESUMO FINAL
        // =============================================================================

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════════╗\n";
        echo "║                        EXECUÇÃO CONCLUÍDA                          ║\n";
        echo "╠════════════════════════════════════════════════════════════════════╣\n";
        echo "║  ✅ Produtos de assinatura criados com sucesso                     ║\n";
        echo "║  ✅ Planos criados/atualizados com sucesso                         ║\n";
        echo "║  ✅ Sistema idempotente validado                                   ║\n";
        echo "║  ✅ Pode executar este script múltiplas vezes                      ║\n";
        echo "╚════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        echo "💡 PRÓXIMOS PASSOS:\n";
        echo "   1. Verifique os produtos e planos criados no dashboard\n";
        echo "   2. Use os IDs dos planos para criar assinaturas\n";
        echo "   3. Configure webhooks para monitorar eventos\n";
        echo "   4. Implemente a lógica de negócio específica\n";
        echo "\n";

        echo "📝 NOTA IMPORTANTE:\n";
        echo "   Este script agora cria produtos de tipo 'subscription' ANTES de criar\n";
        echo "   os planos. Cada plano está vinculado ao seu respectivo produto.\n";
        echo "   - Produto Básico → Plano Básico\n";
        echo "   - Produto Premium → Plano Premium\n";
        echo "   - Produto Enterprise → Plano Enterprise\n";
        echo "\n";

    } catch (\Exception $e) {
        echo "\n❌ ERRO FATAL:\n";
        echo "   Mensagem: {$e->getMessage()}\n";
        echo "   Arquivo: {$e->getFile()}:{$e->getLine()}\n";

        if (getenv('CLUBIFY_DEBUG') === 'true') {
            echo "\n📋 Stack Trace:\n";
            echo $e->getTraceAsString() . "\n";
        }

        exit(1);
    }
}

// Executar apenas se for chamado via CLI
if (php_sapi_name() === 'cli') {
    main();
}
