<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiErrorEnvelopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || !str_contains($contentType, 'application/json')) {
            return $response;
        }

        $payload = json_decode((string) $response->getContent(), true);
        if (!is_array($payload)) {
            return $response;
        }

        $statusCode = $response->getStatusCode();
        $isErrorStatus = in_array($statusCode, [400, 401, 403, 404, 422, 500], true);

        if ($isErrorStatus) {
            $message = null;

            // If response already has 'success' key, don't wrap it
            if (isset($payload['success'])) {
                return $response;
            }

            if (isset($payload['error']['message'])) {
                return $response;
            }

            if (isset($payload['message']) && is_string($payload['message'])) {
                $message = $payload['message'];
            } elseif (isset($payload['status']) && $payload['status'] === 'error' && isset($payload['message'])) {
                $message = $payload['message'];
            }

            if (!is_string($message) || $message === '') {
                $message = Response::$statusTexts[$statusCode] ?? 'Request failed';
            }

            $response->setContent(json_encode([
                'error' => [
                    'code' => $statusCode,
                    'message' => $message,
                ],
            ], JSON_UNESCAPED_SLASHES));

            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}

