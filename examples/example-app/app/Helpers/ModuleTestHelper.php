<?php

namespace App\Helpers;

use Exception;
use TypeError;

/**
 * Helper para testes de módulos do SDK
 */
class ModuleTestHelper
{
    /**
     * Testar um método específico
     */
    public static function testMethod($object, string $methodName, array $params = [], string $expectedType = 'mixed'): array
    {
        try {

            $result = call_user_func_array([$object, $methodName], $params);

            // Verificar tipo de retorno
            $actualType = gettype($result);
            $typeMatch = self::validateReturnType($result, $expectedType);

            if ($typeMatch) {
                return [
                    'method' => $methodName,
                    'success' => true,
                    'result' => self::formatReturnValue($result, $expectedType),
                    'raw_result' => $result,
                    'detailed_info' => self::extractDetailedInfo($result, $expectedType),
                    'response_type' => $actualType,
                    'error' => null
                ];
            } else {
                return [
                    'method' => $methodName,
                    'success' => false,
                    'result' => null,
                    'raw_result' => null,
                    'detailed_info' => null,
                    'response_type' => $actualType,
                    'error' => "Type mismatch: expected {$expectedType}, got {$actualType}"
                ];
            }
        } catch (Exception | TypeError $e) {
            return [
                'method' => $methodName,
                'success' => false,
                'result' => null,
                'raw_result' => null,
                'detailed_info' => null,
                'response_type' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validar tipo de retorno
     */
    private static function validateReturnType($result, string $expectedType): bool
    {
        if ($expectedType === 'void') {
            return true;
        } elseif ($expectedType === 'object') {
            return is_object($result);
        } elseif ($expectedType === 'mixed') {
            return true;
        } else {
            return gettype($result) === $expectedType;
        }
    }

    /**
     * Formatar valor de retorno para exibição
     */
    private static function formatReturnValue($value, string $expectedType): string
    {
        if ($expectedType === 'void') {
            return '✅ OK - Method executed';
        } elseif ($expectedType === 'boolean') {
            return ($value ? '✅ true' : '❌ false');
        } elseif ($expectedType === 'string') {
            return strlen($value) . ' chars: "' . substr($value, 0, 50) . (strlen($value) > 50 ? '..."' : '"');
        } elseif ($expectedType === 'array') {
            $parts = [];

            if (isset($value['id'])) {
                $parts[] = '🆔 ID: ' . $value['id'];
            }

            if (isset($value['success'])) {
                $parts[] = ($value['success'] ? '✅ Success' : '❌ Failed');
            }

            if (isset($value['data']['id'])) {
                $parts[] = '📦 Data ID: ' . $value['data']['id'];
            }

            if (isset($value['message'])) {
                $parts[] = '💬 ' . substr($value['message'], 0, 30) . (strlen($value['message']) > 30 ? '...' : '');
            }

            if (isset($value['status'])) {
                $parts[] = '📊 Status: ' . $value['status'];
            }

            if (empty($parts)) {
                $parts[] = '📋 Array (' . count($value) . ' items)';
            }

            return implode(' | ', $parts);

        } elseif ($expectedType === 'object') {
            return '🏗️ ' . get_class($value) . ' instance';
        } else {
            return (string)$value;
        }
    }

    /**
     * Extrair informações detalhadas do retorno
     */
    private static function extractDetailedInfo($value, string $expectedType): array
    {
        $info = [];

        if ($expectedType === 'void') {
            $info['status'] = 'Method executed successfully';
            $info['return_type'] = 'void';
        } elseif ($expectedType === 'boolean') {
            $info['status'] = $value ? 'true' : 'false';
            $info['return_type'] = 'boolean';
        } elseif ($expectedType === 'string') {
            $info['status'] = 'String returned';
            $info['length'] = strlen($value);
            $info['preview'] = substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '');
            $info['return_type'] = 'string';
        } elseif ($expectedType === 'array') {
            $info['return_type'] = 'array';
            $info['item_count'] = count($value);

            // Informações específicas de resposta da API
            if (isset($value['id'])) {
                $info['id'] = $value['id'];
                $info['operation'] = 'Resource with ID returned';
            }

            if (isset($value['success'])) {
                $info['api_success'] = $value['success'] ? 'true' : 'false';
                $info['operation'] = 'API response with success status';
            }

            if (isset($value['data'])) {
                $info['has_data'] = true;
                if (is_array($value['data']) && isset($value['data']['id'])) {
                    $info['data_id'] = $value['data']['id'];
                }
            }

            if (isset($value['message'])) {
                $info['message'] = $value['message'];
            }

            if (isset($value['status'])) {
                $info['status'] = $value['status'];
            }

            // Capturar chaves principais do array
            $info['keys'] = array_keys($value);

        } elseif ($expectedType === 'object') {
            $info['return_type'] = 'object';
            $info['class'] = get_class($value);
            $info['status'] = 'Object instance returned';

            // Propriedades públicas se houver
            $publicProps = get_object_vars($value);
            if (!empty($publicProps)) {
                $info['public_properties'] = array_keys($publicProps);
            }
        }

        return $info;
    }

    /**
     * Calcular estatísticas dos testes
     */
    public static function calculateStats(array $results): array
    {
        $totalMethods = 0;
        $workingMethods = 0;
        $errorMethods = 0;

        foreach ($results as $moduleResults) {
            foreach ($moduleResults as $result) {
                $totalMethods++;
                if ($result['success']) {
                    $workingMethods++;
                } else {
                    $errorMethods++;
                }
            }
        }

        $successRate = $totalMethods > 0 ? round(($workingMethods / $totalMethods) * 100, 2) : 0;

        return [
            'totalMethods' => $totalMethods,
            'workingMethods' => $workingMethods,
            'errorMethods' => $errorMethods,
            'successRate' => $successRate
        ];
    }
}