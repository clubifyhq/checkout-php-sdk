<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Helper para respostas JSON consistentes
 */
class ResponseHelper
{
    /**
     * Resposta de sucesso padrão
     */
    public static function success(array $data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $status);
    }

    /**
     * Resposta de erro padrão
     */
    public static function error(string $message, int $status = 500, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $status);
    }

    /**
     * Resposta de erro com detalhes da exception
     */
    public static function exception(Throwable $e, string $message = 'Internal Server Error', int $status = 500): JsonResponse
    {
        $errorData = [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];

        // Adicionar trace em desenvolvimento
        if (app()->environment(['local', 'development'])) {
            $errorData['trace'] = $e->getTraceAsString();
        }

        return self::error($message, $status, $errorData);
    }

    /**
     * Resposta de debug com informações do SDK
     */
    public static function debug(array $debugInfo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'debug_info' => $debugInfo,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment()
        ]);
    }

    /**
     * Resposta de módulo testado
     */
    public static function moduleTest(string $moduleName, bool $success, array $results = [], ?Throwable $error = null): JsonResponse
    {
        $data = [
            'module' => $moduleName,
            'test_success' => $success,
            'results' => $results
        ];

        if ($error) {
            $data['error'] = [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine()
            ];
        }

        return response()->json([
            'success' => $success,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $success ? 200 : 500);
    }

    /**
     * Resposta de status geral
     */
    public static function status(array $statusInfo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $statusInfo,
            'timestamp' => now()->toISOString(),
            'server_time' => now()->format('Y-m-d H:i:s T')
        ]);
    }
}