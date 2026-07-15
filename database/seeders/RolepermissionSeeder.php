<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'admin')->value('id');
        $teacherRole = DB::table('roles')->where('slug', 'teacher')->value('id');
        $studentRole = DB::table('roles')->where('slug', 'student')->value('id');

        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        $teacherPermissions = DB::table('permissions')
            ->whereIn('module', ['Users'])
            ->where('slug', 'like', '%.view')
            ->pluck('id')
            ->toArray();
        $studentPermissions = DB::table('permissions')
            ->where('slug', 'users.view')
            ->pluck('id')
            ->toArray();

        $records = [];

        foreach ($allPermissions as $permId) {
            $records[] = ['role_id' => $adminRole, 'permission_id' => $permId];
        }
        foreach ($teacherPermissions as $permId) {
            $records[] = ['role_id' => $teacherRole, 'permission_id' => $permId];
        }
        foreach ($studentPermissions as $permId) {
            $records[] = ['role_id' => $studentRole, 'permission_id' => $permId];
        }

        // Idempotent & efficient: rely on (role_id, permission_id) unique constraint.
        // Using bulk upsert avoids per-row updateOrInsert calls.
        DB::table('role_permission')->upsert(
            $records,
            ['role_id', 'permission_id']
        );
    }
}

