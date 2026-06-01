<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $teams = [
            ['Lakers', 'Los Angeles', 'LAL', 'Western', 'Pacific'],
            ['Celtics', 'Boston', 'BOS', 'Eastern', 'Atlantic'],
            ['Bulls', 'Chicago', 'CHI', 'Eastern', 'Central'],
            ['Warriors', 'Golden State', 'GSW', 'Western', 'Pacific'],
            ['Heat', 'Miami', 'MIA', 'Eastern', 'Southeast'],
            ['Spurs', 'San Antonio', 'SAS', 'Western', 'Southwest'],
            ['76ers', 'Philadelphia', 'PHI', 'Eastern', 'Atlantic'],
            ['Pistons', 'Detroit', 'DET', 'Eastern', 'Central'],
        ];

        $team = $this->faker->randomElement($teams);

        return [
            'name' => $team[0],
            'city' => $team[1],
            'abbreviation' => $team[2],
            'conference' => $team[3],
            'division' => $team[4],
            'arena' => $this->faker->company() . ' Arena',
            'founded_year' => $this->faker->numberBetween(1946, 2000),
            'is_active' => true,
        ];
    }
}
