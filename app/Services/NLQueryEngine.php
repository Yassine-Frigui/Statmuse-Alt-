<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NLQueryEngine
{
    private const MIN_GAMES = 20;

    private array $schema;

    private array $allowedTables = [
        'players', 'teams', 'seasons', 'games', 'game_player_stats',
        'player_season_stats', 'championships', 'awards', 'player_awards',
        'corpus_entries',
    ];

    private array $aggregateFunctions = ['SUM', 'AVG', 'COUNT', 'MIN', 'MAX', 'COALESCE', 'NULLIF', 'ROUND'];

    private array $trace = [];

    public function __construct(
        private GeminiService $gemini
    ) {
        $this->schema = $this->loadSchema();
    }

    public function ask(string $question): array
    {
        $this->trace = [
            'question' => $question,
            'pipeline' => 'local',
            'steps' => [],
        ];

        $result = $this->tryGemini($question);
        if ($result !== null) {
            return $result;
        }

        Log::info('NLQueryEngine: using local query builder');
        return $this->askLocally($question);
    }

    public function getTrace(): array
    {
        return $this->trace;
    }

    private function tryGemini(string $question): ?array
    {
        $this->trace['pipeline'] = 'gemini';
        $this->trace['steps'][] = ['step' => 'gemini_attempt', 'status' => 'started'];

        try {
            $prompt = $this->buildPrompt($question);
            $this->trace['steps'][] = [
                'step' => 'gemini_prompt',
                'prompt_sent' => $prompt,
            ];

            $raw = $this->gemini->generateContent($prompt);
            $result = json_decode($raw, true);

            $this->trace['steps'][] = [
                'step' => 'gemini_response',
                'raw_response' => $raw,
                'parsed' => $result,
            ];

            if (!is_array($result) || empty($result['query']['from'])) {
                $this->trace['steps'][] = [
                    'step' => 'gemini_fallback',
                    'reason' => 'Response did not contain valid query',
                ];
                return null;
            }

            $this->trace['steps'][] = [
                'step' => 'executing_query',
                'query_structure' => $result['query'],
            ];

            $data = $this->executeQuery($result['query']);
            $reply = $this->buildReply($result, $data);

            $this->trace['steps'][] = [
                'step' => 'complete',
                'row_count' => count($data),
            ];

            return [
                'reply' => $reply,
                'data' => $data,
                'intent' => $result['intent'] ?? 'general',
                'entities' => $this->extractEntities($result),
            ];
        } catch (\Throwable $e) {
            Log::warning('NLQueryEngine: Gemini failed, falling back to local', [
                'error' => $e->getMessage(),
            ]);
            $this->trace['steps'][] = [
                'step' => 'gemini_error',
                'error' => $e->getMessage(),
            ];
            $this->trace['pipeline'] = 'local_fallback';
            return null;
        }
    }

    private function buildPrompt(string $question): string
    {
        $schema = $this->formatSchema();

        return <<<PROMPT
You are an NBA analyst with full SQL read access to a MySQL database.
Given a user's basketball question, determine exactly what data to retrieve
and return a structured query and a natural-language answer.

{$schema}

---
USER QUESTION: {$question}
---

Respond with ONLY valid JSON. No markdown fences. No extra text.

{
  "intent": "short_label_describing_intent",
  "entities": {
    "players": ["identified_player_names"],
    "teams": ["identified_team_names"],
    "season": "season_year_or_null",
    "metrics": ["relevant_metrics"]
  },
  "explanation": "One sentence on how you'll answer",
  "query": {
    "from": "primary_table_name",
    "joins": [
      {
        "type": "inner|left",
        "table": "table_to_join",
        "left": "current_table.column",
        "op": "=",
        "right": "joined_table.column"
      }
    ],
    "columns": [
      {"expr": "table.column_or_expression", "alias": "output_name"}
    ],
    "where": [
      {"col": "table.column", "op": "=|>|<|>=|<=|LIKE|IN|!=", "val": "value"}
    ],
    "order_by": [
      {"expr": "alias_or_column", "dir": "ASC|DESC"}
    ],
    "group_by": ["table.column"],
    "limit": 10
  },
  "answer": "Natural-language answer template using the results"
}

RULES:
1. Use ONLY these tables and columns from the schema
2. For computed columns, use SQL expressions with table.column notation
   Examples: points / games_played, SUM(points), AVG(rebounds), fg_pct * 100
3. "Efficiency" usually means field goal percentage (fg_pct).
   Sort by FG% descending for efficiency queries.
   Show PPG alongside FG% for context.
   ALWAYS require PPG >= 10 for efficiency/FG% queries to ensure volume.
4. All WHERE values must be literal strings or numbers
5. Use table.column notation everywhere (not just column names)
6. For head-to-head queries, use games table joined to teams
7. For "who won championship" use championships table
8. For player/team lookup, search by first_name, last_name, or name/abbreviation
9. Prefer player_season_stats for season-level stats (totals over full season)
10. Prefer game_player_stats for single-game queries
11. Limit results to reasonable number (default 10)
12. For efficiency, FG%, or any stat leaderboard: ALWAYS filter
    WHERE player_season_stats.games_played >= 20
    to exclude players with too few games to be meaningful.
13. "Volume scorer" or "Volume shooter" means high PPG (points per game).
    Sort by PPG descending and show FG% for context.
    Require PPG >= 10 as a minimum threshold.
PROMPT;
    }

    public function askLocally(string $question): array
    {
        $questionLower = strtolower(trim($question));

        $this->trace['steps'][] = ['step' => 'extracting_year'];
        $year = $this->extractYear($question);
        $this->trace['steps'][] = ['step' => 'year_extracted', 'year' => $year];

        $this->trace['steps'][] = ['step' => 'resolving_entities'];
        $entities = $this->resolveEntities($question);
        $this->trace['steps'][] = [
            'step' => 'entities_resolved',
            'players' => array_map(fn($p) => $p['name'], $entities['players']),
            'teams' => array_map(fn($t) => $t['full_name'], $entities['teams']),
        ];

        $this->trace['steps'][] = ['step' => 'detecting_intent'];
        $intent = $this->detectIntent($questionLower, $entities);
        $this->trace['steps'][] = [
            'step' => 'intent_detected',
            'type' => $intent['type'],
            'table' => $intent['table'],
        ];

        $this->trace['steps'][] = [
            'step' => 'building_query',
            'intent_type' => $intent['type'],
        ];
        $query = $this->buildQuery($intent, $entities, $questionLower);

        $sql = $this->buildSqlString($query);

        $this->trace['steps'][] = [
            'step' => 'query_built',
            'query_structure' => $query,
            'sql' => $sql,
        ];

        $data = $this->executeQuery($query);
        $this->trace['steps'][] = [
            'step' => 'query_executed',
            'row_count' => count($data),
        ];

        $metric = $this->detectMetric($questionLower);
        $reply = $this->buildLocalReply($intent, $entities, $data, $metric);

        $this->trace['steps'][] = ['step' => 'complete'];

        return [
            'reply' => $reply,
            'data' => $data,
            'intent' => $intent['type'],
            'entities' => $entities['labels'],
        ];
    }

    private function extractYear(string $question): ?int
    {
        if (preg_match('/(\d{4})\s*-\s*(\d{2}|\d{4})/', $question, $m)) {
            $start = (int) $m[1];
            if ($start >= 1900 && $start <= 2100) return $start;
        }
        if (preg_match('/\b(19|20)\d{2}\b/', $question, $m)) {
            $year = (int) $m[0];
            if ($year >= 1900 && $year <= 2100) return $year;
        }
        if (preg_match('/\b\d{4}\b/', $question, $m)) {
            return (int) $m[0];
        }
        return null;
    }

    private function resolveEntities(string $question): array
    {
        $players = [];
        $teams = [];
        $labels = [];

        $tokens = preg_split('/[\s,;?!.]+/', trim($question));
        $tokens = array_filter($tokens, fn($t) => strlen($t) > 1);
        $tokens = array_values($tokens);

        $twoGrams = [];
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $twoGrams[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }
        $threeGrams = [];
        for ($i = 0; $i < count($tokens) - 2; $i++) {
            $threeGrams[] = $tokens[$i] . ' ' . $tokens[$i + 1] . ' ' . $tokens[$i + 2];
        }

        $allNames = array_merge($threeGrams, $twoGrams, $tokens);
        $allNames = array_unique($allNames);

        foreach ($allNames as $name) {
            if (count($players) >= 3) break;

            $player = DB::table('players')
                ->where(DB::raw('LOWER(first_name)'), strtolower($name))
                ->orWhere(DB::raw('LOWER(last_name)'), strtolower($name))
                ->orWhere(DB::raw("LOWER(CONCAT(first_name, ' ', last_name))"), strtolower($name))
                ->select('id', 'first_name', 'last_name')
                ->first();

            if ($player && !isset($players[$player->id])) {
                $players[$player->id] = [
                    'id' => $player->id,
                    'name' => $player->first_name . ' ' . $player->last_name,
                    'first_name' => $player->first_name,
                    'last_name' => $player->last_name,
                ];
            }
        }

        foreach ($allNames as $name) {
            if (count($teams) >= 2) break;

            $team = DB::table('teams')
                ->where(DB::raw('LOWER(name)'), strtolower($name))
                ->orWhere(DB::raw('LOWER(city)'), strtolower($name))
                ->orWhere(DB::raw('LOWER(abbreviation)'), strtolower($name))
                ->select('id', 'name', 'city', 'abbreviation')
                ->first();

            if ($team && !isset($teams[$team->id])) {
                $teams[$team->id] = [
                    'id' => $team->id,
                    'full_name' => $team->city . ' ' . $team->name,
                    'abbreviation' => $team->abbreviation,
                ];
            }
        }

        $players = array_values($players);
        $teams = array_values($teams);

        foreach ($players as $p) {
            $labels[] = $p['name'];
        }
        foreach ($teams as $t) {
            $labels[] = $t['full_name'];
        }

        return [
            'players' => $players,
            'teams' => $teams,
            'labels' => $labels,
            'year' => $this->extractYear($question),
        ];
    }

    private function detectIntent(string $questionLower, array $entities): array
    {
        $playerCount = count($entities['players']);
        $teamCount = count($entities['teams']);

        if (preg_match('/\b(championship|finals|won the|title|ring|champion)\b/i', $questionLower)) {
            return ['type' => 'championship', 'table' => 'championships'];
        }

        if ($playerCount >= 2 || preg_match('/\b(compare|vs|versus|head.to.head|h2h)\b/i', $questionLower)) {
            return ['type' => 'comparison', 'table' => 'player_season_stats'];
        }

        if ($playerCount >= 1 && preg_match('/\b(stats?|averag|scor\w*|points?|rebound|assist|steal|block|efficien\w*|fg|shoot|per|season|career)\b/i', $questionLower)) {
            return ['type' => 'player_stats', 'table' => 'player_season_stats'];
        }

        if ($playerCount >= 1) {
            return ['type' => 'player_info', 'table' => 'players'];
        }

        if ($teamCount >= 2 || preg_match('/\b(compare|vs|versus)\b.*\b(team|franchise)\b/i', $questionLower)) {
            return ['type' => 'team_comparison', 'table' => 'teams'];
        }

        if ($teamCount >= 1) {
            return ['type' => 'team_info', 'table' => 'teams'];
        }

        if (preg_match('/\b(award|mvp|rookie|dpoy|player of the year|all.star|all.nba)\b/i', $questionLower)) {
            return ['type' => 'awards', 'table' => 'player_awards'];
        }

        if (preg_match('/\b(scor\w*|points?|rebounds?|assists?|steals?|blocks?|stats?|efficien\w*|leader|ranking|averag|top|best|ppg|rpg|apg|per)\b/i', $questionLower)) {
            return ['type' => 'leaders', 'table' => 'player_season_stats'];
        }

        if (preg_match('/\b(team|franchise|history|info|about)\b/i', $questionLower)) {
            return ['type' => 'team_info', 'table' => 'teams'];
        }

        return ['type' => 'overview', 'table' => 'player_season_stats'];
    }

    private function buildQuery(array $intent, array $entities, string $questionLower): array
    {
        $method = match ($intent['type']) {
            'championship' => 'buildChampionshipQuery',
            'comparison' => 'buildComparisonQuery',
            'player_stats' => 'buildPlayerStatsQuery',
            'player_info' => 'buildPlayerInfoQuery',
            'team_comparison' => 'buildTeamComparisonQuery',
            'team_info' => 'buildTeamInfoQuery',
            'awards' => 'buildAwardsQuery',
            'leaders' => 'buildLeadersQuery',
            default => 'buildOverviewQuery',
        };

        return $this->$method($entities, $questionLower);
    }

    private function buildLeadersQuery(array $entities, string $questionLower): array
    {
        $metric = $this->detectMetric($questionLower);
        $this->trace['steps'][] = ['step' => 'metric_detected', 'metric' => $metric ?? 'none'];
        $year = $entities['year'] ?? $this->extractYear($questionLower);
        $limit = $this->extractLimit($questionLower);

        $orderCol = match ($metric) {
            'rebounds' => 'total_rebounds',
            'assists' => 'total_assists',
            'steals' => 'total_steals',
            'blocks' => 'total_blocks',
            'fg_pct' => 'fg_pct',
            'efficiency' => 'fg_pct',
            'volume' => 'ppg',
            'points' => 'total_points',
            default => 'total_points',
        };

        $columns = [
            ['expr' => 'players.first_name', 'alias' => 'first_name'],
            ['expr' => 'players.last_name', 'alias' => 'last_name'],
            ['expr' => 'players.position', 'alias' => 'position'],
        ];

        if ($metric === 'efficiency') {
            $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        } elseif ($metric === 'volume') {
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'];
            $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            $columns[] = ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        } elseif ($metric === 'fg_pct') {
            $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            $columns[] = ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        } elseif ($metric === 'points') {
            $columns[] = ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'];
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'];
            $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        } elseif ($metric !== null) {
            $columns[] = ['expr' => "SUM(player_season_stats.{$metric})", 'alias' => $orderCol];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        } else {
            $columns[] = ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'];
            $columns[] = ['expr' => 'SUM(player_season_stats.rebounds)', 'alias' => 'total_rebounds'];
            $columns[] = ['expr' => 'SUM(player_season_stats.assists)', 'alias' => 'total_assists'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
        }

        $query = [
            'from' => 'player_season_stats',
            'joins' => [
                ['type' => 'inner', 'table' => 'players', 'left' => 'player_season_stats.player_id', 'op' => '=', 'right' => 'players.id'],
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'player_season_stats.season_id', 'op' => '=', 'right' => 'seasons.id'],
            ],
            'columns' => $columns,
            'where' => [],
            'order_by' => [['expr' => $orderCol, 'dir' => 'DESC']],
            'group_by' => ['players.id', 'players.first_name', 'players.last_name', 'players.position'],
            'limit' => $limit,
        ];

        if ($year) {
            $query['where'][] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        } elseif (!$entities['year']) {
            $latestSeason = DB::table('seasons')->orderByDesc('year')->first();
            if ($latestSeason) {
                $query['where'][] = ['col' => 'seasons.id', 'op' => '=', 'val' => $latestSeason->id];
            }
        }

        $query['where'][] = ['col' => 'player_season_stats.games_played', 'op' => '>=', 'val' => self::MIN_GAMES];

        if (in_array($metric, ['efficiency', 'fg_pct', 'volume'], true)) {
            $query['having'][] = [
                'expr' => 'SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0)',
                'op' => '>=',
                'val' => 10,
            ];
        }

        return $query;
    }

    private function buildPlayerStatsQuery(array $entities, string $questionLower): array
    {
        $metric = $this->detectMetric($questionLower);
        $year = $entities['year'] ?? $this->extractYear($questionLower);

        $columns = [
            ['expr' => 'players.first_name', 'alias' => 'first_name'],
            ['expr' => 'players.last_name', 'alias' => 'last_name'],
            ['expr' => 'players.position', 'alias' => 'position'],
            ['expr' => 'seasons.year', 'alias' => 'season'],
        ];

        if ($metric === 'efficiency') {
            $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'];
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.rebounds) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'rpg'];
            $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.assists) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'apg'];
        } elseif ($metric !== null) {
            $columns[] = ['expr' => "SUM(player_season_stats.{$metric})", 'alias' => 'total'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
            if ($metric === 'points') {
                $columns[] = ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'];
                $columns[] = ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'];
            }
        } else {
            $columns[] = ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'];
            $columns[] = ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'];
            $columns[] = ['expr' => 'SUM(player_season_stats.rebounds)', 'alias' => 'total_rebounds'];
            $columns[] = ['expr' => 'SUM(player_season_stats.assists)', 'alias' => 'total_assists'];
        }

        $where = [];
        $groupBy = ['players.id', 'players.first_name', 'players.last_name', 'players.position', 'seasons.year'];

        if (!empty($entities['players'])) {
            $playerIds = array_map(fn($p) => $p['id'], $entities['players']);
            $where[] = ['col' => 'players.id', 'op' => 'IN', 'val' => $playerIds];
        }

        if ($year) {
            $where[] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        }

        if (empty($entities['players'])) {
            $where[] = ['col' => 'player_season_stats.games_played', 'op' => '>=', 'val' => self::MIN_GAMES];
        }

        return [
            'from' => 'player_season_stats',
            'joins' => [
                ['type' => 'inner', 'table' => 'players', 'left' => 'player_season_stats.player_id', 'op' => '=', 'right' => 'players.id'],
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'player_season_stats.season_id', 'op' => '=', 'right' => 'seasons.id'],
            ],
            'columns' => $columns,
            'where' => $where,
            'order_by' => [['expr' => 'seasons.year', 'dir' => 'DESC']],
            'group_by' => $groupBy,
            'limit' => 20,
        ];
    }

    private function buildPlayerInfoQuery(array $entities, string $questionLower): array
    {
        $where = [];
        if (!empty($entities['players'])) {
            $playerIds = array_map(fn($p) => $p['id'], $entities['players']);
            $where[] = ['col' => 'players.id', 'op' => 'IN', 'val' => $playerIds];
        } else {
            $tokens = preg_split('/[\s,;?!.]+/', trim($questionLower));
            $significant = array_filter($tokens, fn($t) => strlen($t) > 2);
            $significant = array_values($significant);
            if (!empty($significant)) {
                $where[] = ['col' => 'players.last_name', 'op' => 'LIKE', 'val' => '%' . end($significant) . '%'];
            }
        }

        return [
            'from' => 'players',
            'joins' => [],
            'columns' => [
                ['expr' => 'players.id', 'alias' => 'id'],
                ['expr' => 'players.first_name', 'alias' => 'first_name'],
                ['expr' => 'players.last_name', 'alias' => 'last_name'],
                ['expr' => 'players.position', 'alias' => 'position'],
                ['expr' => 'players.height', 'alias' => 'height'],
                ['expr' => 'players.weight', 'alias' => 'weight'],
                ['expr' => 'players.college', 'alias' => 'college'],
                ['expr' => 'players.drafted_year', 'alias' => 'drafted_year'],
            ],
            'where' => $where,
            'order_by' => [['expr' => 'players.last_name', 'dir' => 'ASC']],
            'group_by' => [],
            'limit' => 10,
        ];
    }

    private function buildChampionshipQuery(array $entities, string $questionLower): array
    {
        $year = $entities['year'] ?? $this->extractYear($questionLower);

        $where = [];
        if ($year) {
            $where[] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        }

        return [
            'from' => 'championships',
            'joins' => [
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'championships.season_id', 'op' => '=', 'right' => 'seasons.id'],
                ['type' => 'inner', 'table' => 'teams as champion', 'left' => 'championships.champion_team_id', 'op' => '=', 'right' => 'champion.id'],
                ['type' => 'inner', 'table' => 'teams as runner_up', 'left' => 'championships.runner_up_team_id', 'op' => '=', 'right' => 'runner_up.id'],
                ['type' => 'left', 'table' => 'players as mvp', 'left' => 'championships.mvp_player_id', 'op' => '=', 'right' => 'mvp.id'],
            ],
            'columns' => [
                ['expr' => 'seasons.year', 'alias' => 'year'],
                ['expr' => 'champion.name', 'alias' => 'champion'],
                ['expr' => 'runner_up.name', 'alias' => 'runner_up'],
                ['expr' => "CONCAT(mvp.first_name, ' ', mvp.last_name)", 'alias' => 'finals_mvp'],
                ['expr' => 'championships.result_label', 'alias' => 'result'],
            ],
            'where' => $where,
            'order_by' => [['expr' => 'seasons.year', 'dir' => 'DESC']],
            'group_by' => [],
            'limit' => 10,
        ];
    }

    private function buildComparisonQuery(array $entities, string $questionLower): array
    {
        $year = $entities['year'] ?? $this->extractYear($questionLower);
        $metric = $this->detectMetric($questionLower);

        $playerIds = array_map(fn($p) => $p['id'], $entities['players'] ?? []);
        if (count($playerIds) < 2) {
            $playerIds = array_slice($playerIds, 0, 2);
        }

        $columns = [
            ['expr' => 'players.first_name', 'alias' => 'first_name'],
            ['expr' => 'players.last_name', 'alias' => 'last_name'],
            ['expr' => 'seasons.year', 'alias' => 'season'],
            ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'],
            ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'],
            ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'],
            ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'],
            ['expr' => 'SUM(player_season_stats.rebounds)', 'alias' => 'total_rebounds'],
            ['expr' => 'SUM(player_season_stats.assists)', 'alias' => 'total_assists'],
        ];

        $where = [['col' => 'players.id', 'op' => 'IN', 'val' => $playerIds]];

        if ($year) {
            $where[] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        }

        return [
            'from' => 'player_season_stats',
            'joins' => [
                ['type' => 'inner', 'table' => 'players', 'left' => 'player_season_stats.player_id', 'op' => '=', 'right' => 'players.id'],
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'player_season_stats.season_id', 'op' => '=', 'right' => 'seasons.id'],
            ],
            'columns' => $columns,
            'where' => $where,
            'order_by' => [['expr' => 'players.last_name', 'dir' => 'ASC'], ['expr' => 'seasons.year', 'dir' => 'DESC']],
            'group_by' => ['players.id', 'players.first_name', 'players.last_name', 'seasons.year'],
            'limit' => 50,
        ];
    }

    private function buildTeamInfoQuery(array $entities, string $questionLower): array
    {
        $where = [];
        if (!empty($entities['teams'])) {
            $teamIds = array_map(fn($t) => $t['id'], $entities['teams']);
            $where[] = ['col' => 'teams.id', 'op' => 'IN', 'val' => $teamIds];
        }

        return [
            'from' => 'teams',
            'joins' => [],
            'columns' => [
                ['expr' => 'teams.name', 'alias' => 'name'],
                ['expr' => 'teams.city', 'alias' => 'city'],
                ['expr' => 'teams.abbreviation', 'alias' => 'abbreviation'],
                ['expr' => 'teams.conference', 'alias' => 'conference'],
                ['expr' => 'teams.division', 'alias' => 'division'],
                ['expr' => 'teams.arena', 'alias' => 'arena'],
                ['expr' => 'teams.founded_year', 'alias' => 'founded_year'],
            ],
            'where' => $where,
            'order_by' => [['expr' => 'teams.name', 'dir' => 'ASC']],
            'group_by' => [],
            'limit' => 10,
        ];
    }

    private function buildTeamComparisonQuery(array $entities, string $questionLower): array
    {
        $year = $entities['year'] ?? $this->extractYear($questionLower);
        $teamIds = array_map(fn($t) => $t['id'], $entities['teams'] ?? []);

        $where = [];
        if (!empty($teamIds)) {
            $where[] = ['col' => 'home_team_id', 'op' => 'IN', 'val' => $teamIds];
        }
        if ($year) {
            $where[] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        }

        return [
            'from' => 'games',
            'joins' => [
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'games.season_id', 'op' => '=', 'right' => 'seasons.id'],
                ['type' => 'inner', 'table' => 'teams as home', 'left' => 'games.home_team_id', 'op' => '=', 'right' => 'home.id'],
                ['type' => 'inner', 'table' => 'teams as away', 'left' => 'games.away_team_id', 'op' => '=', 'right' => 'away.id'],
            ],
            'columns' => [
                ['expr' => 'home.name', 'alias' => 'home_team'],
                ['expr' => 'away.name', 'alias' => 'away_team'],
                ['expr' => 'games.home_score', 'alias' => 'home_score'],
                ['expr' => 'games.away_score', 'alias' => 'away_score'],
                ['expr' => 'games.date', 'alias' => 'date'],
                ['expr' => 'games.stage', 'alias' => 'stage'],
            ],
            'where' => $where,
            'order_by' => [['expr' => 'games.date', 'dir' => 'DESC']],
            'group_by' => [],
            'limit' => 20,
        ];
    }

    private function buildAwardsQuery(array $entities, string $questionLower): array
    {
        $year = $entities['year'] ?? $this->extractYear($questionLower);

        $where = [];
        if ($year) {
            $where[] = ['col' => 'seasons.year', 'op' => '=', 'val' => $year];
        }

        $awardKeywords = ['mvp', 'rookie of the year', 'defensive player of the year', 'sixth man', 'most improved'];
        foreach ($awardKeywords as $keyword) {
            if (str_contains($questionLower, $keyword)) {
                $where[] = ['col' => 'awards.name', 'op' => 'LIKE', 'val' => '%' . $keyword . '%'];
                break;
            }
        }

        return [
            'from' => 'player_awards',
            'joins' => [
                ['type' => 'inner', 'table' => 'players', 'left' => 'player_awards.player_id', 'op' => '=', 'right' => 'players.id'],
                ['type' => 'inner', 'table' => 'awards', 'left' => 'player_awards.award_id', 'op' => '=', 'right' => 'awards.id'],
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'player_awards.season_id', 'op' => '=', 'right' => 'seasons.id'],
            ],
            'columns' => [
                ['expr' => "CONCAT(players.first_name, ' ', players.last_name)", 'alias' => 'player'],
                ['expr' => 'awards.name', 'alias' => 'award'],
                ['expr' => 'seasons.year', 'alias' => 'season'],
            ],
            'where' => $where,
            'order_by' => [['expr' => 'seasons.year', 'dir' => 'DESC']],
            'group_by' => [],
            'limit' => 20,
        ];
    }

    private function buildOverviewQuery(array $entities, string $questionLower): array
    {
        $latestSeason = DB::table('seasons')->orderByDesc('year')->first();

        return [
            'from' => 'player_season_stats',
            'joins' => [
                ['type' => 'inner', 'table' => 'players', 'left' => 'player_season_stats.player_id', 'op' => '=', 'right' => 'players.id'],
                ['type' => 'inner', 'table' => 'seasons', 'left' => 'player_season_stats.season_id', 'op' => '=', 'right' => 'seasons.id'],
            ],
            'columns' => [
                ['expr' => 'players.first_name', 'alias' => 'first_name'],
                ['expr' => 'players.last_name', 'alias' => 'last_name'],
                ['expr' => 'players.position', 'alias' => 'position'],
                ['expr' => 'SUM(player_season_stats.points)', 'alias' => 'total_points'],
                ['expr' => 'SUM(player_season_stats.games_played)', 'alias' => 'gp'],
                ['expr' => 'ROUND(SUM(player_season_stats.points) * 1.0 / NULLIF(SUM(player_season_stats.games_played), 0), 1)', 'alias' => 'ppg'],
                ['expr' => 'ROUND(AVG(player_season_stats.fg_pct) * 100, 1)', 'alias' => 'fg_pct'],
            ],
            'where' => array_merge(
                $latestSeason ? [['col' => 'seasons.id', 'op' => '=', 'val' => $latestSeason->id]] : [],
                [['col' => 'player_season_stats.games_played', 'op' => '>=', 'val' => self::MIN_GAMES]]
            ),
            'order_by' => [['expr' => 'total_points', 'dir' => 'DESC']],
            'group_by' => ['players.id', 'players.first_name', 'players.last_name', 'players.position'],
            'limit' => 10,
        ];
    }

    private function detectMetric(string $questionLower): ?string
    {
        if (preg_match('/\b(volume\s*(scor|shoot)|high.volume|volume\s+leader)\b/i', $questionLower)) return 'volume';
        if (preg_match('/\befficien|\bper\b/i', $questionLower)) return 'efficiency';
        if (preg_match('/\brebound/i', $questionLower)) return 'rebounds';
        if (preg_match('/\bassist/i', $questionLower)) return 'assists';
        if (preg_match('/\bsteal/i', $questionLower)) return 'steals';
        if (preg_match('/\bblock/i', $questionLower)) return 'blocks';
        if (preg_match('/\bfg|field goal|shooting|fg_pct/i', $questionLower)) return 'fg_pct';
        if (preg_match('/\b(points?\s+(per|avg|leader|total|season|career)|scor\w*|ppg)\b/i', $questionLower)) return 'points';
        return null;
    }

    private function extractLimit(string $questionLower): int
    {
        if (preg_match('/top\s+(\d+)/i', $questionLower, $m)) return (int) $m[1];
        if (preg_match('/\b(\d+)\s*(best|top|leading)/i', $questionLower, $m)) return (int) $m[1];
        return 10;
    }

    private function buildLocalReply(array $intent, array $entities, array $data, ?string $metric = null): string
    {
        if (empty($data)) {
            return "No data found. Try asking about players, teams, seasons, championships, or leaders in specific stats.";
        }

        $headers = array_keys($data[0]);

        $title = match ($intent['type']) {
            'leaders' => $this->leadersTitle($metric, $headers),
            'player_stats' => 'Player Stats',
            'player_info' => 'Player Information',
            'championship' => 'NBA Championships',
            'comparison' => 'Player Comparison',
            'team_info' => 'Team Information',
            'team_comparison' => 'Head-to-Head Games',
            'awards' => 'NBA Awards',
            default => 'NBA Data Summary',
        };

        $reply = "**{$title}**\n\n";

        $reply .= '| ' . implode(' | ', array_map(fn($h) => str_replace('_', ' ', ucfirst($h)), $headers)) . " |\n";
        $reply .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";

        foreach ($data as $row) {
            $vals = array_map(fn($h) => $this->formatCell($row[$h] ?? ''), $headers);
            $reply .= '| ' . implode(' | ', $vals) . " |\n";
        }

        if (count($data) > 10) {
            $reply .= "\n*Showing " . count($data) . " results*\n";
        }

        if ($metric !== null) {
            $reply .= "\n*Sorted by: " . strtoupper($metric) . "*\n";
        }

        return $reply;
    }

    private function leadersTitle(?string $metric, array $headers): string
    {
        if ($metric === 'efficiency') {
            $hasFgPct = in_array('fg_pct', $headers);
            if ($hasFgPct) return 'Top Players — FG% Efficiency Leaders (min 10 PPG)';
            return 'Top Players — Efficiency';
        }
        if ($metric === 'volume') return 'Top Players — Volume Scorers (PPG Leaders)';
        if ($metric === 'fg_pct') return 'Top Players — Field Goal Percentage (min 10 PPG)';
        if ($metric === 'points') return 'Top Players — Scoring Leaders';
        if ($metric === 'rebounds') return 'Top Players — Rebound Leaders';
        if ($metric === 'assists') return 'Top Players — Assist Leaders';
        if ($metric === 'steals') return 'Top Players — Steal Leaders';
        if ($metric === 'blocks') return 'Top Players — Block Leaders';
        return 'League Leaders';
    }

    private function formatCell(mixed $value): string
    {
        if ($value === null || $value === '') return '-';
        if (is_float($value)) return number_format($value, 1);
        if (is_int($value)) return number_format($value);
        return (string) $value;
    }

    private function buildSqlString(array $query): string
    {
        $cols = [];
        foreach ($query['columns'] ?? [] as $col) {
            $expr = $col['expr'] ?? '*';
            $alias = $col['alias'] ?? null;
            $cols[] = $alias ? "{$expr} AS {$alias}" : $expr;
        }
        if (empty($cols)) $cols[] = '*';

        $sql = "SELECT " . implode(', ', $cols) . " FROM {$query['from']}";

        foreach ($query['joins'] ?? [] as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $sql .= " {$type} JOIN {$join['table']} ON {$join['left']} {$join['op']} {$join['right']}";
        }

        $wheres = [];
        foreach ($query['where'] ?? [] as $cond) {
            $val = is_array($cond['val']) ? '(' . implode(',', array_map(fn($v) => is_string($v) ? "'{$v}'" : $v, $cond['val'])) . ')' : (is_string($cond['val']) ? "'{$cond['val']}'" : $cond['val']);
            $wheres[] = "{$cond['col']} {$cond['op']} {$val}";
        }
        if (!empty($wheres)) $sql .= " WHERE " . implode(' AND ', $wheres);

        if (!empty($query['group_by'])) $sql .= " GROUP BY " . implode(', ', $query['group_by']);

        $havings = [];
        foreach ($query['having'] ?? [] as $cond) {
            $val = is_string($cond['val']) ? "'{$cond['val']}'" : $cond['val'];
            $havings[] = "{$cond['expr']} {$cond['op']} {$val}";
        }
        if (!empty($havings)) $sql .= " HAVING " . implode(' AND ', $havings);

        foreach ($query['order_by'] ?? [] as $ob) {
            $sql .= " ORDER BY {$ob['expr']} {$ob['dir']}";
            break;
        }

        if (isset($query['limit'])) $sql .= " LIMIT {$query['limit']}";

        return $sql;
    }

    private function executeQuery(array $query): array
    {
        $from = $query['from'] ?? null;
        if (!$from || !in_array($from, $this->allowedTables, true)) {
            throw new \InvalidArgumentException("Invalid primary table: " . ($from ?? 'null'));
        }

        $builder = DB::table($from);

        $this->applyJoins($builder, $query['joins'] ?? []);
        $this->applyColumns($builder, $query);
        $this->applyWhere($builder, $query['where'] ?? []);
        $this->applyGroupBy($builder, $query['group_by'] ?? []);
        $this->applyHaving($builder, $query['having'] ?? []);

        $orderBy = $query['order_by'] ?? [];
        if (!empty($orderBy)) {
            $this->applyOrderBy($builder, $orderBy);
        }

        $limit = min((int) ($query['limit'] ?? 10), 100);
        $builder->limit($limit);

        $rows = $builder->get()->toArray();
        return json_decode(json_encode($rows), true);
    }

    private function applyJoins($builder, array $joins): void
    {
        foreach ($joins as $join) {
            $table = $join['table'] ?? null;
            $left = $join['left'] ?? null;
            $right = $join['right'] ?? null;
            $type = strtolower($join['type'] ?? 'inner');
            $op = $join['op'] ?? '=';

            if (!$table || !$left || !$right) continue;
            if (!in_array(explode(' as ', $table)[0], $this->allowedTables, true))
                throw new \InvalidArgumentException("Invalid join table: {$table}");

            $joinMethod = $type === 'left' ? 'leftJoin' : 'join';
            $builder->{$joinMethod}($table, $left, $op, $right);
        }
    }

    private function applyColumns($builder, array $query): void
    {
        $columns = $query['columns'] ?? [];

        if (empty($columns)) {
            $builder->select("{$query['from']}.*");
            return;
        }

        $selects = [];
        foreach ($columns as $col) {
            $expr = $col['expr'] ?? null;
            $alias = $col['alias'] ?? null;
            if (!$expr) continue;

            $validated = $this->validateExpression($expr);
            $select = $alias ? DB::raw("{$validated} as {$alias}") : DB::raw($validated);
            $selects[] = $select;
        }

        if (!empty($selects)) {
            $builder->select($selects);
        }
    }

    private function applyWhere($builder, array $conditions): void
    {
        foreach ($conditions as $cond) {
            $col = $cond['col'] ?? null;
            $op = strtoupper($cond['op'] ?? '=');
            $val = $cond['val'] ?? null;
            if (!$col) continue;

            $validOps = ['=', '>', '<', '>=', '<=', 'LIKE', 'IN', '!=', '<>'];
            if (!in_array($op, $validOps, true)) $op = '=';

            if ($op === 'IN') {
                $val = is_array($val) ? $val : [$val];
                $builder->whereIn($col, $val);
            } elseif ($op === 'LIKE') {
                $builder->where($col, 'LIKE', $val);
            } else {
                $builder->where($col, $op, $val);
            }
        }
    }

    private function applyGroupBy($builder, array $groupBy): void
    {
        foreach ($groupBy as $col) {
            $builder->groupBy($col);
        }
    }

    private function applyHaving($builder, array $conditions): void
    {
        foreach ($conditions as $cond) {
            $expr = $cond['expr'] ?? null;
            $op = strtoupper($cond['op'] ?? '=');
            $val = $cond['val'] ?? null;
            if (!$expr) continue;
            $validOps = ['=', '>', '<', '>=', '<=', '!=', '<>'];
            if (!in_array($op, $validOps, true)) $op = '=';
            $validated = $this->validateExpression($expr);
            if (is_string($val)) {
                $builder->having(DB::raw($validated), $op, $val);
            } else {
                $builder->having(DB::raw($validated), $op, $val);
            }
        }
    }

    private function applyOrderBy($builder, array $orderBy): void
    {
        foreach ($orderBy as $ob) {
            $expr = $ob['expr'] ?? null;
            $dir = strtoupper($ob['dir'] ?? 'ASC');
            if (!$expr) continue;
            $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';
            $builder->orderBy(DB::raw($this->validateExpression($expr)), $dir);
        }
    }

    private function validateExpression(string $expr): string
    {
        $expr = trim($expr);
        if ($expr === '*') return '*';

        if (!preg_match('/^[a-zA-Z0-9_.()+\-*\/,\'\s"`%]+$/', $expr)) {
            throw new \InvalidArgumentException("Invalid characters in expression: {$expr}");
        }

        preg_match_all('/(?<![\'"])[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*(?![\'"])/', $expr, $matches);
        foreach ($matches[0] as $ref) {
            [$table, $column] = explode('.', $ref, 2);
            $baseTable = explode(' as ', $table)[0];
            if (!in_array($baseTable, $this->allowedTables, true)) {
                throw new \InvalidArgumentException("Invalid table: {$ref}");
            }
        }

        preg_match_all('/\b([A-Z_][A-Z0-9_]*)\s*\(/i', $expr, $funcMatches);
        foreach ($funcMatches[1] as $func) {
            if (!in_array(strtoupper($func), $this->aggregateFunctions, true)) {
                throw new \InvalidArgumentException("Invalid function: {$func}");
            }
        }

        return $expr;
    }

    private function buildReply(array $result, array $data): string
    {
        $template = $result['answer'] ?? 'Here are the results:';
        if (empty($data)) return 'No data found for your query.';
        $summary = "\n\n";
        $headers = array_keys($data[0]);
        $summary .= '| ' . implode(' | ', array_map(fn($h) => str_replace('_', ' ', ucfirst($h)), $headers)) . " |\n";
        $summary .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";
        foreach (array_slice($data, 0, 10) as $row) {
            $vals = array_map(fn($h) => $row[$h] ?? '-', $headers);
            $summary .= '| ' . implode(' | ', $vals) . " |\n";
        }
        return $template . $summary;
    }

    private function extractEntities(array $result): array
    {
        $entities = [];
        $e = $result['entities'] ?? [];
        if (!empty($e['players'])) {
            foreach ((array) $e['players'] as $p) $entities[] = $p;
        }
        if (!empty($e['teams'])) {
            foreach ((array) $e['teams'] as $t) $entities[] = $t;
        }
        if (!empty($e['season'])) $entities[] = 'Season: ' . $e['season'];
        if (!empty($e['metrics'])) {
            foreach ((array) $e['metrics'] as $m) $entities[] = ucfirst($m);
        }
        return $entities;
    }

    private function formatSchema(): string
    {
        $lines = ['DATABASE SCHEMA (NBA Stats Database):', ''];
        foreach ($this->schema as $table => $info) {
            $lines[] = "Table: {$table}";
            $lines[] = '  Columns:';
            foreach ($info['columns'] as $col => $type) $lines[] = "    - {$col} ({$type})";
            if (!empty($info['relationships'])) {
                $lines[] = '  Relationships:';
                foreach ($info['relationships'] as $rel) $lines[] = "    - {$rel}";
            }
            $lines[] = '';
        }
        $lines[] = 'COMMON QUERY PATTERNS:';
        $lines[] = '- Player info: players table, filter by first_name/last_name';
        $lines[] = '- Season stats: player_season_stats JOIN players JOIN seasons';
        $lines[] = '- Game stats: game_player_stats JOIN games JOIN players JOIN teams';
        $lines[] = '- Championships: championships JOIN seasons JOIN teams';
        $lines[] = '- Scoring leaders: group by player_id, order by points DESC';
        $lines[] = '- Efficiency: points / games_played (PPG), fg_pct, or both';
        return implode("\n", $lines);
    }

    private function loadSchema(): array
    {
        return [
            'players' => ['columns' => ['id' => 'integer PK', 'first_name' => 'string', 'last_name' => 'string', 'position' => 'string nullable', 'height' => 'string nullable', 'weight' => 'string nullable', 'college' => 'string nullable', 'drafted_year' => 'integer nullable'], 'relationships' => ['HasMany game_player_stats via player_id', 'HasMany player_season_stats via player_id']],
            'teams' => ['columns' => ['id' => 'integer PK', 'name' => 'string', 'city' => 'string', 'abbreviation' => 'string', 'conference' => 'string nullable', 'division' => 'string nullable', 'arena' => 'string nullable', 'founded_year' => 'integer nullable'], 'relationships' => ['HasMany games (as home/away)', 'HasMany championships']],
            'seasons' => ['columns' => ['id' => 'integer PK', 'year' => 'integer', 'label' => 'string', 'start_date' => 'date', 'end_date' => 'date'], 'relationships' => ['HasMany games, player_season_stats, championships']],
            'games' => ['columns' => ['id' => 'integer PK', 'date' => 'date', 'home_team_id' => 'integer', 'away_team_id' => 'integer', 'home_score' => 'integer', 'away_score' => 'integer', 'season_id' => 'integer', 'stage' => 'string'], 'relationships' => ['BelongsTo season, homeTeam, awayTeam']],
            'game_player_stats' => ['columns' => ['id' => 'integer PK', 'game_id' => 'integer', 'player_id' => 'integer', 'team_id' => 'integer', 'points' => 'integer', 'rebounds' => 'integer', 'assists' => 'integer', 'steals' => 'integer', 'blocks' => 'integer', 'fg_pct' => 'float', 'three_pct' => 'float', 'ft_pct' => 'float', 'minutes' => 'string', 'turnovers' => 'integer', 'personal_fouls' => 'integer'], 'relationships' => ['BelongsTo game, player, team']],
            'player_season_stats' => ['columns' => ['id' => 'integer PK', 'player_id' => 'integer', 'team_id' => 'integer', 'season_id' => 'integer', 'games_played' => 'integer', 'points' => 'integer', 'rebounds' => 'integer', 'assists' => 'integer', 'steals' => 'integer', 'blocks' => 'integer', 'fg_pct' => 'float', 'three_pct' => 'float', 'ft_pct' => 'float'], 'relationships' => ['BelongsTo player, team, season']],
            'championships' => ['columns' => ['id' => 'integer PK', 'season_id' => 'integer', 'champion_team_id' => 'integer', 'runner_up_team_id' => 'integer', 'mvp_player_id' => 'integer', 'result_label' => 'string'], 'relationships' => ['BelongsTo season, championTeam, runnerUpTeam, mvpPlayer']],
            'awards' => ['columns' => ['id' => 'integer PK', 'name' => 'string', 'description' => 'text']],
            'player_awards' => ['columns' => ['id' => 'integer PK', 'player_id' => 'integer', 'award_id' => 'integer', 'season_id' => 'integer'], 'relationships' => ['BelongsTo player, award, season']],
            'corpus_entries' => ['columns' => ['id' => 'integer PK', 'title' => 'string', 'content' => 'text', 'category' => 'string', 'tags' => 'json'], 'relationships' => []],
        ];
    }
}
