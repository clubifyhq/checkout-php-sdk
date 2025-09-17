# ⚡ Laravel Commands - Documentação Completa

## Visão Geral

O SDK Clubify Checkout para Laravel inclui um conjunto completo de **comandos Artisan** para facilitar a instalação, configuração, publicação de assets e sincronização com a API. Estes comandos automatizam tarefas comuns e oferecem ferramentas poderosas para desenvolvimento e manutenção.

### 🎯 Comandos Disponíveis

- **`clubify:install`** - Instalação e configuração inicial completa
- **`clubify:publish`** - Publicação seletiva de assets (config, lang, stubs)
- **`clubify:sync`** - Sincronização e teste de conectividade com a API

### 🏗️ Estrutura dos Commands

```
Laravel Commands
├── InstallCommand
│   ├── Publicação de assets
│   ├── Configuração de .env
│   └── Instruções pós-instalação
├── PublishCommand
│   ├── Publicação seletiva
│   ├── Dry-run support
│   └── Asset management
└── SyncCommand
    ├── Teste de conectividade
    ├── Sincronização de dados
    └── Health checking
```

## 🚀 clubify:install

### Descrição

Comando principal para instalação e configuração inicial do SDK. Automatiza todo o processo de setup, incluindo publicação de assets, configuração de variáveis de ambiente e instruções pós-instalação.

### Sintaxe

```bash
php artisan clubify:install [options]
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--force` | Força sobrescrita de arquivos existentes |
| `--config-only` | Publica apenas arquivos de configuração |
| `--no-publish` | Não publica assets (útil para reinstalação) |

### Exemplos de Uso

#### Instalação Padrão
```bash
# Instalação completa com todos os assets
php artisan clubify:install

# Saída esperada:
🚀 Instalando Clubify Checkout SDK...
📦 Publicando assets...
✅ Assets publicados com sucesso
🔧 Configurando variáveis de ambiente...
  📝 Adicionado: CLUBIFY_CHECKOUT_API_KEY
  📝 Adicionado: CLUBIFY_CHECKOUT_API_SECRET
  📝 Adicionado: CLUBIFY_CHECKOUT_TENANT_ID
  ⏭️  Já existe: CLUBIFY_CHECKOUT_ENVIRONMENT
✅ Variáveis de ambiente adicionadas

🎉 Instalação concluída com sucesso!
```

#### Instalação com Force
```bash
# Força sobrescrita de arquivos existentes
php artisan clubify:install --force

# Útil para:
# - Reinstalar após atualizações
# - Restaurar configurações padrão
# - Corrigir arquivos corrompidos
```

#### Instalação Apenas Config
```bash
# Publica apenas configuração (mais rápido)
php artisan clubify:install --config-only

# Útil para:
# - Atualizações que só modificaram configuração
# - Projetos que já possuem traduções customizadas
```

#### Instalação Sem Assets
```bash
# Configura .env sem publicar assets
php artisan clubify:install --no-publish

# Útil para:
# - Ambientes automatizados
# - Quando assets já foram publicados manualmente
```

### Processo de Instalação

#### 1. Publicação de Assets
```bash
# O comando publica automaticamente:
# ✅ config/clubify-checkout.php
# ✅ resources/lang/vendor/clubify-checkout/
# ✅ resources/stubs/vendor/clubify-checkout/
```

#### 2. Configuração de Variáveis
```env
# Adicionadas automaticamente ao .env:
CLUBIFY_CHECKOUT_API_KEY=your-api-key-here
CLUBIFY_CHECKOUT_API_SECRET=your-api-secret-here
CLUBIFY_CHECKOUT_TENANT_ID=your-tenant-id-here
CLUBIFY_CHECKOUT_ENVIRONMENT=sandbox
CLUBIFY_CHECKOUT_BASE_URL=https://api.clubify.com
CLUBIFY_CHECKOUT_TIMEOUT=30
CLUBIFY_CHECKOUT_RETRY_ATTEMPTS=3
CLUBIFY_CHECKOUT_CACHE_TTL=3600
CLUBIFY_CHECKOUT_DEBUG=false
```

#### 3. Instruções Pós-Instalação
```bash
📋 Próximos passos:

1. Configure suas credenciais no arquivo .env:
   - CLUBIFY_CHECKOUT_API_KEY
   - CLUBIFY_CHECKOUT_API_SECRET
   - CLUBIFY_CHECKOUT_TENANT_ID

2. Ajuste as configurações em config/clubify-checkout.php

3. Execute o comando de sincronização:
   php artisan clubify:sync

4. Teste a integração:
   php artisan clubify:test

📚 Documentação: https://docs.clubify.com/sdk/php
🆘 Suporte: https://github.com/clubify/checkout-sdk-php/issues
```

## 📦 clubify:publish

### Descrição

Comando para publicação seletiva de assets específicos. Oferece controle granular sobre quais componentes publicar, com suporte a dry-run para visualização prévia.

### Sintaxe

```bash
php artisan clubify:publish [asset] [options]
```

### Assets Disponíveis

| Asset | Descrição | Arquivos |
|-------|-----------|----------|
| `config` | Arquivos de configuração | `config/clubify-checkout.php` |
| `lang` | Arquivos de tradução | `resources/lang/*/messages.php` |
| `stubs` | Templates de código | `resources/stubs/*.stub` |
| `all` | Todos os assets | `config/`, `resources/` |

### Opções

| Opção | Descrição |
|-------|-----------|
| `--force` | Força sobrescrita de arquivos existentes |
| `--dry-run` | Mostra o que seria publicado sem executar |

### Exemplos de Uso

#### Publicação Interativa
```bash
# Comando sem parâmetros abre menu interativo
php artisan clubify:publish

📦 Qual asset você deseja publicar?
  [0] config - Arquivos de configuração
  [1] lang - Arquivos de tradução
  [2] stubs - Templates de código
  [3] all - Todos os assets
 >
```

#### Publicação Específica
```bash
# Publicar apenas configuração
php artisan clubify:publish config

📦 Publicando config...
✅ Asset 'config' publicado com sucesso!

📋 Próximos passos:
1. Edite o arquivo config/clubify-checkout.php
2. Configure as variáveis de ambiente no .env
3. Execute: php artisan config:cache
```

#### Dry Run
```bash
# Simular publicação sem executar
php artisan clubify:publish lang --dry-run

🔍 Simulação de publicação do asset: lang

📄 Descrição: Arquivos de tradução
🏷️  Tag: clubify-checkout-lang
📁 Arquivos que seriam publicados:
   - resources/lang/*/messages.php

💡 Para executar a publicação, remova a flag --dry-run
```

#### Publicação com Force
```bash
# Força sobrescrita de arquivos existentes
php artisan clubify:publish stubs --force

📦 Publicando stubs...
✅ Asset 'stubs' publicado com sucesso!

📋 Próximos passos:
1. Use os templates em resources/stubs/vendor/clubify-checkout/
2. Customize os stubs conforme sua aplicação
3. Execute comandos que usam os stubs
```

### Assets Detalhados

#### Config Asset
```bash
php artisan clubify:publish config

# Publica:
# └── config/clubify-checkout.php

# Próximos passos automáticos:
# 1. Editar configurações
# 2. Configurar .env
# 3. Cache da configuração
```

#### Lang Asset
```bash
php artisan clubify:publish lang

# Publica:
# └── resources/lang/vendor/clubify-checkout/
#     ├── en/
#     │   ├── validation.php
#     │   ├── messages.php
#     │   └── errors.php
#     └── pt-BR/
#         ├── validation.php
#         ├── messages.php
#         └── errors.php
```

#### Stubs Asset
```bash
php artisan clubify:publish stubs

# Publica:
# └── resources/stubs/vendor/clubify-checkout/
#     ├── controller.stub
#     ├── middleware.stub
#     ├── job.stub
#     ├── service.stub
#     └── webhook-handler.stub
```

## 🔄 clubify:sync

### Descrição

Comando para sincronização de dados e teste de conectividade com a API Clubify Checkout. Verifica saúde do sistema, autentica credenciais e sincroniza dados de cache.

### Sintaxe

```bash
php artisan clubify:sync [options]
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--test` | Executa apenas teste de conectividade |
| `--force` | Força sincronização mesmo com cache válido |
| `--clear-cache` | Limpa cache antes da sincronização |
| `--timeout=30` | Timeout para operações (segundos) |

### Exemplos de Uso

#### Sincronização Completa
```bash
php artisan clubify:sync

🔄 Iniciando sincronização com Clubify Checkout...
🔍 Testando conectividade...
  🔧 Testando inicialização do SDK...
     ✅ SDK inicializado (versão: 1.0.0)
  🌐 Testando conectividade básica...
     ✅ API acessível
     📊 Response time: 125ms
  🔐 Testando autenticação...
     ✅ Autenticação válida
  🧩 Testando módulos...
     ✅ Organização
     ✅ Produtos
     ✅ Checkout
     ✅ Pagamentos
     ✅ Clientes
     ✅ Webhooks

✅ Teste de conectividade concluído com sucesso!

📊 Executando sincronização completa...
  🏢 Sincronizando dados da organização...
     ✅ Dados da organização sincronizados
  📦 Sincronizando dados de produtos...
     ✅ Dados de produtos sincronizados
  👥 Sincronizando dados de clientes...
     ✅ Dados de clientes sincronizados
  🔗 Sincronizando configuração de webhooks...
     ✅ Configuração de webhooks sincronizada
  🗂️  Atualizando cache de configuração...
     ✅ Cache de configuração atualizado

🎉 Sincronização completa finalizada!
```

#### Teste de Conectividade
```bash
# Apenas teste, sem sincronização
php artisan clubify:sync --test

🔄 Iniciando sincronização com Clubify Checkout...
🔍 Testando conectividade...
  🔧 Testando inicialização do SDK...
     ✅ SDK inicializado (versão: 1.0.0)
  🌐 Testando conectividade básica...
     ✅ API acessível
     📊 Response time: 98ms
  🔐 Testando autenticação...
     ✅ Autenticação válida
  🧩 Testando módulos...
     ✅ Organização
     ✅ Produtos
     ✅ Checkout
     ✅ Pagamentos
     ⚠️  Clientes (com problemas)
     ✅ Webhooks

✅ Teste de conectividade concluído com sucesso!
```

#### Sincronização com Cache Limpo
```bash
# Limpa cache antes de sincronizar
php artisan clubify:sync --clear-cache

🔄 Iniciando sincronização com Clubify Checkout...
🗑️  Limpando cache...
     ✅ Cache limpo
🔍 Testando conectividade...
[... resto do processo ...]
```

#### Sincronização Forçada
```bash
# Força sincronização mesmo com cache válido
php artisan clubify:sync --force

# Útil para:
# - Atualizações urgentes de dados
# - Resolução de problemas de cache
# - Sincronização após mudanças de configuração
```

### Processo de Sincronização

#### 1. Teste de Conectividade
```bash
# Verifica:
✅ Inicialização do SDK
✅ Conectividade com API
✅ Autenticação válida
✅ Status de todos os módulos
```

#### 2. Sincronização de Dados
```bash
# Sincroniza e cacheia:
🏢 Dados da organização (TTL: 1h)
📦 Estatísticas de produtos (TTL: 30min)
👥 Estatísticas de clientes (TTL: 30min)
🔗 Configuração de webhooks (TTL: 1h)
🗂️  Configuração geral (TTL: 2h)
```

#### 3. Cache Management
```bash
# Chaves de cache utilizadas:
clubify.organization.status
clubify.products.stats
clubify.customers.stats
clubify.webhooks.config
clubify.configuration
```

## 💡 Exemplos Práticos

### Workflow de Desenvolvimento

```bash
# 1. Instalação inicial
php artisan clubify:install

# 2. Configurar credenciais no .env
# CLUBIFY_CHECKOUT_API_KEY=your-real-api-key
# CLUBIFY_CHECKOUT_API_SECRET=your-real-secret
# CLUBIFY_CHECKOUT_TENANT_ID=your-tenant-id

# 3. Teste de conectividade
php artisan clubify:sync --test

# 4. Publicar assets customizados
php artisan clubify:publish lang --force

# 5. Sincronização completa
php artisan clubify:sync
```

### Workflow de Deploy

```bash
# Automação para deploy
#!/bin/bash

echo "🚀 Iniciando deploy do Clubify Checkout SDK..."

# Publicar configuração atualizada
php artisan clubify:publish config --force

# Otimizar configuração Laravel
php artisan config:cache
php artisan optimize

# Teste de conectividade
php artisan clubify:sync --test

if [ $? -eq 0 ]; then
    echo "✅ Deploy concluído com sucesso!"

    # Sincronização em produção
    php artisan clubify:sync --force
else
    echo "❌ Falha no teste de conectividade!"
    exit 1
fi
```

### Monitoramento e Health Check

```bash
# Cron job para monitoramento (crontab)
# Executa teste de conectividade a cada 15 minutos
*/15 * * * * cd /path/to/project && php artisan clubify:sync --test >/dev/null 2>&1

# Sincronização diária completa
0 6 * * * cd /path/to/project && php artisan clubify:sync --clear-cache >/dev/null 2>&1
```

### Debug e Troubleshooting

```bash
# Debug detalhado
php artisan clubify:sync --test -v

# Limpar completamente cache
php artisan clubify:sync --clear-cache
php artisan cache:clear
php artisan config:clear

# Reinstalação completa
php artisan clubify:install --force
```

## 🔧 Customização de Commands

### Criando Command Personalizado

```php
<?php

namespace App\Console\Commands;

use ClubifyCheckout\ClubifyCheckoutSDK;
use Illuminate\Console\Command;

class ClubifyCustomCommand extends Command
{
    protected $signature = 'clubify:custom
                           {action : Ação a executar}
                           {--force : Força execução}';

    protected $description = 'Comando customizado para Clubify Checkout';

    public function __construct(private ClubifyCheckoutSDK $sdk)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'health' => $this->healthCheck(),
            'stats' => $this->showStats(),
            'clear' => $this->clearData(),
            default => $this->showHelp(),
        };
    }

    private function healthCheck(): int
    {
        $this->info('🔍 Executando health check personalizado...');

        $health = $this->sdk->healthCheck();

        if ($health['status'] === 'healthy') {
            $this->info('✅ Sistema saudável');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Sistema com problemas');
            return Command::FAILURE;
        }
    }

    private function showStats(): int
    {
        $this->info('📊 Estatísticas do SDK:');

        $stats = $this->sdk->getStats();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Versão', $stats['version']],
                ['Módulos', count($stats['modules'])],
                ['Operações', $stats['total_operations']],
                ['Cache Hits', $stats['cache_hits']],
            ]
        );

        return Command::SUCCESS;
    }
}
```

### Registro do Command

```php
// Em App\Console\Kernel.php
protected $commands = [
    \App\Console\Commands\ClubifyCustomCommand::class,
];

// Ou em ServiceProvider
public function boot()
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            \App\Console\Commands\ClubifyCustomCommand::class,
        ]);
    }
}
```

### Uso do Command Personalizado

```bash
# Health check personalizado
php artisan clubify:custom health

# Mostrar estatísticas
php artisan clubify:custom stats

# Comando com force
php artisan clubify:custom clear --force
```

## 🔍 Debug e Troubleshooting

### Problemas Comuns

#### Erro de Autenticação
```bash
❌ Erro do SDK: Authentication failed

# Soluções:
# 1. Verificar credenciais no .env
# 2. Validar tenant_id
# 3. Testar conectividade
php artisan clubify:sync --test
```

#### Erro de Conectividade
```bash
❌ Erro inesperado: Connection timeout

# Soluções:
# 1. Verificar conectividade de rede
# 2. Aumentar timeout
php artisan clubify:sync --timeout=60

# 3. Verificar proxy/firewall
# 4. Testar URL da API
```

#### Cache Corrompido
```bash
⚠️  Falha na sincronização: Invalid cache data

# Solução:
php artisan clubify:sync --clear-cache --force
```

### Logs Detalhados

```bash
# Habilitar debug detalhado
export CLUBIFY_CHECKOUT_DEBUG=true
php artisan clubify:sync -vvv

# Verificar logs
tail -f storage/logs/laravel.log | grep "Clubify"
```

### Verificação de Configuração

```bash
# Verificar configuração atual
php artisan config:show clubify-checkout

# Verificar variáveis de ambiente
php artisan env:show | grep CLUBIFY

# Validar arquivos publicados
php artisan vendor:publish --dry-run --tag=clubify-checkout
```

---

**Desenvolvido com ❤️ seguindo os padrões Laravel Artisan e melhores práticas de CLI.**