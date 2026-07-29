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
            // Already in envelope format — pass through
            if (isset($payload['error']['message'])) {
                return $response;
            }

            $message = null;
            $errors = [];

            if (isset($payload['message']) && is_string($payload['message'])) {
                $message = $payload['message'];
            } elseif (isset($payload['status']) && $payload['status'] === 'error' && isset($payload['message'])) {
                $message = $payload['message'];
            }

            // Preserve field-level validation errors (422 responses)
            if (isset($payload['errors']) && is_array($payload['errors'])) {
                $errors = $payload['errors'];
            }

            if (!is_string($message) || $message === '') {
                $message = Response::$statusTexts[$statusCode] ?? 'Request failed';
            }

            $envelope = [
                'error' => [
                    'code' => $statusCode,
                    'message' => $message,
                ],
            ];

            // Include field-level errors when present (gives frontend specific feedback like "File too large")
            if (!empty($errors)) {
                $envelope['error']['errors'] = $errors;
            }

            $response->setContent(json_encode($envelope, JSON_UNESCAPED_SLASHES));

            if (!$response->headers->has('Content-Type')) {
                $response->headers->set('Content-Type', 'application/json');
            }
        }

        return $response;
    }
}
