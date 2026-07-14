<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole   = DB::table('roles')->where('slug', 'administrator')->value('id');
        $staffRole   = DB::table('roles')->where('slug', 'education_staff')->value('id');
        $managerRole = DB::table('roles')->where('slug', 'management')->value('id');

        $allPermissions = DB::table('permissions')->pluck('id')->toArray();

        // Admin gets all permissions
        foreach ($allPermissions as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminRole, 'permission_id' => $permId],
                ['role_id' => $adminRole, 'permission_id' => $permId]
            );
        }

        // Staff gets students, enrollment, cards, records, evaluation, reports
        $staffSlugs = ['students.view','students.edit','students.import','enrollment.manage','cards.generate',
                       'records.view','records.manage','evaluation.view','evaluation.manage','evaluation.submit','reports.view'];
        $staffPerms = DB::table('permissions')->whereIn('slug', $staffSlugs)->pluck('id')->toArray();
        foreach ($staffPerms as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $staffRole, 'permission_id' => $permId],
                ['role_id' => $staffRole, 'permission_id' => $permId]
            );
        }

        // Management gets view-only
        $viewSlugs = ['students.view', 'reports.view', 'evaluation.view', 'audit.view'];
        $viewPerms = DB::table('permissions')->whereIn('slug', $viewSlugs)->pluck('id')->toArray();
        foreach ($viewPerms as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $managerRole, 'permission_id' => $permId],
                ['role_id' => $managerRole, 'permission_id' => $permId]
            );
        }
    }
}
