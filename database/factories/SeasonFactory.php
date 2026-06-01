<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(1990, 2024);

        return [
            'year' => $year,
            'label' => $year . '-' . substr($year + 1, -2),
            'start_date' => $year . '-10-15',
            'end_date' => ($year + 1) . '-06-20',
        ];
    }
}
