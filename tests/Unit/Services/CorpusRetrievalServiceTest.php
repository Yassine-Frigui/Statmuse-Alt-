<?php

namespace Tests\Unit\Services;

use App\Models\Championship;
use App\Models\Player;
use App\Models\PlayerSeasonStat;
use App\Models\Season;
use App\Models\Team;
use App\Services\CorpusRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorpusRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    private CorpusRetrievalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CorpusRetrievalService::class);
    }

    public function test_ranking_query_returns_top_scorers(): void
    {
        Player::factory()->count(3)->create();
        $season = Season::factory()->create();

        $players = Player::all();

        PlayerSeasonStat::factory()->create([
            'player_id' => $players[0]->id,
            'season_id' => $season->id,
            'points' => 100,
        ]);
        PlayerSeasonStat::factory()->create([
            'player_id' => $players[1]->id,
            'season_id' => $season->id,
            'points' => 200,
        ]);
        PlayerSeasonStat::factory()->create([
            'player_id' => $players[2]->id,
            'season_id' => $season->id,
            'points' => 50,
        ]);

        $result = $this->service->getRanking('points', $season->id, 3);

        $this->assertCount(3, $result);
        $this->assertEquals(200, $result->first()->total);
    }

    public function test_ranking_query_has_limit(): void
    {
        Player::factory()->count(5)->create();
        $season = Season::factory()->create();

        foreach (Player::all() as $player) {
            PlayerSeasonStat::factory()->create([
                'player_id' => $player->id,
                'season_id' => $season->id,
                'points' => 100,
            ]);
        }

        $result = $this->service->getRanking('points', $season->id, 2);

        $this->assertCount(2, $result);
    }

    public function test_get_player_info_finds_by_name(): void
    {
        Player::factory()->create(['first_name' => 'Michael', 'last_name' => 'Jordan']);

        $result = $this->service->getPlayerInfo('Jordan');

        $this->assertCount(1, $result);
        $this->assertEquals('Michael', $result->first()->first_name);
    }

    public function test_get_player_info_returns_empty_for_unknown(): void
    {
        $result = $this->service->getPlayerInfo('UnknownPlayerName');

        $this->assertCount(0, $result);
    }

    public function test_get_team_info_finds_by_abbreviation(): void
    {
        Team::factory()->create(['abbreviation' => 'LAL', 'name' => 'Lakers']);

        $result = $this->service->getTeamInfo('LAL');

        $this->assertCount(1, $result);
        $this->assertEquals('Lakers', $result->first()->name);
    }

    public function test_get_championship_for_season(): void
    {
        $season = Season::factory()->create(['year' => 1998]);
        Championship::factory()->create(['season_id' => $season->id]);

        $result = $this->service->getChampionship(1998);

        $this->assertCount(1, $result);
    }

    public function test_get_championship_returns_empty_for_missing(): void
    {
        $result = $this->service->getChampionship(1800);

        $this->assertCount(0, $result);
    }

    public function test_retrieve_returns_empty_for_unknown_table(): void
    {
        $result = $this->service->retrieve([
            'primary_table' => 'nonexistent',
            'select' => ['*'],
            'filters' => [],
            'limit' => 10,
        ]);

        $this->assertCount(0, $result);
    }

    public function test_retrieve_with_filters(): void
    {
        Team::factory()->create(['conference' => 'Eastern', 'abbreviation' => 'BOS']);
        Team::factory()->create(['conference' => 'Eastern', 'abbreviation' => 'NYK']);
        Team::factory()->create(['conference' => 'Western', 'abbreviation' => 'LAL']);

        $result = $this->service->retrieve([
            'primary_table' => 'teams',
            'select' => ['*'],
            'filters' => [['column' => 'conference', 'operator' => '=', 'value' => 'Eastern']],
            'limit' => 10,
        ]);

        $this->assertCount(2, $result);
    }
}
