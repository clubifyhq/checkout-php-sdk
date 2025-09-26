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
    public function createUser(array $userData, ?string $tenantId = null): array
    {
        $this->logger->info('Creating user', ['email' => $userData['email'] ?? 'unknown']);

        try {
            // Validate and sanitize data
            $user = new UserData($userData);
            $user->validate();

            // Try to find existing user first
            $existingUser = null;
            try {
                $existingUser = $this->repository->findByEmail($userData['email'], $tenantId);
            } catch (\Exception $e) {
                // Log but continue - search might fail due to API inconsistency
                $this->logger->debug('User search failed, will attempt creation anyway', [
                    'email' => $userData['email'],
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
            }

            if ($existingUser) {
                throw new UserValidationException('User with this email already exists');
            }

            // Prepare user data with tenantId if provided
            $userDataToCreate = $user->toArray();
            if ($tenantId) {
                $userDataToCreate['tenantId'] = $tenantId;
            }

            // Prepare headers for repository call
            $headers = [];
            if ($tenantId) {
                $headers['X-Tenant-Id'] = $tenantId;
            }

            // Create user with tenant context
            try {
                $createdUser = $this->repository->createWithHeaders($userDataToCreate, $headers);

                $this->logger->info('User created successfully', [
                    'user_id' => $createdUser['id'],
                    'email' => $createdUser['email'],
                    'tenant_id' => $tenantId
                ]);

                return [
                    'success' => true,
                    'user_id' => $createdUser['id'],
                    'user' => $createdUser
                ];
            } catch (\Exception $e) {
                // If we get a 409 conflict about user existing, try to find and return the existing user
                $errorMessage = $e->getMessage();
                if (strpos($errorMessage, 'User with this email already exists') !== false ||
                    strpos($errorMessage, '409') !== false ||
                    strpos($errorMessage, 'HTTP request failed after 3 attempts') !== false) {

                    // Additional check for HttpException with 409 status code
                    $is409Error = false;
                    if ($e instanceof \Clubify\Checkout\Exceptions\HttpException && $e->getStatusCode() === 409) {
                        $is409Error = true;
                    }
                    if (strpos($errorMessage, 'User with this email already exists') !== false) {
                        $is409Error = true;
                    }

                    if ($is409Error) {
                        $this->logger->info('User already exists, attempting to retrieve existing user', [
                            'email' => $userData['email'],
                            'tenant_id' => $tenantId,
                            'exception_type' => get_class($e),
                            'error_message' => $errorMessage
                        ]);

                        // Try to find the existing user using different approaches
                        $existingUser = $this->findExistingUser($userData['email'], $tenantId);

                        if ($existingUser) {
                            $this->logger->info('Found existing user after creation conflict', [
                                'user_id' => $existingUser['id'],
                                'email' => $existingUser['email'],
                                'tenant_id' => $tenantId
                            ]);

                            return [
                                'success' => true,
                                'user_id' => $existingUser['id'],
                                'user' => $existingUser,
                                'already_existed' => true
                            ];
                        } else {
                            $this->logger->warning('Could not find existing user after 409 conflict', [
                                'email' => $userData['email'],
                                'tenant_id' => $tenantId
                            ]);
                        }
                    }
                }

                throw $e; // Re-throw if not a 409 or couldn't find existing user
            }

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
    public function updateUser(string $userId, array $userData, ?string $tenantId = null): array
    {
        try {
            // Validate user exists
            if (!$this->repository->exists($userId)) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            // Check email uniqueness if email is being updated (tenant-aware)
            if (isset($userData['email']) && $this->repository->isEmailTaken($userData['email'], $userId, $tenantId)) {
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
    public function findUserByEmail(string $email, ?string $tenantId = null): array
    {
        try {
            $user = $this->repository->findByEmail($email, $tenantId);

            if (!$user) {
                throw new UserNotFoundException("User with email {$email} not found");
            }

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (UserNotFoundException $e) {
            $this->logger->warning('User not found by email', [
                'email' => $email,
                'tenant_id' => $tenantId
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find user by email', [
                'email' => $email,
                'tenant_id' => $tenantId,
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

    /**
     * Verifica disponibilidade de email
     */
    public function verifyEmailAvailability(string $email): array
    {
        try {
            $this->logger->info('Verifying email availability', ['email' => $email]);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new UserValidationException('Invalid email format');
            }

            // Check if email is already taken (global verification, cross-tenant)
            $isAvailable = !$this->repository->isEmailTaken($email);

            $result = [
                'success' => true,
                'email' => $email,
                'available' => $isAvailable,
                'checked_at' => date('c')
            ];

            if (!$isAvailable) {
                $result['message'] = 'Email is already in use';
                $this->logger->info('Email not available', ['email' => $email]);
            } else {
                $result['message'] = 'Email is available';
                $this->logger->info('Email is available', ['email' => $email]);
            }

            return $result;

        } catch (UserValidationException $e) {
            $this->logger->warning('Email validation failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to verify email availability', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verifica múltiplos emails de uma só vez
     */
    public function verifyBulkEmailAvailability(array $emails): array
    {
        try {
            $this->logger->info('Verifying bulk email availability', ['count' => count($emails)]);

            $results = [];
            $validEmails = [];

            // Validate all emails first
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $results[$email] = [
                        'available' => false,
                        'error' => 'Invalid email format'
                    ];
                } else {
                    $validEmails[] = $email;
                }
            }

            // Check availability for valid emails (global verification, cross-tenant)
            foreach ($validEmails as $email) {
                $isAvailable = !$this->repository->isEmailTaken($email);
                $results[$email] = [
                    'available' => $isAvailable,
                    'message' => $isAvailable ? 'Email is available' : 'Email is already in use'
                ];
            }

            return [
                'success' => true,
                'results' => $results,
                'total_checked' => count($emails),
                'checked_at' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to verify bulk email availability', [
                'emails_count' => count($emails),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Gera sugestões de email disponíveis baseado no email desejado
     */
    public function suggestAvailableEmails(string $desiredEmail, int $maxSuggestions = 5): array
    {
        try {
            $this->logger->info('Generating email suggestions', [
                'desired_email' => $desiredEmail,
                'max_suggestions' => $maxSuggestions
            ]);

            // Validate email format
            if (!filter_var($desiredEmail, FILTER_VALIDATE_EMAIL)) {
                throw new UserValidationException('Invalid email format');
            }

            // Check if desired email is available (global verification, cross-tenant)
            $isAvailable = !$this->repository->isEmailTaken($desiredEmail);

            $suggestions = [];

            if ($isAvailable) {
                return [
                    'success' => true,
                    'desired_email' => $desiredEmail,
                    'available' => true,
                    'suggestions' => [],
                    'message' => 'Desired email is available'
                ];
            }

            // Generate suggestions
            $emailParts = explode('@', $desiredEmail);
            $username = $emailParts[0];
            $domain = $emailParts[1];

            $suggestionPatterns = [
                $username . '.' . date('Y'),
                $username . date('y'),
                $username . rand(10, 99),
                $username . '.' . rand(100, 999),
                $username . '_' . date('m') . date('d')
            ];

            foreach ($suggestionPatterns as $pattern) {
                if (count($suggestions) >= $maxSuggestions) {
                    break;
                }

                $suggestedEmail = $pattern . '@' . $domain;
                // Global verification for suggestions (cross-tenant)
                if (!$this->repository->isEmailTaken($suggestedEmail)) {
                    $suggestions[] = $suggestedEmail;
                }
            }

            return [
                'success' => true,
                'desired_email' => $desiredEmail,
                'available' => false,
                'suggestions' => $suggestions,
                'message' => 'Desired email is not available, here are some suggestions'
            ];

        } catch (UserValidationException $e) {
            $this->logger->warning('Email suggestion validation failed', [
                'desired_email' => $desiredEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate email suggestions', [
                'desired_email' => $desiredEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Tries multiple approaches to find an existing user when search API fails
     */
    private function findExistingUser(string $email, ?string $tenantId = null): ?array
    {
        $this->logger->info('Starting findExistingUser with multiple approaches', [
            'email' => $email,
            'tenant_id' => $tenantId
        ]);

        // Approach 1: Try findByEmail again (maybe cache issue)
        try {
            $this->logger->info('Approach 1: Trying findByEmail with tenant context');
            $user = $this->repository->findByEmail($email, $tenantId);
            if ($user) {
                $this->logger->info('Approach 1 SUCCESS: Found user via findByEmail', ['user_id' => $user['id'] ?? 'unknown']);
                return $user;
            }
            $this->logger->info('Approach 1: findByEmail returned null');
        } catch (\Exception $e) {
            $this->logger->info('Approach 1 FAILED: findByEmail threw exception', ['error' => $e->getMessage()]);
        }

        // Approach 2: Try searching all users in the tenant and filter by email
        try {
            $this->logger->info('Approach 2: Trying to search all tenant users and filter');
            $allUsers = $this->repository->findBy(['tenant_id' => $tenantId]);
            $this->logger->info('Approach 2: Got users result', [
                'result_type' => gettype($allUsers),
                'has_data_key' => isset($allUsers['data']),
                'user_count' => isset($allUsers['data']) ? count($allUsers['data']) : (is_array($allUsers) ? count($allUsers) : 'not_array')
            ]);

            if (isset($allUsers['data']) && is_array($allUsers['data'])) {
                foreach ($allUsers['data'] as $user) {
                    if (isset($user['email']) && $user['email'] === $email) {
                        $this->logger->info('Approach 2 SUCCESS: Found user in tenant data', ['user_id' => $user['id'] ?? 'unknown']);
                        return $user;
                    }
                }
            } elseif (is_array($allUsers)) {
                foreach ($allUsers as $user) {
                    if (isset($user['email']) && $user['email'] === $email) {
                        $this->logger->info('Approach 2 SUCCESS: Found user in tenant array', ['user_id' => $user['id'] ?? 'unknown']);
                        return $user;
                    }
                }
            }
            $this->logger->info('Approach 2: No matching email found in tenant users');
        } catch (\Exception $e) {
            $this->logger->info('Approach 2 FAILED: findBy tenant search threw exception', ['error' => $e->getMessage()]);
        }

        // Approach 3: Try without tenant filtering (global search)
        try {
            $this->logger->info('Approach 3: Trying global email search without tenant filter');
            $user = $this->repository->findByEmail($email, null);
            if ($user) {
                $userTenantId = $user['tenantId'] ?? $user['tenant_id'] ?? null;
                $this->logger->info('Approach 3: Found user globally', [
                    'user_id' => $user['id'] ?? 'unknown',
                    'user_tenant_id' => $userTenantId,
                    'target_tenant_id' => $tenantId
                ]);

                // Verify if user belongs to the correct tenant
                if (!$tenantId || $userTenantId === $tenantId) {
                    $this->logger->info('Approach 3 SUCCESS: User tenant matches target tenant');
                    return $user;
                } else {
                    $this->logger->info('Approach 3: User found but tenant mismatch');
                }
            } else {
                $this->logger->info('Approach 3: Global search returned null');
            }
        } catch (\Exception $e) {
            $this->logger->info('Approach 3 FAILED: Global email search threw exception', ['error' => $e->getMessage()]);
        }

        $this->logger->warning('All approaches failed to find existing user', [
            'email' => $email,
            'tenant_id' => $tenantId
        ]);
        return null;
    }
}
