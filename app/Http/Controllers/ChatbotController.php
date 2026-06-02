<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotRequest;
use App\Models\Conversation;
use App\Services\GeminiService;
use App\Services\NLQueryEngine;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(
        private NLQueryEngine $engine,
        private GeminiService $gemini
    ) {}

    public function index()
    {
        return view('chatbot.index');
    }

    public function ask(ChatbotRequest $request): JsonResponse
    {
        $question = $request->input('message');

        try {
            $result = $this->engine->ask($question);

            $conversation = null;
            if (auth()->check()) {
                $conversation = Conversation::create([
                    'user_id' => auth()->id(),
                    'messages' => [
                        ['role' => 'user', 'content' => $question],
                        ['role' => 'assistant', 'content' => $result['reply'], 'retrieved_data' => $result['data']],
                    ],
                ]);
            }

            return response()->json([
                'reply' => $result['reply'],
                'data' => $result['data'],
                'conversation_id' => $conversation?->id,
                'intent' => $result['intent'],
                'entities' => $result['entities'],
                'debug' => $this->engine->getTrace(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'reply' => 'Sorry, I encountered an error processing your question. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function insight(ChatbotRequest $request): JsonResponse
    {
        $prompt = $request->input('message');

        try {
            $response = $this->gemini->generateInsight($prompt);

            return response()->json([
                'reply' => $response['content'] ?? 'No insight generated.',
                'source' => 'gemini',
                'prompt' => $prompt,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'reply' => 'Unable to generate insight at this time. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function history(Conversation $conversation): JsonResponse
    {
        return response()->json($conversation);
    }
}
