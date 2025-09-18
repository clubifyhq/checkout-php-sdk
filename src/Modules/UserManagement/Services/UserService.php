<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Modules\UserManagement\DTOs\UserData;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use DateTime;

/**
 * Serviço de gestão de usuários
 *
 * CRUD completo de usuários com validação e segurança.
 * Responsável por:
 * - CRUD de usuários
 * - Autenticação e autorização
 * - Gestão de perfis
 * - Validação de dados
 */
class UserService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'user-management';
    }

    /**
     * Verifica disponibilidade do serviço
     */
    protected function checkAvailability(): bool
    {
        try {
            $response = $this->httpClient->get('/health');
            return $response->isSuccessful();
        } catch (\Exception) {
            return false;
        }
    }

    public function createUser(array $userData): array
    {
        return $this->executeWithMetrics('create_user', function () use ($userData) {
            $user = new UserData($userData);
            $user->validate();

            $this->logger->info('Creating user', ['email' => $user->email]);

            $response = $this->httpClient->post('/users', $user->toArray());

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to create user: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $responseData = $response->getData();

            return [
                'success' => true,
                'user_id' => $responseData['id'] ?? $responseData['user_id'],
                'user' => $responseData['user'] ?? $responseData,
            ];
        });
    }

    public function getUser(string $userId): array
    {
        return $this->executeWithMetrics('get_user', function () use ($userId) {
            $response = $this->httpClient->get("/users/{$userId}");

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to get user: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $responseData = $response->getData();

            return [
                'success' => true,
                'user' => $responseData['user'] ?? $responseData,
            ];
        });
    }

    public function updateUser(string $userId, array $userData): array
    {
        return $this->executeWithMetrics('update_user', function () use ($userId, $userData) {
            $this->logger->info('Updating user', ['user_id' => $userId]);

            $response = $this->httpClient->put("/users/{$userId}", $userData);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to update user: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $responseData = $response->getData();

            return [
                'success' => true,
                'user_id' => $userId,
                'user' => $responseData['user'] ?? $responseData,
                'updated_at' => $responseData['updated_at'] ?? (new DateTime())->format('c'),
            ];
        });
    }

    public function deleteUser(string $userId): array
    {
        return $this->executeWithMetrics('delete_user', function () use ($userId) {
            $this->logger->info('Deleting user', ['user_id' => $userId]);

            $response = $this->httpClient->delete("/users/{$userId}");

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to delete user: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            return [
                'success' => true,
                'user_id' => $userId,
                'deleted_at' => (new DateTime())->format('c'),
            ];
        });
    }

    public function listUsers(array $filters = []): array
    {
        return $this->executeWithMetrics('list_users', function () use ($filters) {
            $queryParams = http_build_query($filters);
            $endpoint = '/users' . ($queryParams ? '?' . $queryParams : '');

            $response = $this->httpClient->get($endpoint);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to list users: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $responseData = $response->getData();

            return [
                'success' => true,
                'users' => $responseData['users'] ?? $responseData['data'] ?? [],
                'total' => $responseData['total'] ?? count($responseData['users'] ?? []),
                'filters' => $filters,
                'pagination' => $responseData['pagination'] ?? null,
            ];
        });
    }

    public function updateUserProfile(string $userId, array $profileData): array
    {
        return $this->executeWithMetrics('update_user_profile', function () use ($userId, $profileData) {
            $this->logger->info('Updating user profile', ['user_id' => $userId]);

            $response = $this->httpClient->patch("/users/{$userId}/profile", $profileData);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to update user profile: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $responseData = $response->getData();

            return [
                'success' => true,
                'user_id' => $userId,
                'profile' => $responseData['profile'] ?? $responseData,
                'updated_at' => $responseData['updated_at'] ?? (new DateTime())->format('c'),
            ];
        });
    }
}
