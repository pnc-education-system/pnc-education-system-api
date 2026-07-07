<?php 

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleObserver
{
    public function __construct(
        protected Request $request
    ){}
    public function created(Role $role): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),            
            'event'          => 'role_created',          
            'auditable_type' => Role::class,             
            'auditable_id'   => $role->id,              
            'new_values'     => $role->toArray(),        
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),

        ]);
    }
    public function updated(Role $role): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'role_updated',
            'auditable_type' => Role::class,
            'auditable_id'   => $role->id,
            'old_values'     => $role->getOriginal(),   
            'new_values'     => $role->getChanges(),     
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),
        ]);
    }
    public function deleted(Role $role): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'role_deleted',
            'auditable_type' => Role::class,
            'auditable_id'   => $role->id,
            'old_values'     => $role->toArray(),        
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
            'url'            => $this->request->fullUrl(),
            'method'         => $this->request->method(),
        ]);
    }

}