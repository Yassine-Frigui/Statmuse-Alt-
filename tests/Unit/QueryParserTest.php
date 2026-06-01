<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\QueryUnderstandingService;
use App\Enums\IntentType;

class QueryParserTest extends TestCase
{
    protected function getService(): QueryUnderstandingService
    {
        // Mock the GeminiService dependency
        $mockGemini = $this->createMock(\App\Services\GeminiService::class);
        
        return new QueryUnderstandingService($mockGemini);
    }

    /** @test */
    public function it_detects_ranking_query_for_best_scorers()
    {
        $service = $this->getService();
        $result = $service->analyze('best scorers in 2022-2023');

        $this->assertEquals(IntentType::RankingQuery->value, $result['intent']);
        $this->assertEquals('points', $result['constraints']['metric']);
        $this->assertEquals(10, $result['constraints']['limit']);
    }

    /** @test */
    public function it_detects_ranking_query_for_top_rebounders()
    {
        $service = $this->getService();
        $result = $service->analyze('top rebounders 2023');

        $this->assertEquals(IntentType::RankingQuery->value, $result['intent']);
        $this->assertEquals('rebounds', $result['constraints']['metric']);
    }

    /** @test */
    public function it_parses_season_year_from_query()
    {
        $service = $this->getService();
        $result = $service->analyze('leading scorers in 2022-2023');

        $this->assertEquals(2022, $result['entities']['season_year']);
    }

    /** @test */
    public function it_parses_single_year_from_query()
    {
        $service = $this->getService();
        $result = $service->analyze('best players in 2023');

        $this->assertEquals(2023, $result['entities']['season_year']);
    }

    /** @test */
    public function it_extracts_limit_from_query()
    {
        $service = $this->getService();
        $result = $service->analyze('top 5 scorers');

        $this->assertEquals(5, $result['constraints']['limit']);
    }

    /** @test */
    public function it_handles_fallback_when_no_intent_detected()
    {
        $service = $this->getService();
        $result = $service->analyze('random text without clear intent');

        // Should fall back to SeasonStats when no local match
        $this->assertEquals(IntentType::SeasonStats->value, $result['intent']);
    }
}