<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComparePageTest extends TestCase
{
    public function test_compare_page_loads(): void
    {
        $response = $this->get('/compare');

        $response->assertStatus(200);
        $response->assertSeeText('Player Matchup');
    }
}
