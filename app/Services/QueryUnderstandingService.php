<?php

namespace App\Services;

use App\Enums\IntentType;

class QueryUnderstandingService
{
    public function __construct(
        private GeminiService $gemini
    ) {}

    public function analyze(string $question): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $response = $this->gemini->analyze($systemPrompt, $question);

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $result = json_decode($text, true);

        if (!isset($result['intent']) || !$this->isValidIntent($result['intent'])) {
            $result['intent'] = IntentType::SeasonStats->value;
        }

        return $result;
    }

    private function buildSystemPrompt(): string
    {
        $intents = implode(', ', array_map(fn(IntentType $case) => $case->value, IntentType::cases()));

        return <<<PROMPT
You are an NBA query analyzer. Extract intent, entities, and constraints from basketball questions.

Supported intents: {$intents}

Respond with ONLY valid JSON in this exact structure:
{
  "intent": "one_of_the_supported_intents",
  "entities": {
    "player_name": null or string,
    "team_name": null or string,
    "season_year": null or integer,
    "competition": null or string
  },
  "constraints": {
    "metric": null or string,
    "limit": null or integer,
    "sort": null or "asc"|"desc",
    "period": null or string
  }
}
PROMPT;
    }

    private function isValidIntent(string $intent): bool
    {
        return collect(IntentType::cases())->contains(fn(IntentType $case) => $case->value === $intent);
    }
}
