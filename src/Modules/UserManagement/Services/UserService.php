<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;
use Clubify\Checkout\Modules\UserManagement\DTOs\UserData;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserNotFoundException;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserValidationException;

/**
 * Serviço de gestão de usuários refatorado
 *
 * Implementa a nova arquitetura usando Repository Pattern e ServiceInterface.
 * Responsável por:
 * - Business logic de usuários
 * - Validação de dados
 * - Orquestração de operações
 * - Gestão de eventos
 */
class UserService implements ServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private Logger $logger
    ) {
    }

    /**
     * Obtém o nome do serviço
     */
    public function getName(): string
    {
        return 'user_service';
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Verifica se o serviço está saudável (health check)
     */
    public function isHealthy(): bool
    {
        try {
            // Test repository health with a simple operation
            $this->repository->count();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('UserService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'repository_type' => get_class($this->repository),
            'timestamp' => time()
        ];
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'repository' => get_class($this->repository)
        ];
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available' => $this->isAvailable(),
            'repository' => get_class($this->repository),
            'timestamp' => time()
        ];
    }

    /**
     * Cria um novo usuário
     */
    public function createUser(array $userData): array
    {
        $this->logger->info('Creating user', ['email' => $userData['email'] ?? 'unknown']);

        try {
            // Validate and sanitize data
            $user = new UserData($userData);
            $user->validate();

            // Check for duplicates
            if (isset($userData['email']) && $this->repository->isEmailTaken($userData['email'])) {
                throw new UserValidationException('User with this email already exists');
            }

            // Create user
            $createdUser = $this->repository->create($user->toArray());

            $this->logger->info('User created successfully', [
                'user_id' => $createdUser['id'],
                'email' => $createdUser['email']
            ]);

            return [
                'success' => true,
                'user_id' => $createdUser['id'],
                'user' => $createdUser
            ];

        } catch (UserValidationException $e) {
            $this->logger->warning('User validation failed', [
                'error' => $e->getMessage(),
                'user_data' => $userData
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'user_data' => $userData
            ]);
            throw $e;
        }
    }

    /**
     * Obtém um usuário por ID
     */
    public function getUser(string $userId): array
    {
        try {
            $user = $this->repository->findById($userId);

            if (!$user) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (UserNotFoundException $e) {
            $this->logger->warning('User not found', ['user_id' => $userId]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza um usuário
     */
    public function updateUser(string $userId, array $userData): array
    {
        try {
            // Validate user exists
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            // Check email uniqueness if email is being updated
            if (isset($userData['email']) && $this->repository->isEmailTaken($userData['email'], $userId)) {
                throw new UserValidationException('Email is already in use by another user');
            }

            // Update user
            $updatedUser = $this->repository->update($userId, $userData);

            $this->logger->info('User updated successfully', [
                'user_id' => $userId,
                'updated_fields' => array_keys($userData)
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'user' => $updatedUser
            ];

        } catch (UserNotFoundException | UserValidationException $e) {
            $this->logger->warning('Cannot update user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Exclui um usuário
     */
    public function deleteUser(string $userId): array
    {
        try {
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            $deleted = $this->repository->delete($userId);

            if ($deleted) {
                $this->logger->info('User deleted successfully', ['user_id' => $userId]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'deleted_at' => date('c')
                ];
            }

            throw new \Exception('Failed to delete user');

        } catch (UserNotFoundException $e) {
            $this->logger->warning('Cannot delete user - not found', ['user_id' => $userId]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lista usuários com filtros
     */
    public function listUsers(array $filters = []): array
    {
        try {
            $users = $this->repository->findBy($filters);
            $total = $this->repository->count($filters);

            return [
                'success' => true,
                'users' => $users['data'] ?? $users,
                'total' => $total,
                'filters' => $filters,
                'pagination' => $users['pagination'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to list users', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateUserProfile(string $userId, array $profileData): array
    {
        try {
            $updatedProfile = $this->repository->updateProfile($userId, $profileData);

            $this->logger->info('User profile updated successfully', [
                'user_id' => $userId,
                'updated_fields' => array_keys($profileData)
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'profile' => $updatedProfile,
                'updated_at' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update user profile', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtém roles do usuário
     */
    public function getUserRoles(string $userId): array
    {
        try {
            $roles = $this->repository->getUserRoles($userId);

            return [
                'success' => true,
                'user_id' => $userId,
                'roles' => $roles['roles'] ?? $roles,
                'permissions' => $roles['permissions'] ?? []
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get user roles', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Busca usuário por email
     */
    public function findUserByEmail(string $email): array
    {
        try {
            $user = $this->repository->findByEmail($email);

            if (!$user) {
                throw new UserNotFoundException("User with email {$email} not found");
            }

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (UserNotFoundException $e) {
            $this->logger->warning('User not found by email', ['email' => $email]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find user by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Autentica um usuário
     */
    public function authenticateUser(string $email, string $password): array
    {
        try {
            $this->logger->info('Authenticating user', ['email' => $email]);

            $user = $this->repository->findByEmail($email);

            if (!$user) {
                throw new UserNotFoundException("User with email {$email} not found");
            }

            // Verify password (assuming repository has this method)
            $isValid = $this->repository->verifyPassword($email, $password);

            if (!$isValid) {
                throw new UserValidationException("Invalid credentials");
            }

            $this->logger->info('User authenticated successfully', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            return [
                'success' => true,
                'user_id' => $user['id'],
                'user' => $user,
                'authenticated' => true,
                'authenticated_at' => date('c')
            ];

        } catch (UserNotFoundException | UserValidationException $e) {
            $this->logger->warning('User authentication failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to authenticate user', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(string $userId, string $newPassword): array
    {
        try {
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            $success = $this->repository->changePassword($userId, $newPassword);

            if ($success) {
                $this->logger->info('Password changed successfully', ['user_id' => $userId]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Password changed successfully'
                ];
            }

            throw new \Exception('Failed to change password');

        } catch (UserNotFoundException $e) {
            $this->logger->warning('Cannot change password - user not found', ['user_id' => $userId]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to change password', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ativa usuário
     */
    public function activateUser(string $userId): array
    {
        try {
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            $success = $this->repository->activateUser($userId);

            if ($success) {
                $this->logger->info('User activated successfully', ['user_id' => $userId]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'status' => 'active'
                ];
            }

            throw new \Exception('Failed to activate user');

        } catch (UserNotFoundException $e) {
            $this->logger->warning('Cannot activate user - not found', ['user_id' => $userId]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to activate user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Desativa usuário
     */
    public function deactivateUser(string $userId): array
    {
        try {
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            $success = $this->repository->deactivateUser($userId);

            if ($success) {
                $this->logger->info('User deactivated successfully', ['user_id' => $userId]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'status' => 'inactive'
                ];
            }

            throw new \Exception('Failed to deactivate user');

        } catch (UserNotFoundException $e) {
            $this->logger->warning('Cannot deactivate user - not found', ['user_id' => $userId]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to deactivate user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
