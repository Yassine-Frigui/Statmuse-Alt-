<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;

    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function analyze(string $systemPrompt, string $userQuery): array
    {
        return $this->call([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['parts' => [['text' => $userQuery]]]],
        ]);
    }

    public function transform(string $systemPrompt, array $context): array
    {
        return $this->call([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['parts' => [['text' => json_encode($context)]]]],
        ]);
    }

    public function format(string $systemPrompt, array $data, string $question): string
    {
        $jsonPayload = json_encode([
            'question' => $question,
            'data' => $data,
        ]);

        $response = $this->call([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['parts' => [['text' => $jsonPayload]]]],
        ]);

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? 'Unable to format response.';
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
        }

        $response = $this->call([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
        ]);

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function call(array $payload): array
    {
        $url = $this->endpoint . '?key=' . $this->apiKey;

        $response = Http::timeout(30)
            ->retry(1, 1000)
            ->withHeader('Content-Type', 'application/json')
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }
}
