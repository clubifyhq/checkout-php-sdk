<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Http;

use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Http\Interceptor\InterceptorInterface;
use Clubify\Checkout\Core\Http\Retry\RetryStrategy;
use Clubify\Checkout\Enums\HttpMethod;
use Clubify\Checkout\Exceptions\HttpException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente HTTP para o Clubify SDK
 *
 * Fornece funcionalidades de retry automático, interceptors,
 * timeout e error handling usando Guzzle como base.
 */
class Client
{
    private GuzzleClient $client;
    private ConfigurationInterface $config;
    private RetryStrategy $retryStrategy;
    private array $interceptors = [];

    public function __construct(ConfigurationInterface $config)
    {
        $this->config = $config;
        $this->retryStrategy = new RetryStrategy(
            $config->getMaxRetries(),
            $config->getRetryConfig()['delay'] ?? 1000,
            $config->getRetryConfig()['backoff'] ?? 'exponential',
            $config->getRetryConfig()['max_delay'] ?? 30000
        );

        $this->client = $this->createGuzzleClient();
    }

    /**
     * Realizar requisição HTTP
     */
    public function request(
        string $method,
        string $uri,
        array $options = []
    ): ResponseInterface {
        $httpMethod = HttpMethod::from(strtoupper($method));
        $request = $this->buildRequest($httpMethod, $uri, $options);

        return $this->executeWithRetry($request);
    }

    /**
     * GET request
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * Realiza teste de conectividade com a API
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->get('/health');
            $data = json_decode($response->getBody()->getContents(), true);
            return isset($data['status']) && in_array($data['status'], ['healthy', 'ok']);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * POST request
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * PUT request
     */
    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * PATCH request
     */
    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * DELETE request
     */
    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * Adicionar interceptor
     */
    public function addInterceptor(InterceptorInterface $interceptor): self
    {
        $this->interceptors[] = $interceptor;

        // Ordenar por prioridade (maior primeiro)
        usort($this->interceptors, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $this;
    }

    /**
     * Remover todos os interceptors
     */
    public function clearInterceptors(): self
    {
        $this->interceptors = [];
        return $this;
    }

    /**
     * Obter cliente Guzzle interno (para casos avançados)
     */
    public function getGuzzleClient(): GuzzleClient
    {
        return $this->client;
    }

    /**
     * Criar cliente Guzzle configurado
     */
    private function createGuzzleClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        // Adicionar middleware de retry
        $stack->push(
            Middleware::retry(
                function (
                    $retries,
                    RequestInterface $request,
                    ?ResponseInterface $response = null,
                    ?\Throwable $exception = null
                ) {
                    return $this->retryStrategy->shouldRetry(
                        $retries,
                        $exception,
                        $response,
                        $request
                    );
                },
                function ($retries) {
                    $delay = $this->retryStrategy->calculateDelay($retries);
                    return $delay * 1000; // Guzzle espera microsegundos
                }
            )
        );

        return new GuzzleClient([
            'base_uri' => $this->config->getBaseUrl(),
            'timeout' => $this->config->getTimeout() / 1000, // Guzzle espera segundos
            'connect_timeout' => $this->config->getHttpConfig()['connect_timeout'] ?? 10,
            'verify' => $this->config->getHttpConfig()['verify_ssl'] ?? true,
            'headers' => $this->config->getDefaultHeaders(),
            'handler' => $stack,
        ]);
    }

    /**
     * Construir requisição
     */
    private function buildRequest(
        HttpMethod $method,
        string $uri,
        array $options
    ): RequestInterface {
        // Resolver URI completa
        $fullUri = $this->resolveUri($uri, $options['query'] ?? []);

        // Preparar headers
        $headers = array_merge(
            $this->config->getDefaultHeaders(),
            $options['headers'] ?? []
        );

        // Preparar body
        $body = null;
        if ($method->allowsBody() && isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers['Content-Type'] = 'application/json';
        } elseif ($method->allowsBody() && isset($options['body'])) {
            $body = $options['body'];
        }

        return new Request($method->value, $fullUri, $headers, $body);
    }

    /**
     * Executar requisição com retry
     */
    private function executeWithRetry(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->retryStrategy->getMaxAttempts()) {
            $attempt++;

            try {
                // Aplicar interceptors de request
                $processedRequest = $this->applyRequestInterceptors($request);

                // Executar requisição
                $response = $this->client->send($processedRequest);

                // Aplicar interceptors de response
                $processedResponse = $this->applyResponseInterceptors($response, $processedRequest);

                return $processedResponse;

            } catch (RequestException $e) {
                $lastException = $e;

                // Verificar se deve tentar novamente
                if (!$this->retryStrategy->shouldRetry(
                    $attempt - 1,
                    $e,
                    $e->getResponse(),
                    $request
                )) {
                    break;
                }

                // Aplicar delay antes da próxima tentativa
                if ($attempt <= $this->retryStrategy->getMaxAttempts()) {
                    $delay = $this->retryStrategy->calculateDelay($attempt - 1);
                    $this->retryStrategy->delay($delay);
                }
            }
        }

        // Se chegou aqui, todas as tentativas falharam
        throw new HttpException(
            'HTTP request failed after ' . $this->retryStrategy->getMaxAttempts() . ' attempts',
            $lastException?->getCode() ?? 0,
            $lastException,
            $request,
            $lastException?->getResponse(),
            [
                'attempts' => $attempt - 1,
                'uri' => (string) $request->getUri(),
                'method' => $request->getMethod(),
            ]
        );
    }

    /**
     * Aplicar interceptors de request
     */
    private function applyRequestInterceptors(RequestInterface $request): RequestInterface
    {
        $processedRequest = $request;

        foreach ($this->interceptors as $interceptor) {
            $processedRequest = $interceptor->interceptRequest($processedRequest);
        }

        return $processedRequest;
    }

    /**
     * Aplicar interceptors de response
     */
    private function applyResponseInterceptors(
        ResponseInterface $response,
        RequestInterface $request
    ): ResponseInterface {
        $processedResponse = $response;

        // Aplicar interceptors em ordem reversa para response
        foreach (array_reverse($this->interceptors) as $interceptor) {
            $processedResponse = $interceptor->interceptResponse($processedResponse, $request);
        }

        return $processedResponse;
    }

    /**
     * Resolver URI com query parameters
     */
    private function resolveUri(string $uri, array $query = []): string
    {
        $fullUri = $uri;

        if (!empty($query)) {
            $queryString = http_build_query($query);
            $separator = str_contains($uri, '?') ? '&' : '?';
            $fullUri .= $separator . $queryString;
        }

        return $fullUri;
    }
}