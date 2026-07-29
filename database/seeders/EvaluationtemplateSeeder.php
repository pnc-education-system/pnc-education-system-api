<?php

namespace Database\Seeders;

use App\Models\EvaluationCategory;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use Illuminate\Database\Seeder;

class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Create or get the main template
        $template = EvaluationForm::firstOrCreate(
            ['name' => 'Young Stars Self-Assessment'],
            ['description' => 'Standard self-evaluation based on the UK Young Stars model.', 'is_active' => true]
        );

        // Skip if template already has questions
        if ($template->questions()->count() > 0) {
            return;
        }

        // Define categories with their questions
        $categories = [
            [
                'name'       => 'Communication',
                'sort_order' => 1,
                'questions' => [
                    'I express my ideas clearly in meetings and conversations.',
                    'I listen actively and ask good questions.',
                ],
            ],
            [
                'name'       => 'Teamwork',
                'sort_order' => 2,
                'questions' => [
                    'I support teammates and contribute to group tasks.',
                    'I respect different opinions and resolve conflicts calmly.',
                ],
            ],
            [
                'name'       => 'Responsibility',
                'sort_order' => 3,
                'questions' => [
                    'I complete tasks on time and to a high standard.',
                    'I take ownership of my mistakes and learn from them.',
                ],
            ],
            [
                'name'       => 'Problem Solving',
                'sort_order' => 4,
                'questions' => [
                    'I analyse situations before proposing solutions.',
                    'I stay composed and find creative approaches under pressure.',
                ],
            ],
            [
                'name'       => 'Leadership',
                'sort_order' => 5,
                'questions' => [
                    'I motivate others and lead by example.',
                    'I make fair decisions and guide the team toward goals.',
                ],
            ],
            [
                'name'       => 'Learning Mindset',
                'sort_order' => 6,
                'questions' => [
                    'I actively seek feedback and apply what I learn.',
                    'I am curious and enjoy exploring new ideas and skills.',
                ],
            ],
            [
                'name'       => 'Professional Behavior',
                'sort_order' => 7,
                'questions' => [
                    'I am punctual, well-presented, and professional.',
                    'I communicate respectfully with all staff and peers.',
                ],
            ],
        ];

        $sortOrder = 1;
        foreach ($categories as $cat) {
            $category = $template->categories()->create([
                'name'       => $cat['name'],
                'sort_order' => $cat['sort_order'],
            ]);

            foreach ($cat['questions'] as $questionText) {
                $category->questions()->create([
                    'template_id'   => $template->id,
                    'question_text' => $questionText,
                    'score'         => 5,
                    'sort_order'    => $sortOrder++,
                ]);
            }
        }

        $this->command->info("Seeded template '{$template->name}' with " . count($categories) . " categories and " . ($sortOrder - 1) . " questions.");
    }
}
