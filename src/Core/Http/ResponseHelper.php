<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Helper para trabalhar com respostas HTTP
 *
 * Fornece métodos utilitários para verificar status de respostas HTTP
 * e extrair dados JSON de forma segura.
 */
class ResponseHelper
{
    /**
     * Verifica se a resposta HTTP é bem-sucedida (status 200-299)
     */
    public static function isSuccessful(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        return $statusCode >= 200 && $statusCode < 300;
    }

    /**
     * Extrai dados JSON da resposta de forma segura
     *
     * @return array|null Dados decodificados ou null em caso de erro
     */
    public static function getData(ResponseInterface $response): ?array
    {
        try {
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $data;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Verifica se a resposta é bem-sucedida e retorna os dados
     *
     * @return array|null Dados se bem-sucedida, null caso contrário
     */
    public static function getDataIfSuccessful(ResponseInterface $response): ?array
    {
        if (!self::isSuccessful($response)) {
            return null;
        }

        return self::getData($response);
    }
}