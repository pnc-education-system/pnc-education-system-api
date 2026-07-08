<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\Permission;
use App\Observers\RoleObserver;
use App\Observers\PermissionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
    }
}
