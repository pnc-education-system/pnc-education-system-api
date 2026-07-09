<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->error('Authentication required', 401);
            }

            $user = JWTAuth::user();

            if (!$user) {
                return $this->error('User not found', 401);
            }

            if (!$user->is_active) {
                return $this->error('Account is inactive', 401);
            }

            return $next($request);
        } catch (TokenExpiredException $e) {
            return $this->error('Token has expired', 401);
        } catch (TokenInvalidException $e) {
            return $this->error('Invalid token', 401);
        } catch (\Exception $e) {
            return $this->error('Authentication failed', 401);
        }
    }

    private function error(string $message, int $code, array $errors = [])
    {
        $response = ['status' => 'error', 'message' => $message];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}

