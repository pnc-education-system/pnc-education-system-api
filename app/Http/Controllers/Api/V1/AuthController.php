<?php
namespace App\Http\Controllers\Api\V1;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Http\Controllers\Controller;
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if ($validator->fails())
            return $this->error('Validation failed', 422, $validator->errors());
        $user = User::where(
            'email', $request->email
        )->first();
        if (!$user || !Hash::check($request->password, $user->password))
            return $this->error('Invalid credentials', 401);
        if (!$user->is_active)
            return $this->error('Account is inactive', 401);

        $user->update(['last_login_at' => now()]);

        $this->logAudit($user, 'login', $request);

        $accessToken = JWTAuth::fromUser($user);

        $refreshToken = $this->generateRefreshToken($user);
        return response()->json([
            'status' => 'success',
            'message' => 'Authenticated successfully',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->slug],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'permissions' => $user->role?->permissions->pluck('slug')->toArray() ?? []
        ], 200);
    }
    public function logout(Request $request)
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) return $this->error('No token provided', 401);
            $user = JWTAuth::user();
            JWTAuth::invalidate($token);
            if ($user) {
                $this->revokeRefreshTokens($user);
                $this->logAudit($user, 'logout', $request);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ], 200);
        } catch (TokenInvalidException $e) {
            return $this->error('Invalid token', 401);
        } catch (TokenExpiredException $e) {
            return $this->error('Token has expired', 401);
        } catch (\Exception $e) {
            return $this->error('Failed to logout', 500, ['error' => $e->getMessage()]);
        }
    }
    public function refresh(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'refresh_token' => 'required|string'
            ]);
            if ($validator->fails())
                return $this->error('Validation failed', 422, $validator->errors());
            $refreshToken = DB::table('refresh_tokens')->where('token', $request->refresh_token)->where('revoked_at', null)->where('expires_at', '>', now())->first();
            if (!$refreshToken)
                return $this->error('Invalid or expired refresh token', 401);
            $user = User::find($refreshToken->user_id);
            if (!$user || !$user->is_active)
                return $this->error('User not found or inactive', 401);
            $newAccessToken = JWTAuth::fromUser($user);

            $newRefreshToken = $this->generateRefreshToken($user);
            
            DB::table('refresh_tokens')->where('id', $refreshToken->id)->update(['revoked_at' => now()]);
            $this->logAudit($user, 'token_refresh', $request);
            return response()->json([
                'status' => 'success',
                'message' => 'Token refreshed successfully',
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'bearer',
                'expires_in' => (int) config('jwt.ttl') * 60
            ], 200);
        } catch (TokenInvalidException $e) {
            return $this->error('Invalid token', 401);
        } catch (TokenExpiredException $e) {
            return $this->error('Token has expired', 401);
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
        $token = hash('sha256', $user->id . now()->timestamp . random_bytes(32));
        DB::table('refresh_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes((int) config('jwt.refresh_ttl')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $token;
    }
    private function revokeRefreshTokens(User $user): void
    {
        DB::table('refresh_tokens')->where('user_id', $user->id)->where('revoked_at', null)->update(['revoked_at' => now()]);
    }
    private function logAudit($user, string $event, Request $request)
    {
        AuditLog::create([
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => $event === 'login' ? ['last_login_at' => now()] : ['password_changed' => true],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);
    }
    private function error(string $message, int $code, array $errors = [])
    {
        $response = ['status' => 'error', 'message' => $message];
        if (!empty($errors)) $response['errors'] = $errors;
        return response()->json($response, $code);
    }
}
