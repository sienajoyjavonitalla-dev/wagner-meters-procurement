<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        StoreUserRequest $request,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validated();

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
        UpdateUserRequest $request,
        User $user,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validated();

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
        UpdateUserRoleRequest $request,
        User $user,
        AuditLogService $auditLog,
        UserManagementService $userManagement
    ): JsonResponse {
        $actor = $request->user();
        $validated = $request->validated();

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
