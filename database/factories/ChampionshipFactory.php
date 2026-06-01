<?php

namespace Database\Factories;

use App\Models\Championship;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChampionshipFactory extends Factory
{
    protected $model = Championship::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'champion_team_id' => Team::factory(),
            'runner_up_team_id' => Team::factory(),
            'result_label' => '4-2',
        ];
    }
}
