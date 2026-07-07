<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(
        protected Request $request
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        AuditLog::create([
            'user_id'    => $user->id,
            'event'      => 'login',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'url'        => $this->request->fullUrl(),
            'method'     => $this->request->method(),
        ]);
    }
}
