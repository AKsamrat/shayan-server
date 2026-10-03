<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermissionAction;
use App\Models\PermissionModule;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function getPermissions(): JsonResponse
    {
        $modules = PermissionModule::active()
            ->ordered()
            ->with(['actions' => function($query) {
                $query->active()->ordered();
            }])
            ->get();

        $permissions = [];
        foreach ($modules as $module) {
            $actions = [];
            foreach ($module->actions as $action) {
                $actions[] = $action->name;
            }
            $permissions[$module->name] = $actions;
        }

        return $this->success($permissions, 'Permissions retrieved');
    }

    // Helper method to get all valid permissions for validation
    private function getAllValidPermissions(): array
    {
        $modules = PermissionModule::active()
            ->with(['actions' => function($query) {
                $query->active();
            }])
            ->get();

        $permissions = [];
        foreach ($modules as $module) {
            foreach ($module->actions as $action) {
                $permissions[] = $module->name . '.' . $action->name;
            }
        }
        return $permissions;
    }

    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        return $this->success($roles, 'Roles retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        // Validate all permissions are valid and only keep valid ones
        if (isset($validated['permissions'])) {
            $allValid = $this->getAllValidPermissions();
            $validated['permissions'] = array_values(array_intersect($validated['permissions'], $allValid));
        }

        $role = Role::create($validated);

        return $this->success($role, 'Role created successfully', 201);
    }

    public function show(string $id): JsonResponse
    {
        $role = Role::withCount('users')->findOrFail($id);
        return $this->success($role, 'Role retrieved');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system && $role->slug === 'super_admin') {
            return $this->error('Cannot modify the Super Admin role.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'permissions' => 'sometimes|required|array',
            'permissions.*' => 'string',
        ]);

        if (isset($validated['permissions'])) {
            $allValid = $this->getAllValidPermissions();
            $validated['permissions'] = array_values(array_intersect($validated['permissions'], $allValid));
        }

        $role->update($validated);

        return $this->success($role->loadCount('users'), 'Role updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return $this->error('Cannot delete system roles.', 403);
        }

        // Unassign users from this role before deleting
        User::where('role_id', $role->id)->update(['role_id' => null, 'role' => 'customer']);

        $role->delete();

        return $this->success(null, 'Role deleted successfully');
    }

    public function assignRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        $user->update([
            'role_id' => $role->id,
            'role' => $role->slug,
        ]);

        return $this->success($user->load('role'), 'Role assigned successfully');
    }

    // Get all permission modules with their actions
    public function getPermissionModules(): JsonResponse
    {
        $modules = PermissionModule::active()
            ->ordered()
            ->with(['actions' => function($query) {
                $query->active()->ordered();
            }])
            ->get();

        return $this->success($modules, 'Permission modules retrieved');
    }

    // Create a new permission module
    public function createPermissionModule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permission_modules,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $module = PermissionModule::create($validated);

        return $this->success($module, 'Permission module created', 201);
    }

    // Update a permission module
    public function updatePermissionModule(Request $request, int $id): JsonResponse
    {
        $module = PermissionModule::findOrFail($id);

        if ($module->is_system) {
            return $this->error('Cannot modify system permission modules.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:permission_modules,name,' . $id,
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $module->update($validated);

        return $this->success($module, 'Permission module updated');
    }

    // Delete a permission module
    public function deletePermissionModule(int $id): JsonResponse
    {
        $module = PermissionModule::findOrFail($id);

        if ($module->is_system) {
            return $this->error('Cannot delete system permission modules.', 403);
        }

        // Check if module is used in any role
        $rolesUsingModule = Role::whereJsonContains('permissions', $module->name)->count();
        if ($rolesUsingModule > 0) {
            return $this->error('Cannot delete module that is used in existing roles. Remove permissions from roles first.', 400);
        }

        $module->delete();

        return $this->success(null, 'Permission module deleted');
    }

    // Create a permission action
    public function createPermissionAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:permission_modules,id',
            'name' => 'required|string|max:100',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Ensure action name is unique across all modules
        $existing = PermissionAction::where('name', $validated['name'])->exists();
        if ($existing) {
            return $this->error('An action with this name already exists.', 422);
        }

        $action = PermissionAction::create($validated);

        return $this->success($action, 'Permission action created', 201);
    }

    // Update a permission action
    public function updatePermissionAction(Request $request, int $id): JsonResponse
    {
        $action = PermissionAction::findOrFail($id);

        $validated = $request->validate([
            'module_id' => 'sometimes|exists:permission_modules,id',
            'name' => 'sometimes|required|string|max:100|unique:permission_actions,name,' . $id,
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $action->update($validated);

        return $this->success($action, 'Permission action updated');
    }

    // Delete a permission action
    public function deletePermissionAction(int $id): JsonResponse
    {
        $action = PermissionAction::findOrFail($id);

        // Check if action is used in any role
        $actionName = $action->name;
        $rolesUsingAction = Role::whereJsonContains('permissions', $actionName)->count();
        if ($rolesUsingAction > 0) {
            return $this->error('Cannot delete action that is used in existing roles. Remove permissions from roles first.', 400);
        }

        $action->delete();

        return $this->success(null, 'Permission action deleted');
    }

    // Reorder permission modules
    public function reorderPermissionModules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:permission_modules,id',
            'modules.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['modules'] as $item) {
            PermissionModule::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return $this->success(null, 'Permission modules reordered');
    }

    // Reorder permission actions within a module
    public function reorderPermissionActions(Request $request, int $moduleId): JsonResponse
    {
        $validated = $request->validate([
            'actions' => 'required|array',
            'actions.*.id' => 'required|exists:permission_actions,id',
            'actions.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['actions'] as $item) {
            PermissionAction::where('id', $item['id'])->where('module_id', $moduleId)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return $this->success(null, 'Permission actions reordered');
    }

    // Get all permission actions
    public function getPermissionActions(): JsonResponse
    {
        $actions = PermissionAction::ordered()
            ->with(['module' => function($query) {
                $query->select('id', 'name', 'display_name');
            }])
            ->get();

        return $this->success($actions, 'Permission actions retrieved');
    }

    // Get permission actions for a specific module
    public function getPermissionModuleActions(int $moduleId): JsonResponse
    {
        $actions = PermissionAction::where('module_id', $moduleId)
            ->ordered()
            ->get();

        return $this->success($actions, 'Permission actions retrieved');
    }
}
