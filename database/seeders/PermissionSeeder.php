<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder {
    public function run(): void {
        $permissions = [
            ['name' => 'Manage Users',       'slug' => 'users.manage',       'module' => 'admin'],
            ['name' => 'Manage Roles',        'slug' => 'roles.manage',       'module' => 'admin'],
            ['name' => 'View Audit Logs',     'slug' => 'audit.view',         'module' => 'admin'],
            ['name' => 'Manage Settings',     'slug' => 'settings.manage',    'module' => 'admin'],
            ['name' => 'View Students',       'slug' => 'students.view',      'module' => 'students'],
            ['name' => 'Edit Students',       'slug' => 'students.edit',      'module' => 'students'],
            ['name' => 'Import Students',     'slug' => 'students.import',    'module' => 'students'],
            ['name' => 'Manage Enrollment',   'slug' => 'enrollment.manage',  'module' => 'enrollment'],
            ['name' => 'Generate ID Cards',   'slug' => 'cards.generate',     'module' => 'cards'],
            ['name' => 'View Records',        'slug' => 'records.view',       'module' => 'records'],
            ['name' => 'Manage Records',      'slug' => 'records.manage',     'module' => 'records'],
            ['name' => 'View Evaluations',    'slug' => 'evaluation.view',    'module' => 'evaluation'],
            ['name' => 'Manage Evaluations',  'slug' => 'evaluation.manage',  'module' => 'evaluation'],
            ['name' => 'Submit Evaluation',   'slug' => 'evaluation.submit',  'module' => 'evaluation'],
            ['name' => 'View Reports',        'slug' => 'reports.view',       'module' => 'reports'],
            ['name' => 'Manage Batches',      'slug' => 'batches.manage',     'module' => 'enrollment'],
        ];
        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(['slug' => $p['slug']], array_merge($p, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
