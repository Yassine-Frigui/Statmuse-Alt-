<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QueryTransformationService
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function transform(array $analysis): array
    {
        if (($analysis['intent'] ?? null) === 'single_game_scoring') {
            return [
                'intent_type' => 'single_game_scoring',
                'primary_table' => 'game_player_stats',
                'select' => [
                    'game_player_stats.*',
                    'games.date as game_date',
                    'games.stage as game_stage',
                    'seasons.year as season_year',
                    'seasons.label as season_label',
                    'players.first_name',
                    'players.last_name',
                    'players.position',
                    'teams.name as team_name',
                    'teams.abbreviation as team_abbreviation',
                ],
                'filters' => [],
                'order_by' => ['column' => 'points', 'direction' => 'desc'],
                'limit' => $analysis['constraints']['limit'] ?? 10,
                'group_by' => null,
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        try {
            $response = $this->gemini->transform($systemPrompt, $analysis);
        } catch (\Throwable $e) {
            Log::warning('QueryTransformationService: Gemini transform failed', ['error' => $e->getMessage()]);
            return $this->defaultQuery();
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (is_null($text)) {
            Log::warning('QueryTransformationService: Gemini returned no text', ['response' => $response]);
            return $this->defaultQuery();
        }

        $structured = json_decode($text, true);

        if (!is_array($structured)) {
            Log::warning('QueryTransformationService: JSON decode failed', ['text' => $text]);
            return $this->defaultQuery();
        }

        return $this->validate($structured) ? $structured : $this->defaultQuery();
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an NBA query transformer. Convert the intent analysis into a structured database query.

Respond with ONLY valid JSON in this exact structure:
{
  "intent_type": "string",
  "primary_table": "players"|"teams"|"seasons"|"games"|"championships"|"corpus_entries",
  "select": ["column1", "column2"],
  "filters": [{"column": "string", "operator": "="|">"|"<"|">="|"<="|"LIKE", "value": "mixed"}],
  "order_by": {"column": "string", "direction": "asc"|"desc"},
  "limit": integer,
  "group_by": null or string
}

Use only NBA database columns: points, rebounds, assists, steals, blocks, player_name, team_name, season_year, points, games_played, etc.
PROMPT;
    }

    private function validate(array $query): bool
    {
        return isset($query['intent_type'], $query['primary_table']);
    }

    private function defaultQuery(): array
    {
        return [
            'intent_type' => 'season_stats',
            'primary_table' => 'players',
            'select' => ['*'],
            'filters' => [],
            'order_by' => null,
            'limit' => 10,
            'group_by' => null,
        ];
    }
}
