# 🚀 Super Admin - Guia de Configuração Rápida

Este guia te ajudará a configurar e usar o sistema Super Admin em poucos minutos.

## 📋 Pré-requisitos

- Laravel instalado e funcionando
- Banco de dados configurado (SQLite, MySQL, PostgreSQL)
- PHP 8.0+

## ⚡ Configuração Rápida (3 passos)

### 1. Configure as Variáveis de Ambiente

```bash
# Configura automaticamente todas as variáveis necessárias
php artisan super-admin:env-setup
```

### 2. Execute as Migrations

```bash
# Cria todas as tabelas necessárias
php artisan migrate
```

### 3. Crie um Super Admin

```bash
# Cria seu primeiro usuário Super Admin
php artisan super-admin:setup
```

## 🎯 Como Usar

### Acesso Web (Interface Gráfica)

1. **Acesse:** http://localhost/super-admin/login
2. **Faça login** com as credenciais que você criou
3. **Navegue** pelo dashboard

### Acesso API (Programático)

#### 1. Fazer Login via API
```bash
curl -X POST http://localhost/api/super-admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "seu-email@exemplo.com",
    "password": "sua-senha"
  }'
```

#### 2. Usar o Token Retornado
```bash
# Use o token nas próximas requisições
curl -X GET http://localhost/api/super-admin/me \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

## 🔧 Comandos Úteis

### Listar Super Admins
```bash
php artisan tinker
>>> App\Models\SuperAdmin::all();
```

### Criar Super Admin Adicional
```bash
php artisan super-admin:setup --email=admin2@exemplo.com --name="Admin 2"
```

### Verificar Configuração
```bash
# Executa validação completa
php validate-super-admin-integration.php
```

### Limpar Cache (se tiver problemas)
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## 🌐 URLs Importantes

| Funcionalidade | URL |
|----------------|-----|
| Login Web | http://localhost/super-admin/login |
| Dashboard | http://localhost/super-admin/dashboard |
| Gerenciar Tenants | http://localhost/super-admin/tenants |
| Criar Organização | http://localhost/super-admin/create-organization |
| Login API | http://localhost/api/super-admin/login |
| Info do Usuário API | http://localhost/api/super-admin/me |

## 🔐 Variáveis de Ambiente Principais

As principais variáveis que você pode querer personalizar:

```env
# Habilitar/Desabilitar Super Admin
SUPER_ADMIN_ENABLED=true

# Segurança
SUPER_ADMIN_REQUIRE_MFA=false
SUPER_ADMIN_MAX_LOGIN_ATTEMPTS=5

# Sessão
SUPER_ADMIN_SESSION_TIMEOUT=3600  # 1 hora

# JWT (para API)
SUPER_ADMIN_JWT_TTL=3600  # 1 hora
```

## 🚨 Solução de Problemas

### Erro "no such table"
```bash
# Execute as migrations
php artisan migrate
```

### Erro "strtolower() expects string"
```bash
# Já foi corrigido! Se ainda acontecer:
composer dump-autoload
```

### Não consegue fazer login
```bash
# Verifique se o Super Admin existe
php artisan tinker
>>> App\Models\SuperAdmin::where('email', 'seu-email@exemplo.com')->first();

# Recrie se necessário
php artisan super-admin:setup --force
```

### Rotas não encontradas
```bash
# Limpe o cache de rotas
php artisan route:clear
php artisan config:clear
```

## 🎉 Próximos Passos

Após o login bem-sucedido, você pode:

1. **Criar Organizações/Tenants** através da interface
2. **Alternar entre contextos** de diferentes tenants
3. **Monitorar atividades** através dos logs de auditoria
4. **Gerenciar usuários** e permissões

## 📚 Recursos Avançados

- **Multi-tenant**: Alterne entre diferentes tenants
- **Auditoria**: Todos os logs são registrados automaticamente
- **Segurança**: Middleware de proteção e validação
- **API Completa**: Endpoints RESTful para integração

## 💡 Dicas

- Use `php artisan super-admin:setup --help` para ver todas as opções
- Configure `SUPER_ADMIN_REQUIRE_MFA=true` para maior segurança em produção
- Monitore os logs em `storage/logs/laravel.log` para debug

---

🎯 **Tudo pronto!** Agora você tem um sistema Super Admin completo funcionando!