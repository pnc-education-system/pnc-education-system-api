<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionObserver
{
    public function __construct(
        protected Request $request
    ) {}

    public function created(Permission $permission): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'permission_created',
            'auditable_type' => Permission::class,
            'auditable_id'   => $permission->id,
            'new_values'     => $permission->toArray(),
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),
        ]);
    }

    public function updated(Permission $permission): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'permission_updated',
            'auditable_type' => Permission::class,
            'auditable_id'   => $permission->id,
            'old_values'     => $permission->getOriginal(),
            'new_values'     => $permission->getChanges(),
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),
        ]);
    }

    public function deleted(Permission $permission): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'permission_deleted',
            'auditable_type' => Permission::class,
            'auditable_id'   => $permission->id,
            'old_values'     => $permission->toArray(),
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),
        ]);
    }
}