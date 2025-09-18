#!/bin/bash

# Script de execução dos diagnósticos do SDK
# Uso: ./run-diagnostics.sh [quick|full|help]

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para imprimir com cor
print_color() {
    echo -e "${1}${2}${NC}"
}

# Verificar se estamos no diretório correto
if [ ! -f "diagnostic-script.php" ]; then
    print_color $RED "❌ Erro: Execute este script no diretório examples/example-app/"
    exit 1
fi

# Verificar se vendor existe
if [ ! -d "../../vendor" ]; then
    print_color $RED "❌ Erro: Diretório vendor não encontrado. Execute 'composer install' primeiro."
    exit 1
fi

# Função para mostrar ajuda
show_help() {
    echo
    print_color $BLUE "🔧 CLUBIFY SDK DIAGNOSTIC TOOLS"
    echo
    echo "Uso: $0 [opção]"
    echo
    echo "Opções:"
    echo "  quick    - Diagnóstico rápido (5-10 segundos)"
    echo "  full     - Diagnóstico completo (30-60 segundos)"
    echo "  help     - Mostra esta ajuda"
    echo
    echo "Exemplos:"
    echo "  $0 quick   # Teste rápido para identificar dados mock"
    echo "  $0 full    # Análise completa de conectividade"
    echo
    print_color $YELLOW "💡 Dica: Use 'quick' para verificações diárias e 'full' para debugging"
    echo
}

# Verificar configuração do ambiente
check_environment() {
    print_color $BLUE "🔍 Verificando configuração do ambiente..."

    if [ ! -f ".env" ]; then
        print_color $YELLOW "⚠️  Arquivo .env não encontrado"
        print_color $YELLOW "   Usando configurações padrão ou variáveis de ambiente do sistema"
    else
        print_color $GREEN "✅ Arquivo .env encontrado"
    fi

    # Verificar variáveis essenciais
    if [ -z "$CLUBIFY_CHECKOUT_TENANT_ID" ] && ! grep -q "CLUBIFY_CHECKOUT_TENANT_ID" .env 2>/dev/null; then
        print_color $YELLOW "⚠️  CLUBIFY_CHECKOUT_TENANT_ID não configurado"
    fi

    if [ -z "$CLUBIFY_CHECKOUT_API_KEY" ] && ! grep -q "CLUBIFY_CHECKOUT_API_KEY" .env 2>/dev/null; then
        print_color $YELLOW "⚠️  CLUBIFY_CHECKOUT_API_KEY não configurado"
    fi

    echo
}

# Executar diagnóstico rápido
run_quick_diagnostic() {
    print_color $GREEN "🚀 Executando diagnóstico rápido..."
    echo

    start_time=$(date +%s)
    php quick-diagnostic.php
    end_time=$(date +%s)

    duration=$((end_time - start_time))
    echo
    print_color $BLUE "⏱️  Diagnóstico concluído em ${duration}s"
    echo
}

# Executar diagnóstico completo
run_full_diagnostic() {
    print_color $GREEN "🚀 Executando diagnóstico completo..."
    print_color $YELLOW "   (Isso pode levar 30-60 segundos...)"
    echo

    start_time=$(date +%s)
    php diagnostic-script.php
    end_time=$(date +%s)

    duration=$((end_time - start_time))
    echo
    print_color $BLUE "⏱️  Diagnóstico completo concluído em ${duration}s"

    if [ -f "diagnostic-results.json" ]; then
        print_color $GREEN "📄 Relatório detalhado salvo em diagnostic-results.json"
    fi
    echo
}

# Analisar últimos resultados
analyze_results() {
    if [ -f "diagnostic-results.json" ]; then
        print_color $BLUE "📊 Análise dos últimos resultados:"
        echo

        # Extrair estatísticas do JSON usando php
        php -r "
        \$data = json_decode(file_get_contents('diagnostic-results.json'), true);
        \$stats = \$data['statistics'];
        echo \"   ✅ Testes bem-sucedidos: \" . \$stats['successful_tests'] . \"/\" . \$stats['total_tests'] . \" (\" . \$stats['success_rate'] . \"%)\n\";
        echo \"   🎭 Dados mock detectados: \" . \$stats['mock_data_detected'] . \" (\" . \$stats['mock_rate'] . \"%)\n\";
        echo \"   ⏱️  Última execução: \" . \$data['diagnostic_info']['timestamp'] . \"\n\";
        "
        echo
    else
        print_color $YELLOW "📄 Nenhum resultado anterior encontrado. Execute primeiro um diagnóstico."
        echo
    fi
}

# Limpar resultados antigos
clean_results() {
    if [ -f "diagnostic-results.json" ]; then
        rm diagnostic-results.json
        print_color $GREEN "🧹 Resultados anteriores removidos"
    else
        print_color $YELLOW "🧹 Nenhum resultado anterior para remover"
    fi
    echo
}

# Parsing de argumentos
case "${1:-quick}" in
    "quick"|"q")
        check_environment
        run_quick_diagnostic
        ;;
    "full"|"f")
        check_environment
        run_full_diagnostic
        ;;
    "analyze"|"a")
        analyze_results
        ;;
    "clean"|"c")
        clean_results
        ;;
    "help"|"h"|"--help")
        show_help
        ;;
    *)
        print_color $RED "❌ Opção inválida: $1"
        show_help
        exit 1
        ;;
esac