<?php

namespace App\Services;

use App\Models\IngestionLog;
use App\Models\Team;
use App\Models\Season;
use App\Models\Player;
use App\Models\PlayerSeasonStat;
use App\Models\Championship;
use App\Models\Award;
use App\Models\PlayerAward;
use App\Models\Coach;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\CorpusEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataIngestionService
{
    private array $errors = [];
    private int $processed = 0;
    private int $inserted = 0;
    private int $skipped = 0;
    private int $startTime = 0;

    public function fromCsv(string $filePath): array
    {
        $this->resetCounters();
        $this->startTime = (int) (microtime(true) * 1000);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $type = $this->inferTypeFromPath($filePath);

        DB::transaction(function () use ($filePath, $type) {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new \RuntimeException("Cannot open file: {$filePath}");
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                throw new \RuntimeException("Empty CSV or missing headers: {$filePath}");
            }

            $headers = array_map('trim', $headers);

            while (($row = fgetcsv($handle)) !== false) {
                $this->processed++;
                $record = array_combine($headers, array_map('trim', $row));
                if ($record === false) {
                    $this->errors[] = "Row {$this->processed}: column mismatch";
                    continue;
                }

                try {
                    $this->insertRecord($type, $record);
                } catch (\Exception $e) {
                    $this->errors[] = "Row {$this->processed}: {$e->getMessage()}";
                    Log::warning("Ingestion error at row {$this->processed}", [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            fclose($handle);
        });

        $duration = (int) (microtime(true) * 1000) - $this->startTime;

        $log = IngestionLog::create([
            'source' => 'csv',
            'type' => $type,
            'records_processed' => $this->processed,
            'records_inserted' => $this->inserted,
            'records_skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $duration,
        ]);

        return [
            'log_id' => $log->id,
            'source' => 'csv',
            'type' => $type,
            'processed' => $this->processed,
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'errors' => count($this->errors),
            'duration_ms' => $duration,
        ];
    }

    public function fromJson(string $filePath): array
    {
        $this->resetCounters();
        $this->startTime = (int) (microtime(true) * 1000);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $json = file_get_contents($filePath);
        $records = json_decode($json, true);

        if (!is_array($records)) {
            throw new \RuntimeException("Invalid JSON file: {$filePath}");
        }

        $type = $this->inferTypeFromPath($filePath);

        DB::transaction(function () use ($records, $type) {
            foreach ($records as $index => $record) {
                $this->processed++;

                try {
                    $this->insertRecord($type, $record);
                } catch (\Exception $e) {
                    $this->errors[] = "Record {$index}: {$e->getMessage()}";
                    Log::warning("Ingestion error at record {$index}", [
                        'file' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $duration = (int) (microtime(true) * 1000) - $this->startTime;

        $log = IngestionLog::create([
            'source' => 'json',
            'type' => $type,
            'records_processed' => $this->processed,
            'records_inserted' => $this->inserted,
            'records_skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $duration,
        ]);

        return [
            'log_id' => $log->id,
            'source' => 'json',
            'type' => $type,
            'processed' => $this->processed,
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'errors' => count($this->errors),
            'duration_ms' => $duration,
        ];
    }

    private function insertRecord(string $type, array $record): void
    {
        $method = 'insert' . Str::studly($type);

        if (method_exists($this, $method)) {
            $this->$method($record);
        } else {
            throw new \RuntimeException("Unknown ingestion type: {$type}");
        }
    }

    private function insertTeams(array $record): void
    {
        Team::firstOrCreate(
            ['abbreviation' => $record['abbreviation']],
            [
                'name' => $record['name'],
                'city' => $record['city'],
                'conference' => $record['conference'] ?? null,
                'division' => $record['division'] ?? null,
                'arena' => $record['arena'] ?? null,
                'founded_year' => !empty($record['founded_year']) ? (int) $record['founded_year'] : null,
                'is_active' => isset($record['is_active']) ? (bool) $record['is_active'] : true,
            ]
        );
        $this->inserted++;
    }

    private function insertSeasons(array $record): void
    {
        Season::firstOrCreate(
            ['year' => $record['year']],
            [
                'label' => $record['label'],
                'start_date' => $record['start_date'] ?? null,
                'end_date' => $record['end_date'] ?? null,
            ]
        );
        $this->inserted++;
    }

    private function insertPlayersHistorical(array $record): void
    {
        Player::firstOrCreate(
            [
                'first_name' => $record['first_name'],
                'last_name' => $record['last_name'],
            ],
            [
                'position' => $record['position'] ?? null,
                'height' => $record['height'] ?? null,
                'weight' => !empty($record['weight']) ? (int) $record['weight'] : null,
                'birth_date' => $record['birth_date'] ?? null,
                'college' => $record['college'] ?? null,
                'drafted_year' => !empty($record['drafted_year']) ? (int) $record['drafted_year'] : null,
                'bio' => $record['bio'] ?? null,
            ]
        );
        $this->inserted++;
    }

    private function insertPlayerSeasonStats(array $record): void
    {
        $player = Player::find($record['player_id']);
        $team = Team::find($record['team_id']);
        $season = Season::find($record['season_id']);

        if (!$player || !$team || !$season) {
            $this->skipped++;
            return;
        }

        PlayerSeasonStat::firstOrCreate(
            [
                'player_id' => $record['player_id'],
                'season_id' => $record['season_id'],
            ],
            [
                'team_id' => $record['team_id'],
                'games_played' => (int) ($record['games_played'] ?? 0),
                'points' => (float) ($record['points'] ?? 0),
                'rebounds' => (float) ($record['rebounds'] ?? 0),
                'assists' => (float) ($record['assists'] ?? 0),
                'steals' => (float) ($record['steals'] ?? 0),
                'blocks' => (float) ($record['blocks'] ?? 0),
                'minutes' => (float) ($record['minutes'] ?? 0),
                'fg_pct' => $record['fg_pct'] !== '' ? (float) $record['fg_pct'] : null,
                'three_pct' => $record['three_pct'] !== '' ? (float) $record['three_pct'] : null,
                'ft_pct' => $record['ft_pct'] !== '' ? (float) $record['ft_pct'] : null,
            ]
        );
        $this->inserted++;
    }

    private function insertChampionships(array $record): void
    {
        $season = Season::find($record['season_id']);
        $champion = Team::find($record['champion_team_id']);
        $runnerUp = Team::find($record['runner_up_team_id']);

        if (!$season || !$champion || !$runnerUp) {
            $this->skipped++;
            return;
        }

        Championship::firstOrCreate(
            ['season_id' => $record['season_id']],
            [
                'champion_team_id' => $record['champion_team_id'],
                'runner_up_team_id' => $record['runner_up_team_id'],
                'mvp_player_id' => !empty($record['mvp_player_id']) ? $record['mvp_player_id'] : null,
                'result_label' => $record['result_label'],
            ]
        );
        $this->inserted++;
    }

    private function insertAwards(array $record): void
    {
        Award::firstOrCreate(
            ['name' => $record['name']],
            ['description' => $record['description'] ?? null]
        );
        $this->inserted++;
    }

    private function insertPlayerAwards(array $record): void
    {
        $player = Player::find($record['player_id']);
        $award = Award::find($record['award_id']);
        $season = Season::find($record['season_id']);

        if (!$player || !$award || !$season) {
            $this->skipped++;
            return;
        }

        PlayerAward::firstOrCreate(
            [
                'player_id' => $record['player_id'],
                'award_id' => $record['award_id'],
                'season_id' => $record['season_id'],
            ]
        );
        $this->inserted++;
    }

    private function insertCoaches(array $record): void
    {
        Coach::firstOrCreate(
            [
                'first_name' => $record['first_name'],
                'last_name' => $record['last_name'],
            ]
        );
        $this->inserted++;
    }

    private function insertTeamSeasonCoach(array $record): void
    {
        $table = DB::table('team_season_coach');

        $exists = $table
            ->where('coach_id', $record['coach_id'])
            ->where('team_id', $record['team_id'])
            ->where('season_id', $record['season_id'])
            ->exists();

        if ($exists) {
            $this->skipped++;
            return;
        }

        $table->insert([
            'coach_id' => $record['coach_id'],
            'team_id' => $record['team_id'],
            'season_id' => $record['season_id'],
            'games' => (int) ($record['games'] ?? 0),
            'wins' => (int) ($record['wins'] ?? 0),
            'losses' => (int) ($record['losses'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->inserted++;
    }

    private function insertGames(array $record): void
    {
        Game::firstOrCreate(
            [
                'date' => $record['date'],
                'home_team_id' => $record['home_team_id'],
                'away_team_id' => $record['away_team_id'],
            ],
            [
                'home_score' => (int) $record['home_score'],
                'away_score' => (int) $record['away_score'],
                'season_id' => $record['season_id'],
                'stage' => $record['stage'] ?? null,
            ]
        );
        $this->inserted++;
    }

    private function insertCorpusEntries(array $record): void
    {
        CorpusEntry::create([
            'title' => $record['title'],
            'content' => $record['content'],
            'category' => $record['category'] ?? null,
            'tags' => isset($record['tags']) ? (is_array($record['tags']) ? $record['tags'] : json_decode($record['tags'], true)) : null,
            'source' => $record['source'] ?? null,
        ]);
        $this->inserted++;
    }

    private function inferTypeFromPath(string $path): string
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        return Str::snake($filename);
    }

    public function fromApiTeams(NbaApiService $api): array
    {
        $this->resetCounters();
        $this->startTime = (int) (microtime(true) * 1000);

        $apiTeams = $api->fetchTeamIds();
        $this->processed = $apiTeams->count();

        DB::transaction(function () use ($apiTeams) {
            foreach ($apiTeams as $apiTeam) {
                $localTeam = Team::where('nba_api_id', $apiTeam['id'])->first();

                if (!$localTeam) {
                    $localTeam = Team::where('abbreviation', $apiTeam['abbreviation'])->first();
                }

                if ($localTeam) {
                    $localTeam->update([
                        'nba_api_id' => $apiTeam['id'],
                        'nba_api_abbreviation' => $apiTeam['abbreviation'],
                    ]);
                    $this->inserted++;
                } else {
                    $this->skipped++;
                    $this->errors[] = "No match for team: {$apiTeam['abbreviation']} ({$apiTeam['name']})";
                }
            }
        });

        $duration = (int) (microtime(true) * 1000) - $this->startTime;

        IngestionLog::create([
            'source' => 'api',
            'type' => 'team_mapping',
            'records_processed' => $this->processed,
            'records_inserted' => $this->inserted,
            'records_skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $duration,
        ]);

        return [
            'source' => 'api',
            'type' => 'team_mapping',
            'processed' => $this->processed,
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'errors' => count($this->errors),
            'duration_ms' => $duration,
        ];
    }

    public function fromApiGames(NbaApiService $api, int $seasonYear): array
    {
        $this->resetCounters();
        $this->startTime = (int) (microtime(true) * 1000);

        $games = $api->fetchSeasonGames($seasonYear);
        $this->processed = $games->count();

        $season = Season::firstOrCreate(
            ['year' => $seasonYear],
            ['label' => "{$seasonYear}-" . substr((string) ($seasonYear + 1), -2)]
        );

        DB::transaction(function () use ($games, $season) {
            foreach ($games as $game) {
                if ($game['status'] !== '3') {
                    $this->skipped++;
                    continue;
                }

                $homeTeam = Team::where('nba_api_id', $game['home_team_api_id'])->first();
                $awayTeam = Team::where('nba_api_id', $game['away_team_api_id'])->first();

                if (!$homeTeam || !$awayTeam) {
                    $this->skipped++;
                    continue;
                }

                $gameRecord = Game::firstOrCreate(
                    [
                        'date' => $game['date'],
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id,
                    ],
                    [
                        'home_score' => $game['home_score'],
                        'away_score' => $game['away_score'],
                        'season_id' => $season->id,
                        'stage' => $game['stage'] ?: ($game['status_text'] === 'Final' ? 'Regular Season' : $game['status_text']),
                    ]
                );

                $this->inserted++;

                if (!empty($game['player_scoring_leaders'])) {
                    foreach ($game['player_scoring_leaders'] as $leader) {
                        $this->upsertGamePlayerStat($gameRecord, $leader);
                    }
                }
            }
        });

        $duration = (int) (microtime(true) * 1000) - $this->startTime;

        IngestionLog::create([
            'source' => 'api',
            'type' => "games_{$seasonYear}",
            'records_processed' => $this->processed,
            'records_inserted' => $this->inserted,
            'records_skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $duration,
        ]);

        return [
            'source' => 'api',
            'type' => "games_{$seasonYear}",
            'processed' => $this->processed,
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'errors' => count($this->errors),
            'duration_ms' => $duration,
        ];
    }

    private function upsertGamePlayerStat(Game $game, array $leader): void
    {
        $player = Player::where('first_name', $leader['first_name'])
            ->where('last_name', $leader['last_name'])
            ->first();

        if (!$player) {
            $player = Player::create([
                'first_name' => $leader['first_name'],
                'last_name' => $leader['last_name'],
                'bio' => 'Imported from NBA API',
            ]);
        }

        $team = Team::where('nba_api_id', $leader['team_api_id'])->first();
        if (!$team) {
            return;
        }

        \App\Models\GamePlayerStat::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'points' => $leader['points'],
            'is_scoring_leader' => true,
        ]);

        $seasonStat = \App\Models\PlayerSeasonStat::where([
            'player_id' => $player->id,
            'season_id' => $game->season_id,
        ])->first();

        if ($seasonStat) {
            $seasonStat->increment('games_played');
            $seasonStat->increment('points', $leader['points']);
        } else {
            \App\Models\PlayerSeasonStat::create([
                'player_id' => $player->id,
                'season_id' => $game->season_id,
                'team_id' => $team->id,
                'games_played' => 1,
                'points' => $leader['points'],
                'rebounds' => 0,
                'assists' => 0,
                'steals' => 0,
                'blocks' => 0,
                'minutes' => 0,
            ]);
        }
    }

    private function resetCounters(): void
    {
        $this->errors = [];
        $this->processed = 0;
        $this->inserted = 0;
        $this->skipped = 0;
        $this->startTime = 0;
    }
}
