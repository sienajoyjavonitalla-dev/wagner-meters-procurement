<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserManagementException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 6.1.3 GET users for user management.
     */
    public function index(Request $request, UserManagementService $userManagement): JsonResponse
    {
        $actor = $request->user();

        return response()->json([
            'data' => $userManagement->listUsers(),
            'roles' => $userManagement->roles(),
            'can_assign_super_admin' => $userManagement->canAssignSuperAdmin($actor),
        ]);
    }

    /**
     * 6.1.3 POST create user.
     */
    public function store(
        Request $request,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_VIEWER])],
        ]);

        try {
            $user = $userManagement->createUser($validated, $actor, $auditLog);
        } catch (UserManagementException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $user->id,
            ],
        ], 201);
    }

    /**
     * 6.1.3 PATCH update user.
     */
    public function update(
        Request $request,
        User $user,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_VIEWER])],
        ]);

        try {
            $userManagement->updateUser($user, $validated, $actor, $auditLog);
        } catch (UserManagementException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    /**
     * 6.1.3 DELETE user.
     */
    public function destroy(
        Request $request,
        User $user,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        try {
            $userManagement->deleteUser($user, $actor, $auditLog);
        } catch (UserManagementException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 6.1.3 PATCH user role.
     */
    public function updateRole(
        Request $request,
        User $user,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_VIEWER])],
        ]);

        try {
            $userManagement->updateUserRole($user, $validated['role'], $actor, $auditLog);
        } catch (UserManagementException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $user->id,
                'role' => $user->role,
            ],
        ]);
    }
}
