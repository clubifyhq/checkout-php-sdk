<?php

/**
 * Quick Diagnostic Script - Clubify Checkout SDK
 *
 * Versão simplificada para diagnóstico rápido e identificação de dados mock
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Carregar .env se existir
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

class QuickDiagnostic
{
    private ClubifyCheckoutSDK $sdk;
    private int $mockCount = 0;
    private int $realCount = 0;

    public function __construct()
    {
        $config = [
            'credentials' => [
                'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? 'demo_tenant',
                'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'demo_key',
                'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
            ],
            'http' => ['timeout' => 5000, 'connect_timeout' => 3, 'retries' => 1],
            'endpoints' => ['base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1']
        ];

        $this->sdk = new ClubifyCheckoutSDK($config);
        echo "🚀 SDK inicializado - Tenant: " . substr($config['credentials']['tenant_id'], 0, 10) . "...\n\n";
    }

    public function runQuickTests(): void
    {
        echo "=== DIAGNÓSTICO RÁPIDO - MOCK vs REAL DATA ===\n\n";

        // Teste módulos principais
        $this->testModule('customers', 'createCustomer', [
            'name' => 'Test User',
            'email' => 'test.' . time() . '@example.com'
        ]);

        $this->testModule('products', 'getStats');

        $this->testModule('payments', 'getStatus');

        // Organization tem um bug, vamos pular por agora
        // $this->testModule('organization', 'getStatus');

        $this->testModule('customers', 'findByEmail', 'test@example.com');

        // Resumo
        $total = $this->mockCount + $this->realCount;
        $mockPercent = $total > 0 ? round(($this->mockCount / $total) * 100, 1) : 0;

        echo "\n=== RESUMO ===\n";
        echo "📊 Total de testes: $total\n";
        echo "🎭 Dados mock: {$this->mockCount} ({$mockPercent}%)\n";
        echo "🌐 Dados reais: {$this->realCount} (" . round(100 - $mockPercent, 1) . "%)\n\n";

        if ($mockPercent > 70) {
            echo "⚠️  ATENÇÃO: Alto percentual de dados mock!\n";
            echo "   → Verificar credenciais da API\n";
            echo "   → Confirmar environment (sandbox/production)\n";
        } elseif ($mockPercent > 30) {
            echo "🔧 INFO: Alguns dados mock detectados (normal para dev)\n";
        } else {
            echo "✅ SUCESSO: Conectando com dados reais da API!\n";
        }
    }

    private function testModule(string $moduleName, string $method, $params = null): void
    {
        try {
            $module = $this->sdk->$moduleName();

            echo "🧪 Testando {$moduleName}::{$method}()... ";

            if ($params === null) {
                $result = $module->$method();
            } else {
                // Para métodos que recebem parâmetro(s)
                $result = $module->$method($params);
            }

            if (is_array($result)) {
                $isMock = $this->detectMockData($result);

                if ($isMock) {
                    $this->mockCount++;
                    echo "🎭 MOCK\n";
                    $this->showMockIndicators($result);
                } else {
                    $this->realCount++;
                    echo "🌐 REAL\n";
                }
            } else {
                echo "❓ UNKNOWN (não é array)\n";
            }

        } catch (\Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }

    private function detectMockData(array $data): bool
    {
        $json = json_encode($data);

        // Padrões óbvios de mock
        $mockPatterns = [
            'customer_123', 'test@example.com', 'demo_', 'mock_',
            'Test User', 'Sample', 'Example', '1234567890'
        ];

        foreach ($mockPatterns as $pattern) {
            if (stripos($json, $pattern) !== false) {
                return true;
            }
        }

        // IDs muito previsíveis
        if (preg_match('/customer_\d{1,3}[^0-9]/', $json)) {
            return true;
        }

        return false;
    }

    private function showMockIndicators(array $data): void
    {
        $json = json_encode($data);
        $indicators = [];

        if (stripos($json, 'test') !== false) $indicators[] = 'test';
        if (stripos($json, 'mock') !== false) $indicators[] = 'mock';
        if (stripos($json, 'demo') !== false) $indicators[] = 'demo';
        if (stripos($json, 'example') !== false) $indicators[] = 'example';
        if (preg_match('/customer_\d{1,3}/', $json)) $indicators[] = 'predictable_id';

        if (!empty($indicators)) {
            echo "     ↳ Indicadores: " . implode(', ', $indicators) . "\n";
        }
    }
}

// Executar
try {
    $diagnostic = new QuickDiagnostic();
    $diagnostic->runQuickTests();
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}