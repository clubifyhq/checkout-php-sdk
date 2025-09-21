<?php

declare(strict_types=1);

// Carregar autoload do Composer (subir 2 níveis para sdk/php/vendor/)
require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Configurações do SDK
$config = [
    'credentials' => [
        'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? '68c05e15ad23f0f6aaa1ae51',
        'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'clb_test_4f8b2c1d6e9a7f3b5c8e2a1d4f7b9e3c',
        'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
    ],
    'http' => [
        'timeout' => 15000, // 15 segundos
        'connect_timeout' => 5,
        'retries' => 2
    ],
    'endpoints' => [
        'base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug'
    ]
];

echo "=== Teste do SDK Clubify - Criação de Usuário e Subscription ===\n\n";

try {
    // 1. Instanciar o SDK
    echo "1. Instanciando o SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "   ✅ SDK instanciado com sucesso\n\n";

    // 2. Inicializar o SDK (com skip health check para testes)
    echo "2. Inicializando o SDK...\n";
    $initResult = $sdk->initialize(true); // Skip health check

    if ($initResult['success']) {
        echo "   ✅ SDK inicializado com sucesso\n";
        echo "   - Autenticado: " . ($initResult['authenticated'] ? 'SIM' : 'NÃO') . "\n";
        echo "   - Tenant ID: " . $initResult['tenant_id'] . "\n";
        echo "   - Ambiente: " . $initResult['environment'] . "\n";
        echo "   - Health check pulado: " . ($initResult['health_check_skipped'] ? 'SIM' : 'NÃO') . "\n\n";
    } else {
        throw new Exception('Falha na inicialização do SDK');
    }

    // 3. Acessar módulo userManagement
    echo "3. Acessando módulo UserManagement...\n";
    $userManagement = $sdk->userManagement();
    echo "   ✅ Módulo UserManagement carregado\n\n";

    // 4. Preparar dados do usuário
    echo "4. Preparando dados do usuário...\n";
    $userData = [
        'name' => 'João Silva Teste',
        'email' => 'joao.teste.' . time() . '@exemplo.com', // Email único
        'password' => 'SenhaSegura123!',
        'phone' => '+55 11 99999-8888',
        'document' => [
            'type' => 'cpf',
            'number' => '123.456.789-10'
        ],
        'address' => [
            'street' => 'Rua das Flores, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zipCode' => '01234-567',
            'country' => 'BR'
        ],
        'preferences' => [
            'newsletter' => true,
            'notifications' => true
        ],
        'metadata' => [
            'source' => 'test_script',
            'created_at' => date('c')
        ]
    ];

    echo "   - Nome: " . $userData['name'] . "\n";
    echo "   - Email: " . $userData['email'] . "\n";
    echo "   - Telefone: " . $userData['phone'] . "\n";
    echo "   ✅ Dados preparados\n\n";

    // 5. Criar usuário via userManagement
    echo "5. Criando usuário via módulo UserManagement...\n";

    $result = $userManagement->createUser($userData);

    echo "   ✅ Usuário criado com sucesso!\n\n";

    // 6. Exibir resultado
    echo "6. Resultado da criação:\n";
    if (is_array($result)) {
        echo "   - ID do usuário: " . ($result['id'] ?? 'N/A') . "\n";
        echo "   - Email: " . ($result['email'] ?? 'N/A') . "\n";
        echo "   - Status: " . ($result['status'] ?? 'N/A') . "\n";
        echo "   - Data de criação: " . ($result['created_at'] ?? 'N/A') . "\n";

        if (isset($result['subscription'])) {
            echo "   - Subscription ID: " . ($result['subscription']['id'] ?? 'N/A') . "\n";
            echo "   - Plano: " . ($result['subscription']['plan'] ?? 'N/A') . "\n";
        }
    } else {
        echo "   - Resultado: " . print_r($result, true) . "\n";
    }

    echo "\n=== USUÁRIO CRIADO COM SUCESSO! ===\n";

    // 8. Criar subscription para o usuário (se tiver ID)
    if (isset($result['id'])) {
        echo "\n8. Criando subscription para o usuário...\n";

        try {
            $subscriptionModule = $sdk->subscriptions();

            $subscriptionData = [
                'user_id' => $result['id'],
                'plan_id' => 'basic_plan', // Plano básico
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'start_date' => date('Y-m-d'),
                'payment_method' => [
                    'type' => 'credit_card',
                    'token' => 'test_payment_token_123'
                ],
                'metadata' => [
                    'created_by' => 'test_script',
                    'source' => 'api_demo'
                ]
            ];

            $subscription = $subscriptionModule->createSubscription($subscriptionData);

            echo "   ✅ Subscription criada com sucesso!\n";
            echo "   - Subscription ID: " . ($subscription['id'] ?? 'N/A') . "\n";
            echo "   - Status: " . ($subscription['status'] ?? 'N/A') . "\n";
            echo "   - Plano: " . ($subscription['plan_id'] ?? 'N/A') . "\n";

        } catch (Exception $e) {
            echo "   ⚠️  Erro ao criar subscription: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== TESTE CONCLUÍDO COM SUCESSO! ===\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e->getPrevious()) {
        echo "   Erro anterior: " . $e->getPrevious()->getMessage() . "\n";
    }

    echo "\n=== TESTE FALHOU ===\n";
    exit(1);
}

// 7. Teste adicional: Listar usuários (se disponível)
echo "\n7. Teste adicional - Listando usuários...\n";
try {
    if (method_exists($userManagement, 'getUsers')) {
        $users = $userManagement->getUsers(['limit' => 5]);
        echo "   ✅ Total de usuários encontrados: " . count($users) . "\n";

        if (!empty($users)) {
            echo "   - Últimos usuários:\n";
            foreach (array_slice($users, 0, 3) as $user) {
                echo "     * " . ($user['name'] ?? 'N/A') . " (" . ($user['email'] ?? 'N/A') . ")\n";
            }
        }
    } else {
        echo "   ⚠️  Método getUsers não disponível no módulo UserManagement\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Não foi possível listar usuários: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT FINALIZADO ===\n";