<?php

namespace App\Services;

use App\Exceptions\UserManagementException;
use App\Models\User;

class UserManagementService
{
    public function roles(): array
    {
        return [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_VIEWER,
        ];
    }

    public function canAssignSuperAdmin(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->can('assign-super-admin');
    }

    public function listUsers(): array
    {
        return User::query()
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->email,
                'email' => $u->email,
                'role' => $u->role,
                'created_at' => $u->created_at ? $u->created_at->toIso8601String() : null,
            ])
            ->values()
            ->all();
    }

    public function createUser(array $validated, ?User $actor, AuditLogService $auditLog): User
    {
        $this->guardRoleAssignment($validated['role'], $actor);

        $user = User::create([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

        $auditLog->log('users.created', $actor ? $actor->id : null, 'user', $user->id, [
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return $user;
    }

    public function updateUser(User $user, array $validated, ?User $actor, AuditLogService $auditLog): void
    {
        $oldRole = (string) $user->role;
        if (array_key_exists('role', $validated)) {
            $this->guardRoleTransition($oldRole, $validated['role'], $actor);
        }

        $payload = collect($validated)->except(['password'])->all();
        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $auditLog->log('users.updated', $actor ? $actor->id : null, 'user', $user->id, [
            'fields' => array_keys($payload),
            'from_role' => $oldRole,
            'to_role' => $user->role,
        ]);
    }

    public function deleteUser(User $user, ?User $actor, AuditLogService $auditLog): void
    {
        if ($actor && (int) $actor->id === (int) $user->id) {
            throw new UserManagementException('You cannot delete your own account.', 422);
        }

        if ($user->role === User::ROLE_SUPER_ADMIN) {
            $this->ensureAtLeastOneSuperAdmin();
            if (! $this->canAssignSuperAdmin($actor)) {
                throw new UserManagementException('Only super admins can delete super admin users.', 403);
            }
        }

        $deletedUserId = $user->id;
        $deletedEmail = $user->email;
        $user->delete();

        $auditLog->log('users.deleted', $actor ? $actor->id : null, 'user', $deletedUserId, [
            'email' => $deletedEmail,
        ]);
    }

    public function updateUserRole(User $user, string $targetRole, ?User $actor, AuditLogService $auditLog): void
    {
        $oldRole = (string) $user->role;
        $this->guardRoleTransition($oldRole, $targetRole, $actor);

        $user->update(['role' => $targetRole]);
        $auditLog->log('users.role.updated', $actor ? $actor->id : null, 'user', $user->id, [
            'from' => $oldRole,
            'to' => $targetRole,
        ]);
    }

    private function guardRoleAssignment(string $targetRole, ?User $actor): void
    {
        if ($targetRole === User::ROLE_SUPER_ADMIN && ! $this->canAssignSuperAdmin($actor)) {
            throw new UserManagementException('Only super admins can assign super admin role.', 403);
        }
    }

    private function guardRoleTransition(string $oldRole, string $targetRole, ?User $actor): void
    {
        $this->guardRoleAssignment($targetRole, $actor);

        if ($oldRole === User::ROLE_SUPER_ADMIN && $targetRole !== User::ROLE_SUPER_ADMIN) {
            $this->ensureAtLeastOneSuperAdmin();
            if (! $this->canAssignSuperAdmin($actor)) {
                throw new UserManagementException('Only super admins can demote super admin role.', 403);
            }
        }
    }

    private function ensureAtLeastOneSuperAdmin(): void
    {
        $superAdmins = User::query()->where('role', User::ROLE_SUPER_ADMIN)->count();
        if ($superAdmins <= 1) {
            throw new UserManagementException('At least one super admin is required.', 422);
        }
    }
}
