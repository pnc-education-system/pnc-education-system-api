<?php
<<<<<<< HEAD

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
=======
namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
>>>>>>> 4fc27d8d1c3a96455f2b552493b66ceb6692b860
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
<<<<<<< HEAD
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
=======
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
>>>>>>> 4fc27d8d1c3a96455f2b552493b66ceb6692b860
            ]
        );
    }
}
