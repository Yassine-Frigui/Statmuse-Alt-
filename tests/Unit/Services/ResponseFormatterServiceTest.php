<?php

namespace Tests\Unit\Services;

use App\Services\ResponseFormatterService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ResponseFormatterServiceTest extends TestCase
{
    public function test_format_returns_no_data_message_for_empty_collection(): void
    {
        $geminiMock = Mockery::mock('App\Services\GeminiService');
        $formatter = new ResponseFormatterService($geminiMock);

        $result = $formatter->format(
            ['intent_type' => 'player_info'],
            collect(),
            'Tell me about Jordan'
        );

        $this->assertStringContainsString('No data found', $result);
    }

    public function test_format_simple_creates_table(): void
    {
        $geminiMock = Mockery::mock('App\Services\GeminiService');
        $formatter = new ResponseFormatterService($geminiMock);

        $result = $formatter->formatSimple('Top Players', [
            ['name' => 'Jordan', 'points' => 30],
            ['name' => 'LeBron', 'points' => 27],
        ], ['name', 'points']);

        $this->assertStringContainsString('Top Players', $result);
        $this->assertStringContainsString('Jordan', $result);
        $this->assertStringContainsString('LeBron', $result);
    }
}
