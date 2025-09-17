<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Exceptions\NetworkException;
use Clubify\Checkout\Exceptions\RateLimitException;
use Clubify\Checkout\Exceptions\UnauthorizedException;
use Clubify\Checkout\Exceptions\ServerException;

/**
 * Cliente HTTP para comunicação com a API Clubify
 *
 * Responsável por todas as comunicações HTTP com a API,
 * incluindo autenticação, retry automático, rate limiting
 * e logging de requests/responses.
 */
class HttpClient
{
    private Client $client;
    private Configuration $config;
    private Logger $logger;
    private int $retryAttempts;
    private array $defaultHeaders;

    public function __construct(Configuration $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->retryAttempts = $config->getRetryAttempts();

        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'ClubifyCheckoutSDK-PHP/' . $this->getSDKVersion(),
            'X-Tenant-ID' => $config->getTenantId(),
            'Authorization' => 'Bearer ' . $config->getApiKey()
        ];

        $this->client = new Client([
            'base_uri' => $config->getApiUrl(),
            'timeout' => $config->getTimeout(),
            'headers' => $this->defaultHeaders,
            'verify' => true,
            'http_errors' => false
        ]);

        $this->logger->info('HttpClient initialized', [
            'base_uri' => $config->getApiUrl(),
            'timeout' => $config->getTimeout(),
            'tenant_id' => $config->getTenantId()
        ]);
    }

    /**
     * Executa uma requisição GET
     */
    public function get(string $endpoint, array $params = []): array
    {
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->makeRequest('GET', $endpoint . $queryString);
    }

    /**
     * Executa uma requisição POST
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Executa uma requisição PUT
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('PUT', $endpoint, $data);
    }

    /**
     * Executa uma requisição PATCH
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->makeRequest('PATCH', $endpoint, $data);
    }

    /**
     * Executa uma requisição DELETE
     */
    public function delete(string $endpoint): array
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Executa uma requisição HTTP com retry automático
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->retryAttempts) {
            try {
                $startTime = microtime(true);
                $requestId = $this->generateRequestId();

                $this->logger->info("HTTP Request [{$requestId}]", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'data_size' => strlen(json_encode($data))
                ]);

                $options = [];
                if (!empty($data)) {
                    $options['json'] = $data;
                }

                $options['headers'] = array_merge($this->defaultHeaders, [
                    'X-Request-ID' => $requestId,
                    'X-SDK-Version' => $this->getSDKVersion()
                ]);

                $response = $this->client->request($method, $endpoint, $options);
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();

                $this->logger->info("HTTP Response [{$requestId}]", [
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'response_size' => strlen($responseBody)
                ]);

                // Verificar rate limiting
                $this->checkRateLimit($response->getHeaders());

                // Processar resposta baseada no status code
                return $this->processResponse($statusCode, $responseBody, $requestId);

            } catch (RequestException $e) {
                $lastException = $e;
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->logger->error("HTTP Request failed", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'response_time_ms' => $responseTime
                ]);

                if ($attempt === $this->retryAttempts) {
                    break;
                }

                // Calcular delay para retry (exponential backoff)
                $delay = min(pow(2, $attempt - 1) * 1000000, 10000000); // Max 10 segundos
                usleep($delay);

                $attempt++;
            }
        }

        // Se chegou aqui, todas as tentativas falharam
        throw new NetworkException(
            "Request failed after {$this->retryAttempts} attempts: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Processa a resposta HTTP baseada no status code
     */
    private function processResponse(int $statusCode, string $responseBody, string $requestId): array
    {
        $decodedResponse = json_decode($responseBody, true);

        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ServerException("Invalid JSON response: " . json_last_error_msg());
        }

        switch ($statusCode) {
            case 200:
            case 201:
            case 202:
                return $decodedResponse ?? [];

            case 400:
                throw new \Clubify\Checkout\Exceptions\ValidationException(
                    $decodedResponse['message'] ?? 'Bad Request',
                    $decodedResponse['errors'] ?? []
                );

            case 401:
                throw new UnauthorizedException(
                    $decodedResponse['message'] ?? 'Unauthorized'
                );

            case 404:
                throw new \Clubify\Checkout\Exceptions\NotFoundException(
                    $decodedResponse['message'] ?? 'Resource not found'
                );

            case 429:
                $retryAfter = $decodedResponse['retry_after'] ?? 60;
                throw new RateLimitException(
                    "Rate limit exceeded. Retry after {$retryAfter} seconds",
                    $retryAfter
                );

            case 500:
            case 502:
            case 503:
            case 504:
                throw new ServerException(
                    $decodedResponse['message'] ?? 'Internal Server Error',
                    $statusCode
                );

            default:
                throw new \Exception(
                    "Unexpected HTTP status code: {$statusCode}. Response: {$responseBody}"
                );
        }
    }

    /**
     * Verifica headers de rate limiting
     */
    private function checkRateLimit(array $headers): void
    {
        if (isset($headers['X-RateLimit-Remaining'][0])) {
            $remaining = (int) $headers['X-RateLimit-Remaining'][0];

            if ($remaining < 10) {
                $this->logger->warning('Rate limit approaching', [
                    'remaining_requests' => $remaining,
                    'reset_time' => $headers['X-RateLimit-Reset'][0] ?? 'unknown'
                ]);
            }
        }
    }

    /**
     * Gera um ID único para a requisição
     */
    private function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . mt_rand(1000, 9999);
    }

    /**
     * Obtém a versão do SDK
     */
    private function getSDKVersion(): string
    {
        // Em produção, isso viria de uma constante ou arquivo de versão
        return '2.0.0';
    }

    /**
     * Configura headers customizados para uma requisição
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $clone;
    }

    /**
     * Configura timeout customizado
     */
    public function withTimeout(int $timeout): self
    {
        $clone = clone $this;
        $clone->client = new Client(array_merge(
            $this->client->getConfig(),
            ['timeout' => $timeout]
        ));

        return $clone;
    }

    /**
     * Obtém estatísticas do cliente HTTP
     */
    public function getStats(): array
    {
        return [
            'base_uri' => $this->config->getApiUrl(),
            'timeout' => $this->config->getTimeout(),
            'retry_attempts' => $this->retryAttempts,
            'default_headers' => array_keys($this->defaultHeaders)
        ];
    }
}
