<?php

namespace App\Http\Controllers;

use App\Enums\IntentType;
use App\Http\Requests\ChatbotRequest;
use App\Models\Conversation;
use App\Services\CorpusRetrievalService;
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
        return view('chatbot.index');
    }

    public function ask(ChatbotRequest $request): JsonResponse
    {
        $question = $request->input('message');

        try {
            $analysis = $this->understandingService->analyze($question);

            if (($analysis['intent'] ?? null) === IntentType::SingleGameScoring->value) {
                $seasonYear = $analysis['entities']['season_year'] ?? $this->retrievalService->latestSeasonYear();
                $limit = $analysis['constraints']['limit'] ?? 10;
                $metric = $analysis['constraints']['metric'] ?? 'points';

                $data = $this->retrievalService->getSingleGameScoringLeaders($seasonYear, $limit, $metric);
                $reply = $this->formatterService->formatSimple(
                    $this->formatSingleGameTitle($metric) . ($seasonYear ? ' ' . $this->formatSeasonLabel((int) $seasonYear) : ''),
                    $data->toArray(),
                    ['first_name', 'last_name', 'team_abbreviation', 'game_date', 'points', 'rebounds', 'assists']
                );

                $conversation = null;
                if (auth()->check()) {
                    $conversation = Conversation::create([
                        'user_id' => auth()->id(),
                        'messages' => [
                            ['role' => 'user', 'content' => $question, 'structured_query' => [
                                'intent_type' => IntentType::SingleGameScoring->value,
                                'primary_table' => 'game_player_stats',
                                'filters' => $seasonYear ? [['column' => 'season_year', 'operator' => '=', 'value' => $seasonYear]] : [],
                                'limit' => $limit,
                                'metric' => $metric,
                            ]],
                            ['role' => 'assistant', 'content' => $reply, 'retrieved_data' => $data->toArray()],
                        ],
                    ]);
                }

                return response()->json([
                    'reply' => $reply,
                    'data' => $data->toArray(),
                    'conversation_id' => $conversation?->id,
                    'intent' => IntentType::SingleGameScoring->value,
                    'entities' => array_values(array_filter([$seasonYear ? $this->formatSeasonLabel((int) $seasonYear) : null, $limit, $metric])),
                ]);
            }

            // Handle RankingQuery intent for "best scorers in 2022-2023"
            if (($analysis['intent'] ?? null) === IntentType::RankingQuery->value) {
                $seasonYear = $analysis['entities']['season_year'] ?? null;
                $limit = $analysis['constraints']['limit'] ?? 10;
                $metric = $analysis['constraints']['metric'] ?? 'points';

                // Get season ID if season year is provided
                $season = null;
                if ($seasonYear) {
                    $season = \App\Models\Season::where('year', $seasonYear)->first();
                }

                $data = $this->retrievalService->getRanking($metric, $season?->id, $limit);
                
                $reply = $this->formatterService->formatSimple(
                    $this->formatRankingTitle($metric) . ($seasonYear ? ' ' . $this->formatSeasonLabel((int) $seasonYear) : ' All Time'),
                    $data->toArray(),
                    ['player.first_name', 'player.last_name', 'total', 'player.nba_api_id']
                );

                $conversation = null;
                if (auth()->check()) {
                    $conversation = Conversation::create([
                        'user_id' => auth()->id(),
                        'messages' => [
                            ['role' => 'user', 'content' => $question, 'structured_query' => [
                                'intent_type' => IntentType::RankingQuery->value,
                                'primary_table' => 'player_season_stats',
                                'filters' => $season ? [['column' => 'season_id', 'operator' => '=', 'value' => $season->id]] : [],
                                'limit' => $limit,
                                'metric' => $metric,
                            ]],
                            ['role' => 'assistant', 'content' => $reply, 'retrieved_data' => $data->toArray()],
                        ],
                    ]);
                }

                return response()->json([
                    'reply' => $reply,
                    'data' => $data->toArray(),
                    'conversation_id' => $conversation?->id,
                    'intent' => IntentType::RankingQuery->value,
                    'entities' => array_values(array_filter([$seasonYear ? $this->formatSeasonLabel((int) $seasonYear) : null])),
                ]);
            }

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

            $entities = array_values(array_filter([
                $analysis['entities']['player_name'] ?? null,
                $analysis['entities']['team_name'] ?? null,
                $analysis['entities']['competition'] ?? null,
                $analysis['entities']['season_year'] ?? null,
            ]));

            return response()->json([
                'reply' => $reply,
                'data' => $data->toArray(),
                'conversation_id' => $conversation?->id,
                'intent' => $analysis['intent'] ?? null,
                'entities' => $entities,
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

    private function formatSeasonLabel(int $seasonYear): string
    {
        return $seasonYear . '-' . substr((string) ($seasonYear + 1), -2);
    }

    private function formatSingleGameTitle(string $metric): string
    {
        return match ($metric) {
            'points' => 'Single-Game Scoring Leaders',
            'rebounds' => 'Single-Game Rebound Leaders',
            'assists' => 'Single-Game Assist Leaders',
            'steals' => 'Single-Game Steal Leaders',
            'blocks' => 'Single-Game Block Leaders',
            default => 'Single-Game Leaders',
        };
    }

    private function formatRankingTitle(string $metric): string
    {
        return match ($metric) {
            'points' => 'Top Scorers',
            'rebounds' => 'Top Rebounders',
            'assists' => 'Top Assist Leaders',
            'steals' => 'Top Steals Leaders',
            'blocks' => 'Top Block Leaders',
            default => 'Top Players',
        };
    }
}
