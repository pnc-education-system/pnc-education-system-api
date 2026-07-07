<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email', 'password' => 'required|string']);
        if ($validator->fails()) return $this->error('Validation failed', 422, $validator->errors());

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) return $this->error('Invalid credentials', 401);
        if (!$user->is_active) return $this->error('Account is inactive', 401);

        $user->update(['last_login_at' => now()]);
        $this->logAudit($user, 'login', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Authenticated successfully',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->slug],
            'access_token' => JWTAuth::fromUser($user),
            'refresh_token' => $this->generateRefreshToken($user),
            'permissions' => $user->role?->permissions->pluck('slug')->toArray() ?? []
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            $user = JWTAuth::user();
            JWTAuth::invalidate(JWTAuth::getToken());
            if ($user) $this->logAudit($user, 'logout', $request);
            return response()->json(['status' => 'success', 'message' => 'Successfully logged out'], 200);
        } catch (\Exception $e) {
            return $this->error('Failed to logout', 500, ['error' => $e->getMessage()]);
        }
    }

    public function refresh(Request $request)
    {
        try {
            return response()->json(['status' => 'success', 'message' => 'Token refreshed successfully', 'access_token' => JWTAuth::refresh(JWTAuth::getToken())], 200);
        } catch (\Exception $e) {
            return $this->error('Failed to refresh token', 500, ['error' => $e->getMessage()]);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) return $this->error('User not found', 404);
            return response()->json(['status' => 'success', 'message' => 'User data retrieved successfully', 'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->slug], 'permissions' => $user->role?->permissions->pluck('slug')->toArray() ?? []], 200);
        } catch (\Exception $e) {
            return $this->error('Failed to get user data', 500, ['error' => $e->getMessage()]);
        }
    }

    public function requestReset(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) return $this->error('Validation failed', 422, $validator->errors());

        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['status' => 'success', 'message' => 'If the email exists, a reset token has been sent'], 200);

        $token = Str::random(60);
        $user->update(['reset_token' => $token, 'reset_token_expires_at' => now()->addHours(1)]);
        return response()->json(['status' => 'success', 'message' => 'If the email exists, a reset token has been sent', 'reset_token' => $token], 200);
    }

    public function confirmReset(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email', 'reset_token' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        if ($validator->fails()) return $this->error('Validation failed', 422, $validator->errors());

        $user = User::where('email', $request->email)->where('reset_token', $request->reset_token)->where('reset_token_expires_at', '>', now())->first();
        if (!$user) return $this->error('Invalid or expired reset token', 400);

        $user->update(['password' => Hash::make($request->password), 'reset_token' => null, 'reset_token_expires_at' => null]);
        $this->logAudit($user, 'password_reset', $request);
        return response()->json(['status' => 'success', 'message' => 'Password reset successfully'], 200);
    }

    private function generateRefreshToken(User $user): string
    {
        return hash('sha256', $user->id . now()->timestamp . random_bytes(32));
    }
    private function logAudit($user, string $event, Request $request)
    {
        AuditLog::create(['user_id' => $user->id, 'event' => $event, 'auditable_type' => User::class, 'auditable_id' => $user->id, 'new_values' => $event === 'login' ? ['last_login_at' => now()] : ['password_changed' => true], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'url' => $request->url(), 'method' => $request->method()]);
    }
    private function error(string $message, int $code, array $errors = [])
    {
        return response()->json(['status' => 'error', 'message' => $message] + ($errors ? ['errors' => $errors] : []), $code);
    }
}
