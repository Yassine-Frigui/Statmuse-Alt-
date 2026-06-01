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
        $local = $this->classifyLocally($question);
        if ($local !== null) {
            return $local;
        }

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

    private function classifyLocally(string $question): ?array
    {
        $normalized = $this->normalizeQuestion($question);

        if ($this->matchesAny($normalized, ['most points scored in a game', 'single game', 'highest scoring game', 'most points', 'game'])) {
          return $this->buildIntentPayload(
            IntentType::SingleGameScoring,
            $question,
            $this->extractMetric($normalized) ?? 'points',
            'single_game',
            $this->extractLimit($normalized) ?? 10,
            'NBA'
          );
        }

        if ($this->matchesAny($normalized, ['top', 'scorer', 'ranking', 'leaders', 'all time', 'best', 'leading'])) {
            // Determine metric (points, rebounds, assists, etc.)
            $metric = $this->extractMetric($normalized) ?? 'points';
            // Extract season year or range
            $seasonYear = $this->extractSeasonYear($question);
            // Build structured query for ranking query
            return $this->buildIntentPayload(
                IntentType::RankingQuery,
                $question,
                $metric,
                $seasonYear ? 'season' : 'all_time',
                $this->extractLimit($normalized) ?? 10,
                'NBA'
            );
        }

        if ($this->matchesAny($normalized, ['won the championship', 'nba championship', 'who won in'])) {
            return $this->buildIntentPayload(IntentType::ChampionshipQuery, $question, 'points', 'season', 1, 'NBA');
        }

        if ($this->matchesAny($normalized, ['head to head', 'h2h'])) {
            return $this->buildIntentPayload(IntentType::HeadToHead, $question, null, null, 10, 'NBA');
        }

        if ($this->matchesAny($normalized, ['team history', 'history of', 'team info', 'tell me about'])) {
          return $this->buildEntityIntent(IntentType::TeamInfo, $question);
        }

        if ($this->matchesAny($normalized, ['who is', 'player bio', 'player info'])) {
          return $this->buildEntityIntent(IntentType::PlayerInfo, $question);
        }

        if ($this->matchesAny($normalized, ['compare', 'vs', 'versus'])) {
          return $this->buildIntentPayload(IntentType::ComparisonQuery, $question, null, null, 2, 'NBA');
        }

        if ($this->matchesAny($normalized, ['mvp', 'rookie of the year', 'defensive player of the year', 'award winners', 'awards'])) {
            return $this->buildIntentPayload(IntentType::AwardQuery, $question, null, null, 10, 'NBA');
        }

        if ($this->matchesAny($normalized, ['rule', 'rules', 'explain the aba nba merger', 'merger', 'historical'])) {
            return $this->buildIntentPayload(IntentType::HistoricalEvent, $question, null, null, 10, 'NBA');
        }

        if ($this->matchesAny($normalized, ['season stats', 'stats in', '2024', '2023', '2022'])) {
            return $this->buildIntentPayload(IntentType::SeasonStats, $question, $this->extractMetric($normalized) ?? 'points', 'season', $this->extractLimit($normalized) ?? 10, 'NBA');
        }

        if ($this->matchesAny($normalized, ['rule explanation', 'how does', 'what is the rule'])) {
            return $this->buildIntentPayload(IntentType::RuleExplanation, $question, null, null, 10, 'NBA');
        }

        return null;
    }

    private function extractSeasonYear(string $question): ?int
    {
        // First check for season range like "2022-2023"
        if (preg_match('/(\d{4})\s*-\s*(\d{2}|\d{4})/', $question, $matches)) {
            $startYear = (int) $matches[1];
            $endStr = $matches[2];
            // Handle both "2022-23" and "2022-2023" formats
            $endYear = strlen($endStr) === 2 ? (int) substr($matches[1], 0, 2) . $endStr : (int) $endStr;
            
            // Return start year for backward compatibility, but we could also return an array
            // For now, return the start year to maintain compatibility with existing code
            return ($startYear >= 1900 && $startYear <= 2100) ? $startYear : null;
        }
        
        // Fall back to single year extraction
        if (preg_match_all('/\d{4}/', $question, $matches) && !empty($matches[0])) {
            $year = (int) $matches[0][0];
            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }
        
        return null;
    }

    private function extractLimit(string $question): ?int
    {
      if (preg_match('/\btop\s+(\d+)\b/i', $question, $matches)) {
        return (int) $matches[1];
      }

      return null;
    }

    private function normalizeQuestion(string $question): string
    {
      return strtolower(trim(str_replace(['-', '_', '?', '.', ','], ' ', $question)));
    }

    private function matchesAny(string $normalizedQuestion, array $needles): bool
    {
      foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($normalizedQuestion, $needle)) {
          return true;
        }
      }

      return false;
    }

private function extractMetric(string $normalizedQuestion): ?string
    {
      if (preg_match('/\brebound(er|ing)?s?\b/', $normalizedQuestion)) return 'rebounds';
      if (preg_match('/\bassists?\b/', $normalizedQuestion)) return 'assists';
      if (preg_match('/\bsteals?\b/', $normalizedQuestion)) return 'steals';
      if (preg_match('/\bblocks?\b/', $normalizedQuestion)) return 'blocks';
      if (preg_match('/\bscorers?\b/', $normalizedQuestion)) return 'points';
      if (preg_match('/\bpoints?\b/', $normalizedQuestion)) return 'points';
      
      return null;
    }

    private function buildIntentPayload(IntentType $intent, string $question, ?string $metric, ?string $period, int $limit, string $competition): array
    {
      return [
        'intent' => $intent->value,
        'entities' => [
          'player_name' => null,
          'team_name' => null,
          'season_year' => $this->extractSeasonYear($question),
          'competition' => $competition,
        ],
        'constraints' => [
          'metric' => $metric,
          'limit' => $limit,
          'sort' => 'desc',
          'period' => $period,
        ],
      ];
    }

    private function buildEntityIntent(IntentType $intent, string $question): array
    {
      return [
        'intent' => $intent->value,
        'entities' => [
          'player_name' => $this->extractEntityName($question),
          'team_name' => $this->extractEntityName($question),
          'season_year' => $this->extractSeasonYear($question),
          'competition' => 'NBA',
        ],
        'constraints' => [
          'metric' => null,
          'limit' => 10,
          'sort' => 'desc',
          'period' => null,
        ],
      ];
    }

    private function extractEntityName(string $question): ?string
    {
      $words = preg_split('/\s+/', trim($question));
      if (!$words || count($words) < 2) {
        return null;
      }

      return implode(' ', array_slice($words, -2));
    }
}
