<?php 

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogout
{
    public function __construct(
        protected Request $request
    ) {}

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
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'url'        => $this->request->fullUrl(),
                'method'     => $this->request->method(),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
        }
    }
}