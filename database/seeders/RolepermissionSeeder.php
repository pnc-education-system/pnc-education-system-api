<?php

namespace Database\Seeders;


use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionDefs = [
            // Users
            ['name' => 'Manage Users',        'slug' => 'users.manage',        'module' => 'admin'],
            ['name' => 'Manage Roles',         'slug' => 'roles.manage',        'module' => 'admin'],
            ['name' => 'View Audit Logs',      'slug' => 'audit.view',          'module' => 'admin'],
            ['name' => 'Manage Settings',      'slug' => 'settings.manage',     'module' => 'admin'],
            // Students
            ['name' => 'View Students',        'slug' => 'students.view',       'module' => 'students'],
            ['name' => 'Edit Students',        'slug' => 'students.edit',       'module' => 'students'],
            ['name' => 'Import Students',      'slug' => 'students.import',     'module' => 'students'],
            // Enrollment
            ['name' => 'Manage Enrollment',    'slug' => 'enrollment.manage',   'module' => 'enrollment'],
            // Cards
            ['name' => 'Generate ID Cards',    'slug' => 'cards.generate',      'module' => 'cards'],
            // Records
            ['name' => 'View Records',         'slug' => 'records.view',        'module' => 'records'],
            ['name' => 'Manage Records',       'slug' => 'records.manage',      'module' => 'records'],
            // Evaluation
            ['name' => 'View Evaluations',     'slug' => 'evaluation.view',     'module' => 'evaluation'],
            ['name' => 'Manage Evaluations',   'slug' => 'evaluation.manage',   'module' => 'evaluation'],
            ['name' => 'Submit Evaluation',    'slug' => 'evaluation.submit',   'module' => 'evaluation'],
            // Reports
            ['name' => 'View Reports',         'slug' => 'reports.view',        'module' => 'reports'],
        ];

        foreach ($permissionDefs as $def) {
            Permission::firstOrCreate(['slug' => $def['slug']], $def);
        }

        $adminRole = Role::firstOrCreate(
            ['slug' => 'administrator'],
            ['name' => 'Administrator', 'description' => 'Full system access']
        );

        $staffRole = Role::firstOrCreate(
            ['slug' => 'education_staff'],
            ['name' => 'Education Staff', 'description' => 'Day-to-day operations']
        );

        $viewerRole = Role::firstOrCreate(
            ['slug' => 'management'],
            ['name' => 'Management', 'description' => 'Read-only reporting access']
        );

        $adminRole->permissions()->sync(Permission::pluck('id'));


        $staffPerms = Permission::whereIn('slug', [
            'students.view', 'students.edit', 'students.import',
            'enrollment.manage', 'cards.generate',
            'records.view', 'records.manage',
            'evaluation.view', 'evaluation.manage', 'evaluation.submit',
            'reports.view',
        ])->pluck('id');
        $staffRole->permissions()->sync($staffPerms);


        $viewerRole->permissions()->sync(
            Permission::whereIn('slug', ['students.view', 'reports.view', 'evaluation.view'])->pluck('id')
        );
    }
}

