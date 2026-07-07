<?php

namespace Database\Seeders;

use App\Models\EvaluationCategory;
use Illuminate\Database\Seeder;

class EvaluationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Communication',         'slug' => 'communication',         'sort_order' => 1],
            ['name' => 'Teamwork',              'slug' => 'teamwork',              'sort_order' => 2],
            ['name' => 'Responsibility',        'slug' => 'responsibility',        'sort_order' => 3],
            ['name' => 'Problem Solving',       'slug' => 'problem_solving',       'sort_order' => 4],
            ['name' => 'Leadership',            'slug' => 'leadership',            'sort_order' => 5],
            ['name' => 'Learning Mindset',      'slug' => 'learning_mindset',      'sort_order' => 6],
            ['name' => 'Professional Behavior', 'slug' => 'professional_behavior', 'sort_order' => 7],
        ];

        foreach ($categories as $cat) {
            EvaluationCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
