<?php 

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleObserver
{
    public function __construct(
        protected Request $request
    ) {}

    protected function getUserId(): ?int
    {
        return optional(JWTAuth::user())->id;
    }

    protected function log(string $event, Role $role, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => $this->getUserId(),
                'event'          => $event,
                'auditable_type' => Role::class,
                'auditable_id'   => $role->id,
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

    public function created(Role $role): void
    {
        $this->log('role_created', $role, null, $role->toArray());
    }

    public function updated(Role $role): void
    {
        $this->log('role_updated', $role, $role->getOriginal(), $role->getChanges());
    }

    public function deleted(Role $role): void
    {
        $this->log('role_deleted', $role, $role->toArray(), null);
    }
}