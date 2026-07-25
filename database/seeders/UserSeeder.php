<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole   = Role::where('slug', 'administrator')->firstOrFail();
        $staffRole   = Role::where('slug', 'education_staff')->firstOrFail();
        $viewerRole  = Role::where('slug', 'management')->firstOrFail();

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@pnc.edu.kh'],
            [
                'role_id'   => $adminRole->id,
                'name'      => 'Administrator',
                'password'  => Hash::make('Admin@123456'),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'staff@pnc.edu.kh'],
            [
                'role_id'   => $staffRole->id,
                'name'      => 'Samkhnn KHAN',
                'password'  => Hash::make('Staff@123456'),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'management@pnc.edu.kh'],
            [
                'role_id'   => $viewerRole->id,
                'name'      => 'Sim HUL',
                'password'  => Hash::make('Manager@123456'),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );
    }
}
