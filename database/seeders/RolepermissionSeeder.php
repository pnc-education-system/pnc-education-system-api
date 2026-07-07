<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole   = DB::table('roles')->where('slug', 'admin')->value('id');
        $teacherRole = DB::table('roles')->where('slug', 'teacher')->value('id');
        $studentRole = DB::table('roles')->where('slug', 'student')->value('id');

        $allPermissions     = DB::table('permissions')->pluck('id')->toArray();
        $teacherPermissions = DB::table('permissions')->whereIn('module', ['Users'])->where('slug', 'like', '%.view')->pluck('id')->toArray();
        $studentPermissions = DB::table('permissions')->where('slug', 'users.view')->pluck('id')->toArray();

        $records = [];

        // Admin gets all permissions
        foreach ($allPermissions as $permId) {
            $records[] = ['role_id' => $adminRole, 'permission_id' => $permId];
        }

        // Teacher gets view-only on users
        foreach ($teacherPermissions as $permId) {
            $records[] = ['role_id' => $teacherRole, 'permission_id' => $permId];
        }

        // Student gets view own user only
        foreach ($studentPermissions as $permId) {
            $records[] = ['role_id' => $studentRole, 'permission_id' => $permId];
        }

        foreach ($records as $record) {
            DB::table('role_permission')->updateOrInsert($record);
        }
    }
}
