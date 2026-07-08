<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    // Login/logout audit logging is handled in AuthController to avoid duplicate audit records.
    protected $listen = [
        // Login::class => [...],
        // Logout::class => [...],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}

