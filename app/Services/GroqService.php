<?php

namespace App\Services;

use App\Services\Contracts\LLMProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService implements LLMProvider
{
    private string $apiKey;
    private string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    private string $model = 'openai/gpt-oss-120b';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model', 'openai/gpt-oss-120b');
    }

    public function generate(string $prompt): string
    {
        $response = Http::timeout(30)
            ->retry(1, 1000)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->post($this->endpoint, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            Log::error('Groq API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
