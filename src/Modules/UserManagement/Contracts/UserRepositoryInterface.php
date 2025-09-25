<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface específica para User Repository
 *
 * Estende a RepositoryInterface base adicionando métodos específicos
 * para operações de usuário no contexto do User Management.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de usuário
 * - I: Interface Segregation - Interface específica para usuários
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca usuário por email
     *
     * @param string $email Email do usuário
     * @return array|null Dados do usuário ou null se não encontrado
     */
    public function findByEmail(string $email): ?array;

    /**
     * Busca usuários por tenant
     *
     * @param string $tenantId ID do tenant
     * @param array $filters Filtros adicionais
     * @return array Lista de usuários do tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Atualiza perfil do usuário
     *
     * @param string $userId ID do usuário
     * @param array $profileData Dados do perfil a atualizar
     * @return array Dados atualizados do perfil
     */
    public function updateProfile(string $userId, array $profileData): array;

    /**
     * Altera senha do usuário
     *
     * @param string $userId ID do usuário
     * @param string $newPassword Nova senha
     * @return bool True se alterada com sucesso
     */
    public function changePassword(string $userId, string $newPassword): bool;

    /**
     * Ativa usuário
     *
     * @param string $userId ID do usuário
     * @return bool True se ativado com sucesso
     */
    public function activateUser(string $userId): bool;

    /**
     * Desativa usuário
     *
     * @param string $userId ID do usuário
     * @return bool True se desativado com sucesso
     */
    public function deactivateUser(string $userId): bool;

    /**
     * Obtém roles do usuário
     *
     * @param string $userId ID do usuário
     * @return array Lista de roles e permissões
     */
    public function getUserRoles(string $userId): array;

    /**
     * Atribui role ao usuário
     *
     * @param string $userId ID do usuário
     * @param string $role Role a atribuir
     * @return bool True se atribuída com sucesso
     */
    public function assignRole(string $userId, string $role): bool;

    /**
     * Remove role do usuário
     *
     * @param string $userId ID do usuário
     * @param string $role Role a remover
     * @return bool True se removida com sucesso
     */
    public function removeRole(string $userId, string $role): bool;

    /**
     * Busca usuários ativos por tenant
     *
     * @param string $tenantId ID do tenant
     * @return array Lista de usuários ativos
     */
    public function findActiveByTenant(string $tenantId): array;

    /**
     * Verifica se email já está em uso
     *
     * @param string $email Email a verificar
     * @param string|null $excludeUserId ID do usuário a excluir da verificação
     * @param string|null $tenantId ID do tenant para filtrar a verificação
     * @return bool True se email já está em uso
     */
    public function isEmailTaken(string $email, ?string $excludeUserId = null, ?string $tenantId = null): bool;

    /**
     * Busca usuários por role específica
     *
     * @param string $role Role a buscar
     * @param string|null $tenantId ID do tenant (opcional)
     * @return array Lista de usuários com a role
     */
    public function findByRole(string $role, ?string $tenantId = null): array;

    /**
     * Obtém estatísticas de usuários
     *
     * @param string|null $tenantId ID do tenant (opcional)
     * @return array Estatísticas de usuários
     */
    public function getUserStats(?string $tenantId = null): array;

    /**
     * Verifica se a senha está correta
     *
     * @param string $email Email do usuário
     * @param string $password Senha a verificar
     * @return bool True se a senha está correta
     */
    public function verifyPassword(string $email, string $password): bool;

    /**
     * Cria um novo usuário com headers customizados
     *
     * @param array $data Dados do usuário a criar
     * @param array $headers Headers customizados para a requisição
     * @return array Dados do usuário criado
     */
    public function createWithHeaders(array $data, array $headers = []): array;
}
