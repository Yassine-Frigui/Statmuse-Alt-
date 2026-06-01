<?php

namespace Tests\Unit\Services;

use App\Enums\IntentType;
use App\Services\GeminiService;
use App\Services\QueryUnderstandingService;
use Mockery;
use Tests\TestCase;

class QueryUnderstandingServiceTest extends TestCase
{
    /**
     * @dataProvider intentProvider
     */
    public function test_explicit_intents_are_classified_locally(string $question, string $expectedIntent, ?string $expectedMetric = null, ?int $expectedLimit = null): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldNotReceive('analyze');

        $service = new QueryUnderstandingService($gemini);

        $result = $service->analyze($question);

        $this->assertSame($expectedIntent, $result['intent']);

        if ($expectedMetric !== null) {
            $this->assertSame($expectedMetric, $result['constraints']['metric']);
        }

        if ($expectedLimit !== null) {
            $this->assertSame($expectedLimit, $result['constraints']['limit']);
        }
    }

    public static function intentProvider(): array
    {
        return [
            'single game scoring' => [
                'single-game most assists in the 2022-2023 season',
                IntentType::SingleGameScoring->value,
                'assists',
                10,
            ],
            'ranking query' => [
                'top 10 scorers all-time',
                IntentType::RankingQuery->value,
                'points',
                10,
            ],
            'player info' => [
                'Who is Michael Jordan?',
                IntentType::PlayerInfo->value,
                null,
                null,
            ],
            'team info' => [
                'Tell me about the Lakers history',
                IntentType::TeamInfo->value,
                null,
                null,
            ],
            'championship query' => [
                'Who won the NBA championship in 1998?',
                IntentType::ChampionshipQuery->value,
                null,
                null,
            ],
            'comparison query' => [
                'Compare Michael Jordan vs LeBron James',
                IntentType::ComparisonQuery->value,
                null,
                2,
            ],
            'head to head' => [
                'Lakers vs Celtics head to head 2023',
                IntentType::HeadToHead->value,
                null,
                10,
            ],
            'award query' => [
                'List of MVP winners in the 2010s',
                IntentType::AwardQuery->value,
                null,
                null,
            ],
            'historical event' => [
                'Explain the ABA NBA merger',
                IntentType::HistoricalEvent->value,
                null,
                null,
            ],
            'season stats' => [
                'Luka Doncic stats in 2024-2025',
                IntentType::SeasonStats->value,
                'points',
                10,
            ],
        ];
    }
}