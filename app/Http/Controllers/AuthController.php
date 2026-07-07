<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account is inactive',
            ], 401);
        }

        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);

        $user->update(['last_login_at' => now()]);

        $role = $user->role ? $user->role->name : null;
        $permissions = $user->role ? $user->role->permissions->pluck('name')->toArray() : [];

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'role' => $role,
            'permissions' => $permissions,
        ], 200);
    }

    private function generateRefreshToken(User $user): string
    {
        return hash('sha256', $user->id . now()->timestamp . random_bytes(32));
    }
}
