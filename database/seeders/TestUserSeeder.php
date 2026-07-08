<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Full access']
        );

        User::firstOrCreate(
            ['email' => 'test@pnc.edu'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'role_id' => $role->id,
                'is_active' => true,
            ]
        );
    }
}
