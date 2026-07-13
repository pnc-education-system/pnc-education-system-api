<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    use ApiResponse, AuditableLogger;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $role = Role::create([
            'name' => $request->name,
            'slug' => $request->slug ?? \Illuminate\Support\Str::slug($request->name),
            'description' => $request->description,
        ]);

        if ($request->exists('permissions')) {
            $permissionIds = Permission::whereIn('slug', $request->permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $this->logAudit($role, 'role_created', $request, [], $role->toArray());

        return response()->json(
            [
                'status' => 'success',
                'message' => 'Role created successfully',
                'data' => $role->load('permissions'),
            ],
            201
        );
    }

    public function index(Request $request)
    {
        return response()->json(
            [
                'status' => 'success',
                'message' => 'Roles retrieved successfully',
                'data' => Role::with('permissions')->withCount('users')->get(),
            ],
            200
        );
    }

    public function show($id)
    {
        $role = Role::with('permissions')->withCount('users')->find($id);
        if (!$role) {
            return $this->error('Role not found', 404);
        }

        return response()->json(
            [
                'status' => 'success',
                'message' => 'Role retrieved successfully',
                'data' => $role,
            ],
            200
        );
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return $this->error('Role not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $role->toArray();

        foreach (['name', 'slug', 'description'] as $field) {
            if ($request->has($field)) {
                $role->$field = $request->$field;
            }
        }

        $role->save();

        if ($request->exists('permissions')) {
            $permissionIds = Permission::whereIn('slug', $request->permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $this->logAudit($role, 'role_updated', $request, $oldValues, $role->toArray());

        return response()->json(
            [
                'status' => 'success',
                'message' => 'Role updated successfully',
                'data' => $role->load('permissions'),
            ],
            200
        );
    }

    public function permissions()
    {
        return response()->json(
            [
                'status' => 'success',
                'message' => 'Permissions retrieved successfully',
                'data' => Permission::all(),
            ],
            200
        );
    }

    public function destroy(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->error('Role not found', 404);
        }

        $roleData = $role->toArray();
        $role->delete();
        $this->logAudit($role, 'role_deleted', $request, $roleData, []);

        return response()->json(
            [
                'status' => 'success',
                'message' => 'Role deleted successfully',
            ],
            200
        );
    }
}
