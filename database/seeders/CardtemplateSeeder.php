<?php

namespace Database\Seeders;

use App\Models\CardTemplate;
use Illuminate\Database\Seeder;

class CardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'       => 'Classic',
                'layout_key' => 'classic',
                'is_default' => true,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'text',  'field' => 'intake_year',   'x' => 30, 'y' => 30, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Modern',
                'layout_key' => 'modern',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 13, 'bold' => true, 'color' => '#2563eb'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 10],
                        ['type' => 'text',  'field' => 'intake_year',   'x' => 30, 'y' => 30, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Premium',
                'layout_key' => 'premium',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true, 'color' => '#f59e0b'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Corporate',
                'layout_key' => 'corporate',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true, 'color' => '#16a34a'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Corporate Blue',
                'layout_key' => 'corporate-blue',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true, 'color' => '#2563eb'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Corporate Yellow',
                'layout_key' => 'corporate-yellow',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true, 'color' => '#ca8a04'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
            [
                'name'       => 'Official',
                'layout_key' => 'official',
                'is_default' => false,
                'layout_json'=> [
                    'width'    => 85.6,
                    'height'   => 54,
                    'unit'     => 'mm',
                    'elements' => [
                        ['type' => 'text',  'field' => 'full_name',     'x' => 30, 'y' => 10, 'fontSize' => 12, 'bold' => true, 'color' => '#1B3FA0'],
                        ['type' => 'text',  'field' => 'student_id_no', 'x' => 30, 'y' => 22, 'fontSize' => 9],
                        ['type' => 'image', 'field' => 'photo_path',    'x' => 5,  'y' => 5,  'width' => 22, 'height' => 28],
                        ['type' => 'qr',    'field' => 'qr_token',      'x' => 60, 'y' => 28, 'size' => 20],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            CardTemplate::firstOrCreate(
                ['layout_key' => $template['layout_key']],
                $template
            );
        }
    }
}
