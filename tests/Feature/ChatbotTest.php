<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Contracts\LLMProvider;
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

        $llmMock = Mockery::mock(LLMProvider::class);
        $llmMock->shouldReceive('generate')
            ->andReturn(json_encode([
                'type' => 'query',
                'sql' => "SELECT first_name, last_name, position
FROM nba_players
WHERE LOWER(last_name) LIKE '%jordan%'
ORDER BY last_name ASC
LIMIT 5;",
                'reply' => 'Here are the players matching your search:',
            ]));

        $this->instance(LLMProvider::class, $llmMock);
    }

    public function test_guest_can_access_chatbot_api(): void
    {
        $response = $this->postJson('/api/chatbot', [
            'message' => 'Who is Michael Jordan?',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['reply', 'data', 'sql']);
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
            ->assertJsonStructure(['reply', 'data', 'sql', 'conversation_id']);
    }

    public function test_sport_parameter_is_accepted(): void
    {
        $response = $this->postJson('/api/chatbot', [
            'message' => 'Who is Michael Jordan?',
            'sport' => 'nba',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['reply', 'data', 'sql']);
    }

    public function test_unanswerable_question_returns_friendly_message(): void
    {
        $llmMock = Mockery::mock(LLMProvider::class);
        $llmMock->shouldReceive('generate')
            ->andReturn(json_encode([
                'type' => 'unanswerable',
                'reply' => "I don't have data for that. Our database covers seasons 2015–2024.",
            ]));

        $this->instance(LLMProvider::class, $llmMock);

        $response = $this->postJson('/api/chatbot', [
            'message' => '1980 best scorers',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['reply' => "I don't have data for that. Our database covers seasons 2015–2024."])
            ->assertJsonMissing(['sql']);
    }
}
