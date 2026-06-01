<?php

namespace App\Services;

class QueryTransformationService
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function transform(array $analysis): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $response = $this->gemini->transform($systemPrompt, $analysis);

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $structured = json_decode($text, true);

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
