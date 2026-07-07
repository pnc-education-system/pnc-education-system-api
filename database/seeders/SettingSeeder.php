<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name',      'value' => 'PNC Education System', 'group' => 'general'],
            ['key' => 'site_email',     'value' => 'info@pnc.edu',         'group' => 'general'],
            ['key' => 'max_upload_mb',  'value' => '10',                   'group' => 'upload'],
            ['key' => 'timezone',       'value' => 'Asia/Phnom_Penh',      'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(['key' => $setting['key']], array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
