<?php

namespace App\Http\Controllers;

use App\Enums\IntentType;
use App\Http\Requests\ChatbotRequest;
use App\Models\Conversation;
use App\Services\CorpusRetrievalService;
use App\Services\GeminiService;
use App\Services\QueryTransformationService;
use App\Services\QueryUnderstandingService;
use App\Services\ResponseFormatterService;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(
        private QueryUnderstandingService $understandingService,
        private QueryTransformationService $transformationService,
        private CorpusRetrievalService $retrievalService,
        private ResponseFormatterService $formatterService,
    ) {}

    public function index()
    {
        $conversations = auth()->user()
            ? auth()->user()->conversations()->orderByDesc('created_at')->get()
            : collect();

        return view('chatbot.index', compact('conversations'));
    }

    public function ask(ChatbotRequest $request): JsonResponse
    {
        $question = $request->input('message');

        try {
            $analysis = $this->understandingService->analyze($question);
            $structuredQuery = $this->transformationService->transform($analysis);
            $data = $this->retrievalService->retrieve($structuredQuery);
            $reply = $this->formatterService->format($structuredQuery, $data, $question);

            $conversation = null;
            if (auth()->check()) {
                $conversation = Conversation::create([
                    'user_id' => auth()->id(),
                    'messages' => [
                        ['role' => 'user', 'content' => $question, 'structured_query' => $structuredQuery],
                        ['role' => 'assistant', 'content' => $reply, 'retrieved_data' => $data->toArray()],
                    ],
                ]);
            }

            return response()->json([
                'reply' => $reply,
                'data' => $data->toArray(),
                'conversation_id' => $conversation?->id,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'reply' => 'Sorry, I encountered an error processing your question. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function history(Conversation $conversation): JsonResponse
    {
        return response()->json($conversation);
    }
}
