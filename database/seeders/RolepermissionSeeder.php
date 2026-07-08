<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolepermissionSeeder extends Seeder
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
    }
}


