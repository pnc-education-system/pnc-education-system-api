<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Roles retrieved successfully',
            'data' => Role::with('permissions')->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        $role = Role::create($request->only(['name', 'slug', 'description']));

        if ($request->has('permissions')) {
            $permissionIds = Permission::whereIn('slug', $request->permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role->load('permissions'),
        ], 201);
    }

    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->error('Role not found', 404);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully',
        ], 200);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return $this->error('Role not found', 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role retrieved successfully',
            'data' => $role,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->error('Role not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'display_name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Validation failed',
                422,
                $validator->errors()->toArray()
            );
        }

        $oldValues = $role->toArray();

        foreach (
            ['name', 'display_name', 'slug', 'description', 'is_active']
            as $field
        ) {
            if ($request->has($field)) {
                $role->$field = $request->$field;
            }
        }

        $role->save();

        $this->logAudit(
            $role,
            'role_updated',
            $request,
            $oldValues,
            $role->toArray()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions'),
        ], 200);
    }

    public function permissions()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Permissions retrieved successfully',
            'data' => Permission::all(),
        ], 200);
    }

    private function logAudit(
        Role $role,
        string $event,
        Request $request,
        array $oldValues = [],
        array $newValues = []
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => $oldValues,
            'new_values' => $newValues ?: $role->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);
    }

}
