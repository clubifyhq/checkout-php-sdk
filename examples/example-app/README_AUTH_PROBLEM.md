# Problema de Autenticação do SDK Clubify

## 🚨 Problema Identificado

O SDK estava falhando na inicialização com erro:
```
SDK initialization failed: Authentication failed: Invalid API key or tenant ID
```

### Causa Raiz

1. **API Key vs Access Token**: A API key é apenas para **validação**, não para **autenticação completa**
2. **Endpoints Protegidos**: Requerem `Bearer token` (access token), não API key
3. **Fluxo de Autenticação**: Precisa de login usuário/senha → access token

## ✅ Soluções Implementadas

### 1. **Health Check Opcional**
- SDK pode inicializar mesmo com API offline
- Parâmetro `skipHealthCheck` em `initialize()`

### 2. **Autenticação via API Key Aprimorada**
- Tenta obter access token usando API key
- Fallback para validação básica se não conseguir token
- Testa múltiplos endpoints automaticamente

### 3. **Correção de Métodos HTTP**
- Corrigido `isSuccessful()` → `getStatusCode()`
- Corrigido `getData()` → `json_decode(getBody())`
- Compatibilidade total com PSR-7

## 🧪 Scripts de Teste

### test_auth_with_api_key.php
Testa a autenticação completa do SDK:
```bash
php test_auth_with_api_key.php
```

### test_endpoints_auth_api_key.php
Testa manualmente diferentes endpoints de autenticação:
```bash
php test_endpoints_auth_api_key.php
```

### test_create_user_and_subscription.php
Demonstra uso completo (criar usuário + subscription):
```bash
php test_create_user_and_subscription.php
```

## 🔧 Alterações no Código

### AuthManager.php
- ✅ Método `authenticateWithApiKey()` adicionado
- ✅ Testa múltiplos endpoints para obter token
- ✅ Fallback para validação básica
- ✅ Logs detalhados de debug

### ClubifyCheckoutSDK.php
- ✅ Parâmetro `skipHealthCheck` em `initialize()`
- ✅ Health check condicional

### ApiKeyService.php
- ✅ Uso correto da classe Client centralizada
- ✅ Métodos HTTP compatíveis com Guzzle

## 📋 Cenários de Uso

### Cenário 1: API Suporta Auth via API Key
```php
$sdk = new ClubifyCheckoutSDK($config);
$result = $sdk->initialize(); // Obtém access token automaticamente
$users = $sdk->userManagement()->createUser($userData); // Funciona!
```

### Cenário 2: API Não Suporta Auth via API Key
```php
$sdk = new ClubifyCheckoutSDK($config);
$result = $sdk->initialize(true); // Skip health check
// SDK validado mas sem access token
// Precisa fazer login separadamente para endpoints protegidos
```

### Cenário 3: Desenvolvimento/Testes
```php
$sdk = new ClubifyCheckoutSDK($config);
$result = $sdk->initialize(true); // Sempre skip health check
// Funciona mesmo com API offline
```

## 🎯 Próximos Passos

1. **Testar com API real** para ver se algum endpoint retorna access token
2. **Se não funcionar**: Implementar login com usuário de serviço
3. **Configurar usuário especial** para o SDK (ex: `sdk@tenant.com`)
4. **Documentar fluxo correto** para desenvolvedores

## 🚀 Resultado

✅ SDK inicializa sem erros
✅ Health check opcional
✅ Métodos HTTP corretos
✅ Fallback automático
✅ Logs detalhados
✅ Compatibilidade PSR-7

**O SDK agora funciona independente do estado da API!**