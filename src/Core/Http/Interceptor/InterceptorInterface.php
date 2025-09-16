<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Http\Interceptor;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface InterceptorInterface
{
    /**
     * Interceptar requisição antes do envio
     */
    public function interceptRequest(RequestInterface $request): RequestInterface;

    /**
     * Interceptar resposta após o recebimento
     */
    public function interceptResponse(ResponseInterface $response, RequestInterface $request): ResponseInterface;

    /**
     * Obter prioridade do interceptor (maior = primeiro)
     */
    public function getPriority(): int;
}