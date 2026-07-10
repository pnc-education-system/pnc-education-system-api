<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class RoleSeeder extends Seeder {
    public function run(): void {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full system access'],
            ['name' => 'Teacher', 'slug' => 'teacher', 'description' => 'Manage courses'],
            ['name' => 'Student', 'slug' => 'student', 'description' => 'Read only'],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], array_merge($role, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
