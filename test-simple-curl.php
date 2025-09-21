<?php

echo "=== Teste Manual do Endpoint ===\n\n";

// Comando curl para testar o endpoint
$curlCommand = "curl -s -w \"\\nHTTP Status: %{http_code}\\n\" " .
               "-X POST https://checkout.svelve.com/api/v1/auth/api-key/token " .
               "-H \"Content-Type: application/json\" " .
               "-H \"X-Tenant-ID: 507f1f77bcf86cd799439011\" " .
               "-d '{\"api_key\":\"clb_test_c6eb0dda0da66cb65cf92dad27456bbd\",\"tenant_id\":\"507f1f77bcf86cd799439011\",\"grant_type\":\"api_key\"}'";

echo "Executando comando curl:\n";
echo $curlCommand . "\n\n";

echo "Resultado:\n";
$result = shell_exec($curlCommand . ' 2>&1');
echo $result . "\n";

// Analisar resultado
if (strpos($result, '404') !== false) {
    echo "❌ PROBLEMA: Endpoint não encontrado (404)\n";
    echo "   O nginx ainda não foi reiniciado ou a configuração não foi aplicada\n";
    echo "   Solução: docker-compose restart nginx-proxy\n";
} elseif (strpos($result, '401') !== false) {
    echo "⚠️  ESPERADO: Endpoint existe mas credenciais inválidas (401)\n";
    echo "   Isso significa que o nginx está funcionando!\n";
} elseif (strpos($result, '200') !== false) {
    echo "✅ SUCESSO: Endpoint funcionando e credenciais válidas!\n";
} elseif (strpos($result, '500') !== false) {
    echo "❌ ERRO SERVIDOR: Problema interno no backend (500)\n";
} else {
    echo "❓ RESULTADO INESPERADO\n";
}

echo "\n=== Fim do teste ===\n";