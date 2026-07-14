<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ApiResponse, AuditableLogger;

    public function index(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Users retrieved successfully',
            'data' => User::with('role')->paginate(15),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'is_active' => $request->is_active ?? true,
        ];

        if ($request->has('phone')) {
            $data['phone'] = $request->phone;
        }

        $user = User::create($data);

        $this->logAudit($user, 'user_created', $request, [], $user->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user->load('role'),
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return $this->error('User not found', 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User retrieved successfully',
            'data' => $user,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $oldValues = $user->toArray();

        foreach (['name', 'email', 'role_id', 'phone', 'is_active'] as $field) {
            if ($request->has($field)) {
                $user->$field = $request->$field;
            }
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();
        $this->logAudit($user, 'user_updated', $request, $oldValues, $user->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user->load('role'),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        if ((int) $id === (int) auth()->id()) {
            return $this->error('You cannot delete your own account', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found', 404);
        }

        $userData = $user->toArray();
        $user->delete();
        $this->logAudit($user, 'user_deleted', $request, $userData, []);

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully',
        ], 200);
    }

    public function toggle($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found', 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User status toggled',
            'data' => [
                'is_active' => $user->is_active,
            ],
        ], 200);
    }
}
