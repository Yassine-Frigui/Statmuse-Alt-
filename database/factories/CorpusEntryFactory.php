<?php

namespace Database\Factories;

use App\Models\CorpusEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class CorpusEntryFactory extends Factory
{
    protected $model = CorpusEntry::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraphs(3, true),
            'category' => $this->faker->randomElement(['history', 'rules', 'biography', 'event']),
            'tags' => $this->faker->randomElements(['NBA', 'ABA', 'history', 'rules', 'playoffs', 'finals', 'draft'], 2),
            'source' => $this->faker->url(),
        ];
    }
}
