<?php

namespace Database\Factories;

use App\Models\Award;
use Illuminate\Database\Eloquent\Factories\Factory;

class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['MVP', 'Rookie of the Year', 'Defensive Player of the Year', 'Sixth Man of the Year', 'Most Improved Player']),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
