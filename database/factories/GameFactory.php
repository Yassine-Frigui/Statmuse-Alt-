<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('2023-10-01', '2024-06-01')->format('Y-m-d'),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_score' => $this->faker->numberBetween(90, 130),
            'away_score' => $this->faker->numberBetween(90, 130),
            'season_id' => Season::factory(),
            'stage' => $this->faker->randomElement(['Regular Season', 'Playoffs', 'Finals']),
        ];
    }

    public function finals(): static
    {
        return $this->state(fn(array $attributes) => [
            'stage' => 'Finals',
        ]);
    }
}
