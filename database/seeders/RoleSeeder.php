<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class RoleSeeder extends Seeder {
    public function run(): void {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full system access'],
            ['name' => 'Education Staff', 'slug' => 'education_staff', 'description' => 'Day-to-day operations'],
            ['name' => 'Management', 'slug' => 'management', 'description' => 'Read-only reporting access'],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], array_merge($role, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
