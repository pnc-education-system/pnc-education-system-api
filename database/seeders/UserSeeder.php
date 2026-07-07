<?php

namespace Database\Seeders;

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


        User::firstOrCreate(
            ['email' => 'admin@pnc.edu.kh'],
            [
                'role_id'   => $adminRole->id,
                'name'      => 'System Administrator',
                'password'  => Hash::make('Admin@123456'),
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff@pnc.edu.kh'],
            [
                'role_id'   => $staffRole->id,
                'name'      => 'Chandy Srin',
                'password'  => Hash::make('Staff@123456'),
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'management@pnc.edu.kh'],
            [
                'role_id'   => $viewerRole->id,
                'name'      => 'Sok Seyla',
                'password'  => Hash::make('Manager@123456'),
                'is_active' => true,
            ]
        );
    }
}
