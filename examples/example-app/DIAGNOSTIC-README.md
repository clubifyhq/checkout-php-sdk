# 🔧 Scripts de Diagnóstico - Clubify Checkout SDK

## 📋 Visão Geral

Este conjunto de scripts foi desenvolvido para facilitar o diagnóstico de conectividade e identificação de dados mock vs reais no SDK PHP do Clubify Checkout.

## 🎯 Problemas que Resolvem

- ✅ **Identificar dados mock** vs dados reais da API
- ✅ **Testar conectividade** de todos os endpoints
- ✅ **Validar configurações** de credenciais e ambiente
- ✅ **Analisar performance** de módulos individuais
- ✅ **Facilitar debugging** com retornos detalhados em texto

## 📁 Arquivos Incluídos

### 🚀 Scripts Principais

1. **`run-diagnostics.sh`** - Script principal de execução
2. **`quick-diagnostic.php`** - Diagnóstico rápido (5-10s)
3. **`diagnostic-script.php`** - Diagnóstico completo (30-60s)

### 📄 Documentação

4. **`DIAGNOSTIC-README.md`** - Este arquivo
5. **`validation-strategy.md`** - Estratégia de validação contínua
6. **`routes-validation-report.md`** - Relatório de validação de rotas

## 🚀 Como Usar

### Método 1: Script Bash (Recomendado)

```bash
# Diagnóstico rápido (identifica mock vs real data)
./run-diagnostics.sh quick

# Diagnóstico completo (análise detalhada)
./run-diagnostics.sh full

# Ajuda
./run-diagnostics.sh help
```

### Método 2: Execução Direta PHP

```bash
# Diagnóstico rápido
php quick-diagnostic.php

# Diagnóstico completo
php diagnostic-script.php
```

## 📊 Tipos de Diagnóstico

### 🏃‍♂️ Quick Diagnostic (Rápido)

**Tempo**: 5-10 segundos
**Objetivo**: Identificar rapidamente se o SDK está retornando dados mock ou reais

**O que testa**:
- ✅ Customers::createCustomer()
- ✅ Products::listProducts()
- ✅ Payments::getPaymentMethods()
- ✅ Organization::getInfo()
- ✅ Customers::findByEmail()

**Exemplo de saída**:
```
🧪 Testando customers::createCustomer()... 🎭 MOCK
     ↳ Indicadores: test, predictable_id
🧪 Testando products::listProducts()... 🌐 REAL
🧪 Testando payments::getPaymentMethods()... 🌐 REAL

=== RESUMO ===
📊 Total de testes: 5
🎭 Dados mock: 1 (20.0%)
🌐 Dados reais: 4 (80.0%)
✅ SUCESSO: Conectando com dados reais da API!
```

### 🔍 Full Diagnostic (Completo)

**Tempo**: 30-60 segundos
**Objetivo**: Análise completa de conectividade, performance e identificação de dados mock

**O que testa**:
- 🔌 Conectividade HTTP básica
- 📊 Status geral do SDK
- 🧩 Todos os 9 módulos disponíveis
- 🔧 Métodos específicos de cada módulo
- 🌐 Conexões reais da API
- 🎭 Análise detalhada de dados mock vs reais

**Gera arquivo**: `diagnostic-results.json` com todos os detalhes

## 🎭 Detecção de Dados Mock

### Indicadores Automáticos

O sistema detecta dados mock através de:

1. **Padrões de ID previsíveis**:
   - `customer_123`, `user_456`
   - IDs sequenciais simples

2. **Emails de teste**:
   - `test@example.com`
   - `demo@clubify.com`
   - Qualquer email com "test", "demo", "mock"

3. **Valores suspeitos**:
   - Timestamps muito redondos (ex: `1234567890`)
   - Valores monetários muito redondos (ex: `10000`, `25000`)
   - Nomes genéricos ("Test User", "Sample Product")

4. **Texto óbvio**:
   - Presença de palavras: "test", "mock", "demo", "sample"

### Exemplo de Análise Mock

```json
{
  "mock_indicators": ["test", "predictable_id"],
  "mock_analysis": {
    "suspicious_timestamps": [1234567890],
    "round_numbers": [10000, 25000],
    "test_emails": ["test@example.com"],
    "predictable_ids": ["customer_123"]
  }
}
```

## 📈 Interpretação dos Resultados

### Taxa de Sucesso

- **90%+**: ✅ Excelente - SDK funcionando perfeitamente
- **70-90%**: 👍 Bom - Algumas falhas menores
- **<70%**: ⚠️ Atenção - Problemas de conectividade

### Taxa de Dados Mock

- **0-10%**: 🌐 Conectando com API real
- **10-50%**: 🔧 Misto (normal para desenvolvimento)
- **50%+**: 🎭 Principalmente dados mock

## 🔧 Configuração

### Variáveis de Ambiente

Crie um arquivo `.env` ou configure as variáveis:

```bash
CLUBIFY_CHECKOUT_TENANT_ID=seu_tenant_id_aqui
CLUBIFY_CHECKOUT_API_KEY=sua_api_key_aqui
CLUBIFY_CHECKOUT_ENVIRONMENT=sandbox  # ou production
CLUBIFY_CHECKOUT_API_URL=https://checkout.svelve.com/api/v1
```

### Exemplo de .env

```bash
# Clubify Checkout SDK Configuration
CLUBIFY_CHECKOUT_TENANT_ID=68c05e15ad23f0f6aaa1ae51
CLUBIFY_CHECKOUT_API_KEY=clb_test_4186d572ddb73ffdf6e1907cacff58b2
CLUBIFY_CHECKOUT_ENVIRONMENT=sandbox
CLUBIFY_CHECKOUT_API_URL="https://checkout.svelve.com/api/v1"
CLUBIFY_CHECKOUT_TIMEOUT=5
CLUBIFY_CHECKOUT_RETRIES=1
```

## 🐛 Troubleshooting

### Problemas Comuns

#### 1. "SDK retornando só dados mock"

**Possíveis causas**:
- Credenciais inválidas ou expiradas
- Apontando para ambiente errado (sandbox vs production)
- Tenant ID incorreto

**Solução**:
```bash
# Verificar configuração
./run-diagnostics.sh quick

# Conferir variáveis
echo $CLUBIFY_CHECKOUT_TENANT_ID
echo $CLUBIFY_CHECKOUT_API_KEY
```

#### 2. "Muitos testes falhando"

**Possíveis causas**:
- Problemas de rede/firewall
- Timeout muito baixo
- Serviço da API indisponível

**Solução**:
```bash
# Diagnóstico completo para detalhes
./run-diagnostics.sh full

# Verificar conectividade básica
curl -I https://checkout.svelve.com/api/v1/health
```

#### 3. "Erro de autoload"

**Solução**:
```bash
# Instalar dependências
cd ../../
composer install
cd examples/example-app/
```

## 📊 Logs e Relatórios

### Arquivo de Resultados

O diagnóstico completo gera `diagnostic-results.json`:

```json
{
  "diagnostic_info": {
    "timestamp": "2025-09-18 15:30:45",
    "total_time": 45.23,
    "sdk_version": "1.0.0",
    "environment": "sandbox"
  },
  "statistics": {
    "total_tests": 25,
    "successful_tests": 23,
    "failed_tests": 2,
    "mock_data_detected": 5,
    "success_rate": 92.0,
    "mock_rate": 20.0
  },
  "detailed_results": [...]
}
```

### Análise de Resultados

```bash
# Ver última análise
./run-diagnostics.sh analyze

# Limpar resultados antigos
./run-diagnostics.sh clean
```

## 🎯 Casos de Uso

### 1. Verificação Diária

```bash
# Verificação rápida toda manhã
./run-diagnostics.sh quick
```

### 2. Deploy/Mudança de Ambiente

```bash
# Diagnóstico completo após mudanças
./run-diagnostics.sh full
```

### 3. Debugging de Problemas

```bash
# Análise detalhada para investigação
./run-diagnostics.sh full
# Revisar diagnostic-results.json
```

### 4. Validação de Credenciais

```bash
# Verificar se credenciais estão funcionando
./run-diagnostics.sh quick
# Se >50% mock = problema nas credenciais
```

## 🔗 Integração com CI/CD

### GitHub Actions

```yaml
- name: SDK Diagnostic
  run: |
    cd sdk/php/examples/example-app
    ./run-diagnostics.sh quick
```

### Jenkins

```groovy
stage('SDK Diagnostic') {
    steps {
        sh 'cd sdk/php/examples/example-app && ./run-diagnostics.sh full'
        archiveArtifacts 'sdk/php/examples/example-app/diagnostic-results.json'
    }
}
```

## 💡 Dicas Avançadas

### Automatização

```bash
# Criar alias para facilitar uso
echo 'alias clubify-diag="cd /path/to/example-app && ./run-diagnostics.sh"' >> ~/.bashrc

# Usar assim:
clubify-diag quick
clubify-diag full
```

### Monitoramento

```bash
# Script para monitoramento contínuo
while true; do
    ./run-diagnostics.sh quick
    if [ $? -ne 0 ]; then
        echo "Problema detectado!"
        ./run-diagnostics.sh full
        break
    fi
    sleep 300  # 5 minutos
done
```

## 🤝 Contribuição

Para melhorar estes scripts:

1. Adicione novos padrões de detecção de mock em `detectMockData()`
2. Inclua novos módulos em `testAllModules()`
3. Expanda análises em `analyzeMockPatterns()`
4. Documente novos casos de uso

## 📞 Suporte

Se encontrar problemas:

1. Execute o diagnóstico completo: `./run-diagnostics.sh full`
2. Revise o arquivo `diagnostic-results.json`
3. Verifique as configurações de ambiente
4. Consulte a documentação principal do SDK