<?php
use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (!$user) {
            return;
        }

        try {
            AuditLog::create([
                'user_id'    => $user->id,
                'event'      => 'login',
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

