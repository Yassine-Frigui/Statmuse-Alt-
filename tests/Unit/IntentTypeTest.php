<?php

namespace Tests\Unit;

use App\Enums\IntentType;
use Tests\TestCase;

class IntentTypeTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $cases = IntentType::cases();
        $values = array_map(fn(IntentType $case) => $case->value, $cases);

        $this->assertContains('ranking_query', $values);
        $this->assertContains('player_info', $values);
        $this->assertContains('team_info', $values);
        $this->assertContains('championship_query', $values);
        $this->assertContains('historical_event', $values);
        $this->assertContains('season_stats', $values);
        $this->assertContains('head_to_head', $values);
        $this->assertContains('award_query', $values);
        $this->assertContains('rule_explanation', $values);
        $this->assertContains('comparison_query', $values);
        $this->assertContains('single_game_scoring', $values);
    }

    public function test_enum_has_eleven_cases(): void
    {
        $this->assertCount(11, IntentType::cases());
    }
}
