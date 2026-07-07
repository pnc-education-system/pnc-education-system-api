<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@pnc.edu'],
            [
                'role_id'    => $adminRoleId,
                'name'       => 'Super Admin',
                'email'      => 'admin@pnc.edu',
                'password'   => Hash::make('password'),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
