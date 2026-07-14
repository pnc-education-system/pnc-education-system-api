<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SettingSeeder extends Seeder {
    public function run(): void {
        $settings = [
            ['key' => 'site_name', 'value' => 'PNC Education System', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'Asia/Phnom_Penh', 'group' => 'general'],
        ];
        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], array_merge($s, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
