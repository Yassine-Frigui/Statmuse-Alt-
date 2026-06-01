<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BoxscoreReconstructionService
{
    private Client $client;
    private string $baseUrl = 'https://data.nba.com/data/10s/v2015/json/mobile_teams/nba';

    private array $playerIdCache = [];
    private array $teamIdCache = [];
    private array $gameIdCache = [];

    private int $processed = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private bool $force = false;

    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    private array $deferredAssists = [];
    private array $deferredBlocks = [];
    private array $deferredStealsPids = [];
    private array $reboundCumulative = [];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ],
        ]);

        $this->warmTeamCache();
    }

    public function reconstructAll(): array
    {
        $seasonYears = [2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024];
        $results = [];

        foreach ($seasonYears as $year) {
            $results[$year] = $this->reconstructSeason($year);
            sleep(3);
        }

        return [
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'by_season' => $results,
        ];
    }

    public function reconstructSeason(int $seasonYear): array
    {
        $season = Season::where('year', $seasonYear)->first();
        if (!$season) {
            return ['error' => "Season {$seasonYear} not found"];
        }

        $unmappedCount = Game::where('season_id', $season->id)->whereNull('api_game_id')->count();

        if ($unmappedCount > 0) {
            $schedule = $this->fetchSeasonSchedule($seasonYear);
            if ($schedule->isEmpty()) {
                return ['error' => "No schedule data for {$seasonYear}"];
            }
            $this->mapScheduleToGames($schedule, $seasonYear);
        }

        $games = Game::where('season_id', $season->id)
            ->whereNotNull('api_game_id')
            ->get();

        $result = ['total' => $games->count(), 'processed' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($games as $game) {
            if (!$this->shouldProcessGame($game)) {
                $this->skipped++;
                $result['skipped']++;
                continue;
            }

            try {
                $this->reconstructGame($game);
                $this->processed++;
                $result['processed']++;
                usleep(500000);
            } catch (\Exception $e) {
                $this->errors++;
                $result['errors']++;
                Log::error("Boxscore reconstruction failed for game {$game->id}", [
                    'api_game_id' => $game->api_game_id,
                    'error' => $e->getMessage(),
                ]);

                sleep(2);
            }
        }

        return $result;
    }

    private function shouldProcessGame(Game $game): bool
    {
        return $this->force || $game->gamePlayerStats()->count() === 0;
    }

    public function reconstructGame(Game $game): void
    {
        if (!$game->api_game_id) {
            throw new \RuntimeException("Game {$game->id} has no api_game_id");
        }

        $this->deferredAssists = [];
        $this->deferredBlocks = [];
        $this->deferredStealsPids = [];
        $this->reboundCumulative = [];

        $seasonYear = $game->season?->year;
        if (!$seasonYear) {
            throw new \RuntimeException("Game {$game->id} has no season");
        }

        $pbp = $this->fetchPbp($seasonYear, $game->api_game_id);
        if (!$pbp) {
            throw new \RuntimeException("Failed to fetch PBP for game {$game->api_game_id}");
        }

        $boxscore = $this->parsePbp($pbp);

        if (empty($boxscore)) {
            if ($this->force) {
                $game->gamePlayerStats()->delete();
            }
            return;
        }

        DB::transaction(function () use ($game, $boxscore) {
            if ($this->force) {
                $game->gamePlayerStats()->delete();
            } else {
                $existing = $game->gamePlayerStats()->count();
                if ($existing > 0) {
                    return;
                }
            }

            foreach ($boxscore as $entry) {
                $player = $this->findOrCreatePlayer(
                    $entry['nba_pbp_id'],
                    $entry['first_name'],
                    $entry['last_name']
                );

                if (!$player) {
                    continue;
                }

                $team = $this->findTeamByNbaId($entry['team_nba_id']);
                if (!$team) {
                    continue;
                }

                $stat = new GamePlayerStat();
                $stat->game_id = $game->id;
                $stat->player_id = $player->id;
                $stat->team_id = $team->id;
                $stat->points = $entry['pts'];
                $stat->rebounds = $entry['oreb'] + $entry['dreb'];
                $stat->assists = $entry['ast'];
                $stat->steals = $entry['stl'];
                $stat->blocks = $entry['blk'];
                $stat->minutes = 0;
                $stat->fgm = $entry['fgm'];
                $stat->fga = $entry['fga'];
                $stat->fg3m = $entry['fg3m'];
                $stat->fg3a = $entry['fg3a'];
                $stat->ftm = $entry['ftm'];
                $stat->fta = $entry['fta'];
                $stat->offensive_rebounds = $entry['oreb'];
                $stat->defensive_rebounds = $entry['dreb'];
                $stat->turnovers = $entry['to'];
                $stat->personal_fouls = $entry['pf'];
                $stat->fg_pct = $entry['fga'] > 0 ? round($entry['fgm'] / $entry['fga'], 3) : null;
                $stat->three_pct = $entry['fg3a'] > 0 ? round($entry['fg3m'] / $entry['fg3a'], 3) : null;
                $stat->ft_pct = $entry['fta'] > 0 ? round($entry['ftm'] / $entry['fta'], 3) : null;
                $stat->save();
            }
        });
    }

    private function parsePbp(array $pbp): array
    {
        $playerStats = [];

        if (!isset($pbp['g']['pd'])) {
            return [];
        }

        $this->reboundCumulative = [];
        $teamNbaIds = $this->extractTeamIdsFromPbp($pbp);

        $prevHs = 0;
        $prevVs = 0;

        foreach ($pbp['g']['pd'] as $period) {
            if (!isset($period['pla'])) {
                continue;
            }

            foreach ($period['pla'] as $event) {
                $hs = (int)($event['hs'] ?? $prevHs);
                $vs = (int)($event['vs'] ?? $prevVs);
                $scorePts = ($hs - $prevHs) + ($vs - $prevVs);
                $prevHs = $hs;
                $prevVs = $vs;

                $pid = $event['pid'] ?? 0;
                $tid = $event['tid'] ?? 0;
                if (!$pid || !$tid) {
                    continue;
                }

                if (!isset($playerStats[$pid])) {
                    $playerStats[$pid] = [
                        'nba_pbp_id' => $pid,
                        'team_nba_id' => $tid,
                        'first_name' => '',
                        'last_name' => $this->extractLastNameFromEvent($event),
                        'pts' => 0, 'fgm' => 0, 'fga' => 0,
                        'fg3m' => 0, 'fg3a' => 0,
                        'ftm' => 0, 'fta' => 0,
                        'oreb' => 0, 'dreb' => 0,
                        'ast' => 0, 'stl' => 0, 'blk' => 0,
                        'to' => 0, 'pf' => 0,
                    ];
                }

                $this->processEvent($playerStats[$pid], $event, $scorePts);
            }
        }

        $this->resolvePlayerFirstNames($playerStats, $pbp);

        return array_values($playerStats);
    }

    private function processEvent(array &$stats, array $event, int $scorePts = 0): void
    {
        $etype = $event['etype'] ?? 0;
        $pts = ((int)($event['pts'] ?? 0)) > 0 ? (int)$event['pts'] : $scorePts;
        $de = $event['de'] ?? '';

        if ($etype === 1) {
            $stats['fga']++;
            $isMade = $pts > 0;

            if ($isMade) {
                $stats['pts'] += $pts;
                $stats['fgm']++;
                if ($pts === 3 || str_contains($de, '3pt')) {
                    $stats['fg3a']++;
                    $stats['fg3m']++;
                } elseif (str_contains($de, '3pt')) {
                    $stats['fg3a']++;
                }
            } else {
                if (str_contains($de, '3pt')) {
                    $stats['fg3a']++;
                }
            }

            $assistPid = $event['epid'] ?? null;
            if ($assistPid && $isMade) {
                $this->deferredAssists[] = (int)$assistPid;
            }
        } elseif ($etype === 2) {
            $stats['fga']++;
            if (str_contains($de, '3pt')) {
                $stats['fg3a']++;
            }
            $blockerPid = $event['opid'] ?? 0;
            if ($blockerPid && str_contains($de, 'Block')) {
                $this->deferredBlocks[] = (int)$blockerPid;
            }
        } elseif ($etype === 3) {
            $stats['fta']++;
            $isMade = $pts > 0;
            if ($isMade) {
                $stats['pts'] += $pts;
                $stats['ftm']++;
            }
        } elseif ($etype === 4) {
            $this->parseReboundFromDescription($de, $stats);
        } elseif ($etype === 5) {
            $stats['to']++;
            $stealerPid = $event['opid'] ?? null;
            if ($stealerPid && str_contains($de, 'Steal')) {
                $this->deferredStealsPids[] = (int)$stealerPid;
            }
        } elseif ($etype === 6) {
            $stats['pf']++;
        }
    }

    private function parseReboundFromDescription(string $de, array &$stats): void
    {
        if (preg_match('/\(Off:\s*(\d+)\s+Def:\s*(\d+)\)/', $de, $m)) {
            $off = (int)$m[1];
            $def = (int)$m[2];
            $pid = $stats['nba_pbp_id'];
            $prev = $this->reboundCumulative[$pid] ?? ['off' => 0, 'def' => 0];

            if ($off > $prev['off']) {
                $stats['oreb']++;
                $this->reboundCumulative[$pid] = ['off' => $off, 'def' => $prev['def']];
            } elseif ($def > $prev['def']) {
                $stats['dreb']++;
                $this->reboundCumulative[$pid] = ['off' => $prev['off'], 'def' => $def];
            } else {
                $this->reboundCumulative[$pid] = ['off' => $off, 'def' => $def];
            }
        } else {
            $stats['dreb']++;
        }
    }

    private function extractLastNameFromEvent(array $event): string
    {
        $de = $event['de'] ?? '';
        $pid = $event['pid'] ?? 0;

        preg_match('/^\[[A-Z]{2,4}[^\]]*\]\s*([A-Za-zÀ-ÿ\-\'\.\s]+?)\s+(?:3pt\s+)?(?:Shot|Rebound|Turnover|Free Throw|Foul|Violation|Substitution|Driving|Pullup|Running|Alley|Dunk|Layup|Floating|Step Back|Cutting|Hook)/i', $de, $m);

        if (!empty($m[1])) {
            return trim($m[1]);
        }

        $fallback = $this->extractFallbackName($de);
        if ($fallback) {
            return $fallback;
        }

        return "Player_{$pid}";
    }

    private function extractFallbackName(string $de): ?string
    {
        if (preg_match('/^\[[A-Z]{2,4}[^\]]*\]\s*([A-Za-zÀ-ÿ\-\'\.\s]+?)\s+[A-Z]/', $de, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/\s+\d+\s*$/', '', $name);
            if (strlen($name) > 2 && !str_contains($name, '[')) {
                return $name;
            }
        }
        return null;
    }

    private function resolvePlayerFirstNames(array &$playerStats, array $pbp): void
    {
        $knownNames = $this->buildNameMapFromPbp($pbp);

        foreach ($playerStats as $pid => &$stats) {
            if (isset($knownNames[$pid])) {
                $stats['first_name'] = $knownNames[$pid]['first_name'];
                if (empty($stats['last_name']) || $stats['last_name'] === "Player_{$pid}") {
                    $stats['last_name'] = $knownNames[$pid]['last_name'];
                }
            }
        }
        unset($stats);

        $this->applyDeferredStats($playerStats);
    }

    private function buildNameMapFromPbp(array $pbp): array
    {
        $names = [];
        if (!isset($pbp['g']['pd'])) {
            return $names;
        }

        foreach ($pbp['g']['pd'] as $period) {
            foreach ($period['pla'] as $event) {
                $pid = $event['pid'] ?? 0;
                if (!$pid || isset($names[$pid])) {
                    continue;
                }

                $de = $event['de'] ?? '';
                $fullName = $this->parseFullNameFromDescription($de);
                if ($fullName) {
                    $names[$pid] = $fullName;
                }
            }
        }

        return $names;
    }

    private function parseFullNameFromDescription(string $de): ?array
    {
        if (preg_match('/\[[A-Z]{2,4}[^\]]*\]\s*((?:[A-Za-zÀ-ÿ\-\.]+\.?\s*)+?)\s+(?:\([^)]*\)\s+)?(?:3pt\s+)?(?:Shot|Rebound|Turnover|Free Throw|Foul|Scores|Pullup|Jump|Running|Alley|Dunk|Layup|Floating|Step Back|Cutting|Hook|Driving|Violation)/i', $de, $m)) {
            $nameStr = trim($m[1]);

            $nameStr = preg_replace('/\s+\(\d+\s*(?:AST|PTS|REB|TO|PF|BLK)\)/', '', $nameStr);
            $nameStr = preg_replace('/\s+\d+\s*$/', '', $nameStr);

            $parts = preg_split('/\s+/', $nameStr);

            if (count($parts) >= 2) {
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
                return [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];
            } elseif (count($parts) === 1) {
                return [
                    'first_name' => '',
                    'last_name' => $parts[0],
                ];
            }
        }

        return null;
    }

    private function applyDeferredStats(array &$playerStats): void
    {
        foreach ($this->deferredAssists as $assistPid) {
            if (isset($playerStats[$assistPid])) {
                $playerStats[$assistPid]['ast']++;
            }
        }

        foreach ($this->deferredBlocks as $blockerPid) {
            if (isset($playerStats[$blockerPid])) {
                $playerStats[$blockerPid]['blk']++;
            }
        }

        foreach ($this->deferredStealsPids as $stealerPid) {
            if (isset($playerStats[$stealerPid])) {
                $playerStats[$stealerPid]['stl']++;
            }
        }

        $this->deferredAssists = [];
        $this->deferredBlocks = [];
        $this->deferredStealsPids = [];
    }

    private function extractTeamIdsFromPbp(array $pbp): array
    {
        $teamIds = [];
        if (!isset($pbp['g']['pd'])) {
            return $teamIds;
        }

        foreach ($pbp['g']['pd'] as $period) {
            foreach ($period['pla'] as $event) {
                $tid = $event['tid'] ?? 0;
                if ($tid) {
                    $teamIds[$tid] = $tid;
                }
            }
        }

        return $teamIds;
    }

    private function findOrCreatePlayer(int $nbaPbpId, string $firstName, string $lastName): ?Player
    {
        if (isset($this->playerIdCache[$nbaPbpId])) {
            return $this->playerIdCache[$nbaPbpId];
        }

        $player = Player::where('nba_api_id', $nbaPbpId)->first();

        if (!$player && !empty($lastName) && $lastName !== "Player_{$nbaPbpId}") {
            if (!empty($firstName)) {
                $player = Player::where('first_name', $firstName)
                    ->where('last_name', $lastName)
                    ->first();
            }

            if (!$player) {
                $matches = Player::where('last_name', $lastName)->get();
                if ($matches->count() === 1) {
                    $player = $matches->first();
                }
            }

            if ($player) {
                $player->update(['nba_api_id' => $nbaPbpId]);
                if ($player->first_name === 'Unknown' && !empty($firstName)) {
                    $player->update(['first_name' => $firstName]);
                }
            }
        }

        if (!$player && !empty($lastName) && $lastName !== "Player_{$nbaPbpId}") {
            $player = Player::create([
                'first_name' => $firstName ?: 'Unknown',
                'last_name' => $lastName,
                'nba_api_id' => $nbaPbpId,
                'bio' => 'Imported from PBP data',
            ]);
        }

        if ($player) {
            $this->playerIdCache[$nbaPbpId] = $player;
        }

        return $player;
    }

    private function findTeamByNbaId(int $nbaTeamId): ?Team
    {
        if (isset($this->teamIdCache[$nbaTeamId])) {
            return $this->teamIdCache[$nbaTeamId];
        }

        $team = Team::where('nba_api_id', $nbaTeamId)->first();
        if ($team) {
            $this->teamIdCache[$nbaTeamId] = $team;
        }

        return $team;
    }

    private function warmTeamCache(): void
    {
        $teams = Team::whereNotNull('nba_api_id')->get();
        foreach ($teams as $team) {
            $this->teamIdCache[(int)$team->nba_api_id] = $team;
        }
    }

    private function fetchSeasonSchedule(int $seasonYear): Collection
    {
        $url = "{$this->baseUrl}/{$seasonYear}/league/00_full_schedule.json";

        try {
            $response = $this->client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Failed to fetch schedule for {$seasonYear}", ['error' => $e->getMessage()]);
            return collect();
        }

        if (!$data || !isset($data['lscd'])) {
            return collect();
        }

        $games = collect();

        foreach ($data['lscd'] as $month) {
            foreach ($month['mscd']['g'] as $game) {
                if (!isset($game['h']['tid']) || !isset($game['v']['tid'])) {
                    continue;
                }

                $games->push([
                    'api_game_id' => $game['gid'],
                    'date' => $game['gdte'],
                    'home_team_api_id' => (int)$game['h']['tid'],
                    'away_team_api_id' => (int)$game['v']['tid'],
                    'home_score' => (int)($game['h']['s'] ?? 0),
                    'away_score' => (int)($game['v']['s'] ?? 0),
                    'status' => $game['st'] ?? null,
                    'scoring_leaders' => $this->extractScoringLeaders($game),
                ]);
            }
        }

        return $games;
    }

    private function extractScoringLeaders(array $game): array
    {
        $leaders = [];

        if (isset($game['ptsls']['pl'])) {
            foreach ($game['ptsls']['pl'] as $player) {
                $leaders[] = [
                    'pid' => (int)$player['pid'],
                    'fn' => $player['fn'],
                    'ln' => $player['ln'],
                ];
            }
        }

        return $leaders;
    }

    private function mapScheduleToGames(Collection $schedule, int $seasonYear): void
    {
        $this->updatePlayerIdsFromSchedule($schedule);
        $this->updateGameIdsFromSchedule($schedule, $seasonYear);
    }

    private function updatePlayerIdsFromSchedule(Collection $schedule): void
    {
        $seen = [];

        foreach ($schedule as $entry) {
            foreach ($entry['scoring_leaders'] as $leader) {
                $pid = $leader['pid'];
                if (isset($seen[$pid])) {
                    continue;
                }
                $seen[$pid] = true;

                $player = Player::where('nba_api_id', $pid)->first();
                if (!$player) {
                    $player = Player::where('first_name', $leader['fn'])
                        ->where('last_name', $leader['ln'])
                        ->first();
                }

                if ($player) {
                    if (!$player->nba_api_id) {
                        $player->update(['nba_api_id' => $pid]);
                    }
                    $this->playerIdCache[$pid] = $player;
                }
            }
        }
    }

    private function updateGameIdsFromSchedule(Collection $schedule, int $seasonYear): void
    {
        $season = Season::where('year', $seasonYear)->first();
        if (!$season) {
            return;
        }

        $allGames = Game::where('season_id', $season->id)
            ->whereNull('api_game_id')
            ->get()
            ->keyBy(fn($g) => "{$g->date}|{$g->homeTeam?->nba_api_id}|{$g->awayTeam?->nba_api_id}");

        foreach ($schedule as $entry) {
            if ($entry['status'] !== '3') {
                continue;
            }

            $homeApiId = $entry['home_team_api_id'];
            $awayApiId = $entry['away_team_api_id'];
            $key = "{$entry['date']}|{$homeApiId}|{$awayApiId}";

            if (isset($allGames[$key]) && !$allGames[$key]->api_game_id) {
                $allGames[$key]->update(['api_game_id' => $entry['api_game_id']]);
            }
        }
    }

    private function fetchPbp(int $seasonYear, string $gameId): ?array
    {
        $url = "{$this->baseUrl}/{$seasonYear}/scores/pbp/{$gameId}_full_pbp.json";

        try {
            $response = $this->client->get($url);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            return null;
        }
    }
}
