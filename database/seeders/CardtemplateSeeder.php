<?php

namespace Database\Seeders;

use App\Models\CardTemplate;
use Illuminate\Database\Seeder;

class CardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        CardTemplate::firstOrCreate(
            ['name' => 'Standard PNC Card'],
            [
                'is_default'  => true,
                'layout_json' => [
                    'width'  => 85.6,
                    'height' => 54,
                    'unit'   => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'text',  'field' => 'intake_year',   'x' => 30, 'y' => 30, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ]
        );
    }
}
 
