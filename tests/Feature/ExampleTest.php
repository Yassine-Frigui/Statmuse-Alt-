<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_redirects_root_to_chatbot(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/chatbot');
    }

    public function test_chatbot_page_loads(): void
    {
        $response = $this->get('/chatbot');

        $response->assertStatus(200);
    }
}
