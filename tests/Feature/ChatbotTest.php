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
        $geminiMock->shouldReceive('generateContent')
            ->andReturn(json_encode([
                'intent' => 'player_info',
                'entities' => ['players' => ['Michael Jordan'], 'teams' => [], 'season' => null, 'metrics' => []],
                'explanation' => 'Looking up player information',
                'query' => [
                    'from' => 'players',
                    'joins' => [],
                    'columns' => [
                        ['expr' => 'players.first_name', 'alias' => 'first_name'],
                        ['expr' => 'players.last_name', 'alias' => 'last_name'],
                        ['expr' => 'players.position', 'alias' => 'position'],
                    ],
                    'where' => [['col' => 'players.last_name', 'op' => 'LIKE', 'val' => '%Jordan%']],
                    'order_by' => [['expr' => 'players.last_name', 'dir' => 'ASC']],
                    'group_by' => [],
                    'limit' => 5,
                ],
                'answer' => 'Here are the players matching your search:',
            ]));
        $geminiMock->shouldReceive('generateInsight')
            ->andReturn(['content' => 'Deep insight.']);

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
}
