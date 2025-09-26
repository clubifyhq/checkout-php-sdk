<?php

/**
 * Exemplo de Autenticação Super Admin com Email/Password
 *
 * Este exemplo demonstra como usar o novo sistema de autenticação
 * que prioriza email/password sobre API key para super admin.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// ===============================================
// CONFIGURAÇÃO
// ===============================================

$config = [
    'credentials' => [
        'tenant_id' => 'sua_tenant_id_aqui',
        'api_key' => 'sua_api_key_aqui', // Fallback
    ],
    'settings' => [
        'environment' => 'sandbox', // ou 'production'
        'base_url' => 'https://api-sandbox.clubify.com',
        'timeout' => 30,
        'debug' => true
    ]
];

// ===============================================
// EXEMPLO 1: AUTENTICAÇÃO POR EMAIL/PASSWORD
// ===============================================

echo "=== Exemplo 1: Autenticação por Email/Password ===\n";

try {
    $sdk = new ClubifyCheckoutSDK($config);

    // ✨ NOVO: Usar email/password como método primário
    $superAdminCredentials = [
        'email' => 'admin@suaempresa.com',
        'password' => 'sua_senha_segura',
        'tenant_id' => 'sua_tenant_id_aqui'
        // API key é opcional - usado apenas como fallback
    ];

    $result = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    if ($result['success']) {
        echo "✅ Autenticação por email/password bem-sucedida!\n";
        echo "Modo: {$result['mode']}\n";
        echo "Role: {$result['role']}\n";

        // Testar operação que requer super admin
        $context = $sdk->getCurrentContext();
        echo "Contexto atual: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";

    } else {
        echo "❌ Falha na autenticação\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ===============================================
// EXEMPLO 2: COMPATIBILIDADE COM USERNAME
// ===============================================

echo "=== Exemplo 2: Retrocompatibilidade com Username ===\n";

try {
    $sdk = new ClubifyCheckoutSDK($config);

    // 🔄 LEGACY: Usar 'username' como alias para 'email' (retrocompatibilidade)
    $superAdminCredentials = [
        'username' => 'admin@suaempresa.com', // LEGACY: Tratado internamente como email
        'password' => 'sua_senha_segura',
        'tenant_id' => 'sua_tenant_id_aqui'
    ];

    $result = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    if ($result['success']) {
        echo "✅ Autenticação por username/password (legacy) bem-sucedida!\n";
        echo "Nota: 'username' é tratado como 'email' internamente\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ===============================================
// EXEMPLO 3: FALLBACK PARA API KEY
// ===============================================

echo "=== Exemplo 3: Fallback para API Key ===\n";

try {
    $sdk = new ClubifyCheckoutSDK($config);

    // 🔄 FALLBACK: Se email/password falhar, usa API key
    $superAdminCredentials = [
        'email' => 'email_inexistente@teste.com', // Email que pode falhar
        'password' => 'senha_incorreta',           // Senha incorreta
        'api_key' => 'sua_api_key_valida_aqui',   // Fallback
        'tenant_id' => 'sua_tenant_id_aqui'
    ];

    $result = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    if ($result['success']) {
        echo "✅ Autenticação por API key (fallback) bem-sucedida!\n";
        echo "Método usado: API Key como fallback\n";
    }

} catch (Exception $e) {
    echo "❌ Todos os métodos falharam: " . $e->getMessage() . "\n";
}

echo "\n";

// ===============================================
// EXEMPLO 4: CONFIGURAÇÃO COMPLETA COM FALLBACK
// ===============================================

echo "=== Exemplo 4: Configuração Completa ===\n";

try {
    $sdk = new ClubifyCheckoutSDK($config);

    // 🎯 RECOMENDADO: Configuração completa com múltiplas opções
    $superAdminCredentials = [
        // Método primário: email/password
        'email' => 'admin@suaempresa.com',
        'password' => 'sua_senha_segura',

        // Informações do tenant
        'tenant_id' => 'sua_tenant_id_aqui',

        // Fallback: API key (opcional mas recomendado)
        'api_key' => 'sua_api_key_aqui'
    ];

    $result = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    if ($result['success']) {
        echo "✅ SDK inicializado como super admin!\n";

        // Exemplo de operação: criar organização
        echo "\n--- Testando Operação Super Admin ---\n";

        $organizationData = [
            'name' => 'Organização Teste',
            'subdomain' => 'teste-org-' . time(),
            'admin_email' => 'admin@teste-org.com',
            'description' => 'Organização criada via autenticação email/password'
        ];

        // Esta operação requer privilégios de super admin
        $orgResult = $sdk->createOrganization($organizationData);

        if ($orgResult['success']) {
            echo "✅ Organização criada com sucesso!\n";
            echo "ID: " . $orgResult['tenant']['_id'] . "\n";
            echo "Nome: " . $orgResult['tenant']['name'] . "\n";
        } else {
            echo "❌ Falha ao criar organização\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ===============================================
// EXEMPLO 5: CONFIGURAÇÃO USANDO VARIÁVEIS DE AMBIENTE
// ===============================================

echo "=== Exemplo 5: Usando Variáveis de Ambiente ===\n";

// Em .env:
// CLUBIFY_SUPER_ADMIN_EMAIL=admin@suaempresa.com
// CLUBIFY_SUPER_ADMIN_PASSWORD=sua_senha_segura
// CLUBIFY_SUPER_ADMIN_TENANT_ID=sua_tenant_id

try {
    $sdk = new ClubifyCheckoutSDK($config);

    $superAdminCredentials = [
        'email' => $_ENV['CLUBIFY_SUPER_ADMIN_EMAIL'] ?? 'admin@exemplo.com',
        'password' => $_ENV['CLUBIFY_SUPER_ADMIN_PASSWORD'] ?? 'senha123',
        'tenant_id' => $_ENV['CLUBIFY_SUPER_ADMIN_TENANT_ID'] ?? 'tenant123',

        // Fallback opcional
        'api_key' => $_ENV['CLUBIFY_SUPER_ADMIN_API_KEY'] ?? null
    ];

    $result = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    if ($result['success']) {
        echo "✅ Autenticação via variáveis de ambiente bem-sucedida!\n";
        echo "Configuração: segura e flexível\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n=== Exemplos Concluídos ===\n";

// ===============================================
// DICAS DE SEGURANÇA
// ===============================================

echo "\n=== 🔐 Dicas de Segurança ===\n";
echo "1. ✅ Use email/password como método primário\n";
echo "2. ✅ Mantenha API key como fallback para robustez\n";
echo "3. ✅ Armazene credenciais em variáveis de ambiente\n";
echo "4. ✅ Use senhas fortes para contas de super admin\n";
echo "5. ✅ Monitore logs de auditoria para atividades\n";
echo "6. ⚠️  Nunca commite credenciais no código\n";
echo "7. 🔄 Considere rotação periódica de credenciais\n";

echo "\n=== ✨ Benefícios da Nova Autenticação ===\n";
echo "• 🧑‍💼 Autenticação humana real (vs robótica)\n";
echo "• 🔍 Melhor auditoria e rastreabilidade\n";
echo "• 🛡️ Controle granular de permissões\n";
echo "• 🔄 Preparação para MFA futuro\n";
echo "• 🎯 Compatibilidade total com código existente\n";

?>