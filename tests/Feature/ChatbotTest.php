<?php

namespace Tests\Feature;

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

    public function test_chatbot_page_loads_for_guest(): void
    {
        $response = $this->get('/chatbot');

        $response->assertStatus(200);
    }

    public function test_chatbot_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/chatbot');

        $response->assertStatus(200);
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
