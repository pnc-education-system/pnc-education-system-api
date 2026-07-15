<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add enrollment.manage permission if it doesn't exist
        $permissionId = DB::table('permissions')
            ->where('slug', 'enrollment.manage')
            ->value('id');

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name'       => 'Manage Enrollment',
                'slug'       => 'enrollment.manage',
                'module'     => 'enrollment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign to administrator role
        $adminRole = DB::table('roles')->where('slug', 'administrator')->first();
        if ($adminRole) {
            $exists = DB::table('role_permission')
                ->where('role_id', $adminRole->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id'       => $adminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        // Assign to education_staff role too
        $staffRole = DB::table('roles')->where('slug', 'education_staff')->first();
        if ($staffRole) {
            $exists = DB::table('role_permission')
                ->where('role_id', $staffRole->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id'       => $staffRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'enrollment.manage')
            ->value('id');

        if ($permissionId) {
            DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
