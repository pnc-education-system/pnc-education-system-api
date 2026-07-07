<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles, 200);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->find($id);
        
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json($role, 200);
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'display_name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) {
            $role->name = $request->name;
        }
        if ($request->has('display_name')) {
            $role->display_name = $request->display_name;
        }
        if ($request->has('slug')) {
            $role->slug = $request->slug;
        }
        if ($request->has('description')) {
            $role->description = $request->description;
        }
        if ($request->has('is_active')) {
            $role->is_active = $request->is_active;
        }

        $role->save();

        return response()->json($role->load('permissions'), 200);
    }

    public function permissions()
    {
        $permissions = Permission::all();
        return response()->json($permissions, 200);
    }
}
