<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotRequest;
use App\Models\Conversation;
use App\Services\QueryEngine;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(
        private QueryEngine $engine
    ) {}

    public function index()
    {
        return view('chatbot.index');
    }

    public function ask(ChatbotRequest $request): JsonResponse
    {
        $question = $request->input('message');
        $sport = $request->input('sport', 'nba');

        try {
            $result = $this->engine->ask($sport, $question);

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
                'sql' => $result['sql'],
                'conversation_id' => $conversation?->id,
                'debug' => $this->engine->getTrace(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'reply' => 'Sorry, I encountered an error processing your question. Please try again.',
                'error' => $e->getMessage(),
                'debug' => $this->engine->getTrace(),
            ], 500);
        }
    }

    public function history(Conversation $conversation): JsonResponse
    {
        return response()->json($conversation);
    }
}
