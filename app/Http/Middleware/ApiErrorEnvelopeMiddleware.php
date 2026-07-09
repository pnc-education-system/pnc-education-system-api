<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiErrorEnvelopeMiddleware
{
    /**
     * Error HTTP status codes that should be wrapped in a standard envelope.
     */
    private const ERROR_STATUSES = [400, 401, 403, 404, 422, 500];

    /**
     * Wrap error responses in a consistent JSON envelope.
     *
     * Success responses pass through untouched.
     * Error responses are normalized to:
     *   { "error": { "code": 422, "message": "...", "errors": { ... } } }
     *
     * The optional "errors" object preserves Laravel's field-level validation
     * errors so the frontend can display per-field messages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only wrap JSON responses
        $contentType = $response->headers->get('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'application/json')) {
            return $response;
        }

        $payload = json_decode((string) $response->getContent(), true);

        if (! is_array($payload)) {
            return $response;
        }

        // Only wrap known error status codes
        if (! in_array($response->getStatusCode(), self::ERROR_STATUSES, true)) {
            return $response;
        }

        // Skip if the response is already wrapped (e.g., from another middleware pass)
        if (isset($payload['error']['code'], $payload['error']['message'])) {
            return $response;
        }

        // Extract the error message from the payload
        $message = $this->extractMessage($payload, $response->getStatusCode());

        // Build the standard error envelope
        $error = [
            'code'    => $response->getStatusCode(),
            'message' => $message,
        ];

        // Preserve field-level validation errors for 422 responses
        if (! empty($payload['errors'])) {
            $error['errors'] = $payload['errors'];
        }

        $response->setContent(json_encode(
            ['error' => $error],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Extract a human-readable message from the response payload.
     *
     * Checks common payload shapes:
     *   - { "message": "..." }              — Laravel default
     *   - { "status": "error", "message": … }  — ApiResponse trait
     *   - otherwise falls back to HTTP status text
     */
    private function extractMessage(array $payload, int $statusCode): string
    {
        // Laravel ValidationException / default exception format
        if (isset($payload['message']) && is_string($payload['message'])) {
            return $payload['message'];
        }

        // ApiResponse trait format: { status: 'error', message: '...' }
        if (isset($payload['status'], $payload['message']) && $payload['status'] === 'error') {
            return $payload['message'];
        }

        // Fallback to HTTP status text
        return Response::$statusTexts[$statusCode] ?? 'Request failed';
    }
}
