<?php

namespace Database\Factories;

use App\Models\CardTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardTemplateFactory extends Factory
{
    protected $model = CardTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' Template',
            'layout_json' => json_encode([
                'background_color' => fake()->hexColor(),
                'text_color' => fake()->hexColor(),
            ]),
            'is_default' => false,
        ];
    }
}
