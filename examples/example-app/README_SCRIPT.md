# Script de Teste do SDK Clubify

## test_create_user_and_subscription.php

Script completo que demonstra o uso do SDK Clubify para:

1. **Instanciar e inicializar o SDK** com configurações de teste
2. **Criar um usuário** usando o módulo UserManagement
3. **Criar uma subscription** para o usuário usando o módulo Subscriptions
4. **Listar usuários** existentes (se disponível)

### Como executar:

```bash
cd /Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/example-app/
php test_create_user_and_subscription.php
```

### Funcionalidades demonstradas:

- ✅ Instanciação do SDK com configuração completa
- ✅ Inicialização com skip health check (para testes)
- ✅ Uso do módulo UserManagement para criar usuário
- ✅ Uso do módulo Subscriptions para criar assinatura
- ✅ Tratamento de erros abrangente
- ✅ Logs detalhados de cada etapa
- ✅ Uso correto do autoload do Composer

### Configuração:

O script usa as variáveis de ambiente ou valores padrão:
- `CLUBIFY_CHECKOUT_TENANT_ID`
- `CLUBIFY_CHECKOUT_API_KEY`
- `CLUBIFY_CHECKOUT_ENVIRONMENT`
- `CLUBIFY_CHECKOUT_API_URL`

### Exemplo de output esperado:

```
=== Teste do SDK Clubify - Criação de Usuário e Subscription ===

1. Instanciando o SDK...
   ✅ SDK instanciado com sucesso

2. Inicializando o SDK...
   ✅ SDK inicializado com sucesso
   - Autenticado: SIM
   - Tenant ID: 68c05e15ad23f0f6aaa1ae51
   - Ambiente: sandbox
   - Health check pulado: SIM

3. Acessando módulo UserManagement...
   ✅ Módulo UserManagement carregado

4. Preparando dados do usuário...
   - Nome: João Silva Teste
   - Email: joao.teste.1642589234@exemplo.com
   - Telefone: +55 11 99999-8888
   ✅ Dados preparados

5. Criando usuário via módulo UserManagement...
   ✅ Usuário criado com sucesso!

=== USUÁRIO CRIADO COM SUCESSO! ===

8. Criando subscription para o usuário...
   ✅ Subscription criada com sucesso!
   - Subscription ID: sub_123456
   - Status: active
   - Plano: basic_plan

=== TESTE CONCLUÍDO COM SUCESSO! ===
```