<?php

namespace Database\Factories;

use App\Models\PlayerSeasonStat;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerSeasonStatFactory extends Factory
{
    protected $model = PlayerSeasonStat::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'games_played' => $this->faker->numberBetween(20, 82),
            'points' => $this->faker->randomFloat(1, 5, 35),
            'rebounds' => $this->faker->randomFloat(1, 2, 15),
            'assists' => $this->faker->randomFloat(1, 1, 12),
            'steals' => $this->faker->randomFloat(1, 0.5, 3),
            'blocks' => $this->faker->randomFloat(1, 0.2, 3.5),
            'minutes' => $this->faker->randomFloat(1, 15, 40),
            'fg_pct' => $this->faker->randomFloat(3, 0.400, 0.550),
            'three_pct' => $this->faker->randomFloat(3, 0.250, 0.450),
            'ft_pct' => $this->faker->randomFloat(3, 0.650, 0.900),
        ];
    }
}
