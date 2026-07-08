<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

trait AuditableLogger
{
    protected function logAudit($auditable, string $event, Request $request, array $oldValues = [], array $newValues = [])
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => !empty($newValues) ? $newValues : $auditable->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);
    }

    protected function logUserAudit(User $user, string $event, Request $request)
    {
        $newValues = match ($event) {
            'login' => ['last_login_at' => now()],
            'logout' => ['logged_out_at' => now()],
            'token_refresh' => ['refreshed_at' => now()],
            'password_reset' => ['password_changed' => true],
            default => [],
        };

        AuditLog::create([
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);
    }

}

