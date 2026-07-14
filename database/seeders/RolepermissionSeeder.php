<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class RolePermissionSeeder extends Seeder {
    public function run(): void {
        $adminRole = DB::table('roles')->where('slug', 'administrator')->value('id');
        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        foreach ($allPermissions as $permId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $adminRole,
                'permission_id' => $permId,
            ]);
        }
    }
}
