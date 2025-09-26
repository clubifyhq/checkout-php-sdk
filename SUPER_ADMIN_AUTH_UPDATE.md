# 🔐 Atualização do Sistema de Autenticação Super Admin

## 📄 Resumo da Mudança

O SDK PHP agora prioriza autenticação por **email/password** para super admin, mantendo API key como fallback opcional.

## ✅ Implementação

### Antes (apenas API key)
```php
$sdk->initializeAsSuperAdmin([
    'api_key' => 'clb_live_abc123...',
    'tenant_id' => '507f1f77bcf86cd799439011'
]);
```

### Depois (prioriza email/password)
```php
$sdk->initializeAsSuperAdmin([
    'email' => 'admin@empresa.com',        // ✨ NOVO: Campo principal
    'password' => 'senha_segura',          // ✨ NOVO: Campo principal
    'tenant_id' => '507f1f77bcf86cd799011',
    'api_key' => 'clb_live_abc123...'      // 🔄 OPCIONAL: Fallback
]);
```

## 🔄 Compatibilidade

### Suporte a `username` (retrocompatibilidade)
```php
$sdk->initializeAsSuperAdmin([
    'username' => 'admin@empresa.com',     // 🔄 LEGACY: Tratado como alias para 'email'
    'password' => 'senha_segura',
    'tenant_id' => '507f1f77bcf86cd799011'
]);
```

### Estratégia de Autenticação (Padronizada)
1. **Primário**: `email` + `password` → `/auth/login` (padrão da API)
2. **Legacy**: `username` + `password` → `/auth/login` (username usado como email para retrocompatibilidade)
3. **Fallback**: `api_key` + `tenant_id` → `/auth/api-key/token`

## 🛡️ Benefícios de Segurança

### ✅ Vantagens do Email/Password
- ✅ Autenticação humana real
- ✅ Controle granular de permissões por usuário
- ✅ Auditoria completa de ações
- ✅ Possibilidade de MFA futuro
- ✅ Sessões controláveis
- ✅ Logs de auditoria com identidade real

### ⚠️ Limitações do API Key
- ⚠️ Autenticação "robótica"
- ⚠️ Permissões fixas no momento da criação
- ⚠️ Mais difícil rastrear responsabilidade
- ⚠️ Rotação menos frequente

## 📝 Configuração Laravel

### config/clubify-checkout.php
```php
return [
    'credentials' => [
        'tenant_id' => env('CLUBIFY_TENANT_ID'),
        'api_key' => env('CLUBIFY_API_KEY'),
    ],

    // ✨ NOVA SEÇÃO: Super Admin
    'super_admin' => [
        'email' => env('CLUBIFY_SUPER_ADMIN_EMAIL'),
        'password' => env('CLUBIFY_SUPER_ADMIN_PASSWORD'),
        'tenant_id' => env('CLUBIFY_SUPER_ADMIN_TENANT_ID', env('CLUBIFY_TENANT_ID')),

        // Opcional: API key como fallback
        'api_key' => env('CLUBIFY_SUPER_ADMIN_API_KEY', env('CLUBIFY_API_KEY')),
    ],
];
```

### .env
```bash
# Credenciais primárias do super admin
CLUBIFY_SUPER_ADMIN_EMAIL=admin@suaempresa.com
CLUBIFY_SUPER_ADMIN_PASSWORD=sua_senha_segura
CLUBIFY_SUPER_ADMIN_TENANT_ID=507f1f77bcf86cd799439011

# Fallback (opcional)
CLUBIFY_SUPER_ADMIN_API_KEY=clb_live_abc123...
```

## 🔧 Migração

### Passo 1: Atualizar Configuração
Adicione as credenciais de email/password na sua configuração.

### Passo 2: Testar Autenticação
```php
try {
    $result = $sdk->initializeAsSuperAdmin([
        'email' => config('clubify-checkout.super_admin.email'),
        'password' => config('clubify-checkout.super_admin.password'),
        'tenant_id' => config('clubify-checkout.super_admin.tenant_id'),
    ]);

    if ($result['success']) {
        echo "✅ Autenticação por email/password funcionando!";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
    // Fallback para API key se necessário
}
```

### Passo 3: Remover API Key (Opcional)
Após confirmar que email/password funciona, você pode opcionalmente remover a dependência de API key.

## 🚀 Benefícios Práticos

1. **Autenticação Natural**: Use credenciais humanas em vez de strings técnicas
2. **Segurança Aprimorada**: Controle de acesso mais granular
3. **Compatibilidade Total**: Código existente continua funcionando
4. **Fallback Robusto**: Se email/password falhar, tenta API key automaticamente
5. **Logs Melhores**: Auditoria com identidade real do usuário

## 🎯 Próximos Passos

- ✅ Implementação do email/password como primário
- ✅ Manutenção da compatibilidade com API key
- 🔄 **Em desenvolvimento**: MFA para super admin
- 🔄 **Planejado**: Rate limiting avançado
- 🔄 **Planejado**: Gestão de sessões ativas

---

**Nota**: Esta mudança é **100% compatível** com código existente. API keys continuam funcionando como fallback.