<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSuccessfullLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;
        if (!$user) {
            return;
        }

        try {
            AuditLog::create([
                'user_id'    => $user->id,
                'event'      => 'logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url'        => request()->fullUrl(),
                'method'     => request()->method(),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
        }
    }
}

