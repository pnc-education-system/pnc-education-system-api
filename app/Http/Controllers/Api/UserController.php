<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['status' => 'success', 'message' => 'Users retrieved successfully', 'data' => User::with('role')->paginate(15)], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), ['name' => 'required|string|max:255', 'email' => 'required|string|email|max:255|unique:users', 'password' => 'required|string|min:8', 'role_id' => 'nullable|exists:roles,id', 'phone' => 'nullable|string|max:20', 'is_active' => 'boolean']);
        if ($validator->fails()) return $this->error('Validation failed', 422, $validator->errors());

        $user = User::create(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'role_id' => $request->role_id, 'phone' => $request->phone, 'is_active' => $request->is_active ?? true]);
        $this->logAudit($user, 'user_created', $request, $user->toArray());
        return response()->json(['status' => 'success', 'message' => 'User created successfully', 'data' => $user->load('role')], 201);
    }

    public function show($id)
    {
        $user = User::with('role')->find($id);
        if (!$user) return $this->error('User not found', 404);
        return response()->json(['status' => 'success', 'message' => 'User retrieved successfully', 'data' => $user], 200);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return $this->error('User not found', 404);

        $validator = Validator::make($request->all(), ['name' => 'sometimes|required|string|max:255', 'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id, 'password' => 'sometimes|required|string|min:8', 'role_id' => 'nullable|exists:roles,id', 'phone' => 'nullable|string|max:20', 'is_active' => 'boolean']);
        if ($validator->fails()) return $this->error('Validation failed', 422, $validator->errors());

        $oldValues = $user->toArray();
        foreach (['name', 'email', 'role_id', 'phone', 'is_active'] as $field) if ($request->has($field)) $user->$field = $request->$field;
        if ($request->has('password')) $user->password = Hash::make($request->password);
        $user->save();
        $this->logAudit($user, 'user_updated', $request, $oldValues, $user->toArray());
        return response()->json(['status' => 'success', 'message' => 'User updated successfully', 'data' => $user->load('role')], 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) return $this->error('User not found', 404);
        $userData = $user->toArray();
        $user->delete();
        $this->logAudit($user, 'user_deleted', $request(), $userData);
        return response()->json(['status' => 'success', 'message' => 'User deleted successfully'], 200);
    }

    public function toggle($id)
    {
        $user = User::find($id);
        if (!$user) return $this->error('User not found', 404);
        $user->is_active = !$user->is_active;
        $user->save();
        return response()->json(['status' => 'success', 'message' => 'User status toggled', 'data' => ['is_active' => $user->is_active]], 200);
    }

    private function logAudit($user, string $event, Request $request, array $oldValues = [], array $newValues = [])
    {
        AuditLog::create(['user_id' => auth()->id(), 'event' => $event, 'auditable_type' => User::class, 'auditable_id' => $user->id, 'old_values' => $oldValues, 'new_values' => $newValues ?: $user->toArray(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'url' => $request->url(), 'method' => $request->method()]);
    }
    private function error(string $message, int $code, array $errors = [])
    {
        return response()->json(['status' => 'error', 'message' => $message] + ($errors ? ['errors' => $errors] : []), $code);
    }
}
