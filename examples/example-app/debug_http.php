<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "=== Debug HTTP Client ===\n\n";

try {
    // Configuração
    $config = new Configuration([
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'environment' => 'sandbox'
        ],
        'endpoints' => [
            'base_url' => 'https://checkout.svelve.com/api/v1'
        ]
    ]);

    echo "1. Verificando configuração:\n";
    echo "Base URL: " . $config->getBaseUrl() . "\n";
    echo "Tenant ID: " . $config->getTenantId() . "\n";
    echo "Headers padrão:\n";
    print_r($config->getDefaultHeaders());
    echo "\n";

    echo "2. Criando cliente HTTP customizado com debug...\n";

    // Criar logger para capturar requisições
    $logger = new Logger('guzzle');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

    // Criar cliente com middleware de logging
    $client = new \GuzzleHttp\Client([
        'base_uri' => $config->getBaseUrl(),
        'timeout' => 30,
        'headers' => $config->getDefaultHeaders(),
        'handler' => tap(\GuzzleHttp\HandlerStack::create(), function ($stack) use ($logger) {
            $stack->push(
                Middleware::log(
                    $logger,
                    new MessageFormatter('{method} {uri} HTTP/{version} {req_headers} {req_body}')
                )
            );
        })
    ]);

    echo "3. Fazendo requisição direta com Guzzle...\n";

    $response = $client->post('/api-keys/public/validate', [
        'json' => [
            'apiKey' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2'
        ],
        'headers' => $config->getDefaultHeaders() // Forçar headers novamente
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . $response->getBody()->getContents() . "\n";

} catch (\Throwable $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}