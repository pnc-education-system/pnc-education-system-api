<?php

namespace Database\Seeders;

use App\Models\EvaluationCategory;
use App\Models\EvaluationTemplate;
use Illuminate\Database\Seeder;

class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = EvaluationTemplate::firstOrCreate(
            ['name' => 'Young Stars Self-Assessment'],
            ['description' => 'Standard self-evaluation based on the UK Young Stars model.', 'is_active' => true]
        );
        if ($template->questions()->count() === 0) {
            $catMap = EvaluationCategory::pluck('id', 'slug');
            $questions = [
                ['category' => 'communication',         'text' => 'I express my ideas clearly in meetings and conversations.'],
                ['category' => 'communication',         'text' => 'I listen actively and ask good questions.'],
                ['category' => 'teamwork',              'text' => 'I support teammates and contribute to group tasks.'],
                ['category' => 'teamwork',              'text' => 'I respect different opinions and resolve conflicts calmly.'],
                ['category' => 'responsibility',        'text' => 'I complete tasks on time and to a high standard.'],
                ['category' => 'responsibility',        'text' => 'I take ownership of my mistakes and learn from them.'],
                ['category' => 'problem_solving',       'text' => 'I analyse situations before proposing solutions.'],
                ['category' => 'problem_solving',       'text' => 'I stay composed and find creative approaches under pressure.'],
                ['category' => 'leadership',            'text' => 'I motivate others and lead by example.'],
                ['category' => 'leadership',            'text' => 'I make fair decisions and guide the team toward goals.'],
                ['category' => 'learning_mindset',      'text' => 'I actively seek feedback and apply what I learn.'],
                ['category' => 'learning_mindset',      'text' => 'I am curious and enjoy exploring new ideas and skills.'],
                ['category' => 'professional_behavior', 'text' => 'I am punctual, well-presented, and professional.'],
                ['category' => 'professional_behavior', 'text' => 'I communicate respectfully with all staff and peers.'],
            ];
            foreach ($questions as $i => $q) {
                $template->questions()->create([
                    'category_id'   => $catMap[$q['category']],
                    'question_text' => $q['text'],
                    'max_score'     => 5,
                    'sort_order'    => $i + 1,
                ]);
            }
        }
    }
}
