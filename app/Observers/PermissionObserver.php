<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class PermissionObserver
{
    public function __construct(
        protected Request $request
    ) {}

    protected function getUserId(): ?int
    {
        return optional(JWTAuth::user())->id;
    }

    protected function log(string $event, Permission $permission, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => $this->getUserId(),
                'event'          => $event,
                'auditable_type' => Permission::class,
                'auditable_id'   => $permission->id,
                'old_values'     => $oldValues,
                'new_values'     => $newValues,
                'ip_address'     => $this->request->ip(),
                'user_agent'     => $this->request->userAgent(),
                'url'            => $this->request->fullUrl(),
                'method'         => $this->request->method(),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
        }
    }

    public function created(Permission $permission): void
    {
        $this->log('permission_created', $permission, null, $permission->toArray());
    }

    public function updated(Permission $permission): void
    {
        $this->log('permission_updated', $permission, $permission->getOriginal(), $permission->getChanges());
    }

    public function deleted(Permission $permission): void
    {
        $this->log('permission_deleted', $permission, $permission->toArray(), null);
    }
}