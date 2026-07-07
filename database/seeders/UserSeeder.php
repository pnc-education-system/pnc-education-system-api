<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            User::firstOrCreate(
                ['email' => 'admin@pnc.edu'],
                [
                    'role_id'   => $adminRole->id,
                    'name'      => 'Super Admin',
                    'password'  => Hash::make('password'),
                    'is_active' => true,
                ]
            );
        }
    }
}
