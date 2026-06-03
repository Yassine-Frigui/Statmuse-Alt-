<?php

namespace App\Services;

use App\Services\Contracts\LLMProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements LLMProvider
{
    private string $apiKey;
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generate(string $prompt): string
    {
        $response = Http::timeout(30)
            ->retry(1, 1000)
            ->withHeader('Content-Type', 'application/json')
            ->post($this->endpoint . '?key=' . $this->apiKey, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function generateInsight(string $prompt): array
    {
        $response = Http::timeout(30)
            ->retry(1, 1000)
            ->withHeader('Content-Type', 'application/json')
            ->post($this->endpoint . '?key=' . $this->apiKey, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            $response->throw();
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return ['content' => $text];
    }
}
