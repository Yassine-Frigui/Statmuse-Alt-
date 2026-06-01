<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        $firstNames = ['Michael', 'LeBron', 'Kobe', 'Stephen', 'Kevin', 'Shaquille', 'Tim', 'Magic', 'Larry', 'Wilt', 'Bill', 'Hakeem', 'Kareem', 'Julius', 'Moses', 'Dirk', 'Dwyane', 'Chris', 'Allen', 'Jason'];
        $lastNames = ['Jordan', 'James', 'Bryant', 'Curry', 'Durant', "O'Neal", 'Duncan', 'Johnson', 'Bird', 'Chamberlain', 'Russell', "Olajuwon", "Abdul-Jabbar", 'Erving', 'Malone', 'Nowitzki', 'Wade', 'Paul', 'Iverson', 'Kidd'];

        return [
            'first_name' => $this->faker->randomElement($firstNames),
            'last_name' => $this->faker->randomElement($lastNames),
            'position' => $this->faker->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'height' => $this->faker->numberBetween(175, 220),
            'weight' => $this->faker->numberBetween(75, 135),
            'birth_date' => $this->faker->date('Y-m-d', '2000-01-01'),
            'college' => $this->faker->randomElement(['North Carolina', 'Duke', 'Kentucky', 'Kansas', 'UCLA', 'Michigan', 'Syracuse', 'Georgetown']),
            'drafted_year' => $this->faker->numberBetween(1985, 2020),
            'bio' => $this->faker->optional()->sentence(),
        ];
    }

    public function withStats(int $count = 1, array $overrides = []): static
    {
        return $this->has(PlayerSeasonStatFactory::new()->count($count)->state($overrides), 'seasonStats');
    }
}
