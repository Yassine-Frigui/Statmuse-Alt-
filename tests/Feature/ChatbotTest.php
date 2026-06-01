<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('analyze')
            ->andReturn([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"intent": "season_stats", "entities": {"player_name": "Michael Jordan"}}']]]]
                ]
            ]);
        $geminiMock->shouldReceive('transform')
            ->andReturn([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"intent_type": "player_info", "primary_table": "players", "select": ["*"], "filters": [{"column": "last_name", "operator": "LIKE", "value": "%Jordan%"}], "limit": 10}']]]]
                ]
            ]);
        $geminiMock->shouldReceive('format')
            ->andReturn('Michael Jordan is widely considered the greatest basketball player of all time.');

        $this->instance(GeminiService::class, $geminiMock);
    }

    public function test_guest_can_access_chatbot_api(): void
    {
        $response = $this->postJson('/api/chatbot', [
            'message' => 'Who is Michael Jordan?',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['reply', 'data']);
    }

    public function test_chatbot_validates_message_required(): void
    {
        $response = $this->postJson('/api/chatbot', [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_ask_question(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chatbot', [
            'message' => 'Who is Michael Jordan?',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['reply', 'data', 'conversation_id']);
    }

    public function test_single_game_scoring_question_uses_game_stats(): void
    {
        $season = Season::factory()->create(['year' => 2024, 'label' => '2024-25']);
        $homeTeam = Team::factory()->create(['name' => 'Dallas Mavericks', 'abbreviation' => 'DAL']);
        $awayTeam = Team::factory()->create(['name' => 'Boston Celtics', 'abbreviation' => 'BOS']);
        $player = Player::factory()->create(['first_name' => 'Luka', 'last_name' => 'Doncic']);
        $otherPlayer = Player::factory()->create(['first_name' => 'Jrue', 'last_name' => 'Holiday']);
        $game = Game::factory()->create([
            'season_id' => $season->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        GamePlayerStat::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $homeTeam->id,
            'points' => 73,
            'rebounds' => 10,
            'assists' => 7,
            'steals' => 1,
            'blocks' => 0,
            'minutes' => 41.5,
            'is_scoring_leader' => true,
        ]);

        GamePlayerStat::create([
            'game_id' => $game->id,
            'player_id' => $otherPlayer->id,
            'team_id' => $awayTeam->id,
            'points' => 18,
            'rebounds' => 5,
            'assists' => 15,
            'steals' => 2,
            'blocks' => 0,
            'minutes' => 36.0,
            'is_scoring_leader' => false,
        ]);

        $response = $this->postJson('/api/chatbot', [
            'message' => 'single-game most assists in the 2024-2025 by a player',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('intent', 'single_game_scoring')
            ->assertJsonPath('data.0.first_name', 'Jrue')
            ->assertJsonPath('data.0.last_name', 'Holiday')
            ->assertJsonPath('data.0.assists', 15)
            ->assertJsonFragment(['first_name' => 'Jrue', 'last_name' => 'Holiday', 'assists' => 15]);
    }
}
