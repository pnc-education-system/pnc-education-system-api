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

        $role = $user->role ? $user->role->slug : null;
        $permissions = $user->role ? $user->role->permissions->pluck('slug')->toArray() : [];

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'role' => $role,
            'permissions' => $permissions,
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            $token = JWTAuth::getToken();
            
            if (!$token) {
                return response()->json([
                    'message' => 'No token provided',
                ], 401);
            }

            JWTAuth::invalidate($token);
            
            return response()->json([
                'message' => 'Successfully logged out',
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'Invalid token',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token has expired',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $token = JWTAuth::getToken();
            
            if (!$token) {
                return response()->json([
                    'message' => 'No token provided',
                ], 401);
            }

            $newToken = JWTAuth::refresh($token);
            
            return response()->json([
                'access_token' => $newToken,
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'Invalid token',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token has expired',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            $role = $user->role ? $user->role->slug : null;
            $permissions = $user->role ? $user->role->permissions->pluck('slug')->toArray() : [];

            return response()->json([
                'user' => $user,
                'role' => $role,
                'permissions' => $permissions,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get user data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateRefreshToken(User $user): string
    {
        return hash('sha256', $user->id . now()->timestamp . random_bytes(32));
    }
}
