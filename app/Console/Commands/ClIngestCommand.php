<?php

namespace App\Console\Commands;

use App\Models\ClMatch;
use App\Models\ClSeason;
use App\Models\ClStanding;
use App\Models\ClTeam;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class ClIngestCommand extends Command
{
    protected $signature = 'cl:ingest
        {--season= : Single season year to ingest (e.g. 2024)}
        {--all : Ingest all available seasons}
        {--since= : Ingest seasons from this year onward}
        {--until= : Ingest seasons up to this year}
        {--skip-teams : Skip team ingestion}';

    protected $description = 'Fetch Champions League data from football-data.org and persist it';

    private FootballDataService $api;

    private const COMPETITION_CODE = 'CL';

    private int $teamsUpserted = 0;
    private int $teamsSkipped = 0;
    private int $matchesUpserted = 0;
    private int $standingsUpserted = 0;

    public function handle(FootballDataService $api): int
    {
        $this->api = $api;

        $this->info('Fetching Champions League competition data...');
        $competition = $this->api->getCompetition(self::COMPETITION_CODE);
        $allSeasons = $competition['seasons'] ?? [];
        $this->line("  Found " . count($allSeasons) . " available seasons");

        $seasons = $this->resolveSeasons($allSeasons);
        if (empty($seasons)) {
            $this->error('No seasons match your criteria.');
            return 1;
        }

        if (!$this->option('skip-teams')) {
            $this->ingestTeams();
        }

        foreach ($seasons as $s) {
            $this->ingestSeason($s);
        }

        $this->newLine();
        $this->info('Done.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teams upserted', $this->teamsUpserted],
                ['Teams skipped', $this->teamsSkipped],
                ['Matches upserted', $this->matchesUpserted],
                ['Standings entries', $this->standingsUpserted],
            ]
        );

        return 0;
    }

    private function resolveSeasons(array $allSeasons): array
    {
        if ($this->option('all')) {
            return $allSeasons;
        }

        if ($season = $this->option('season')) {
            return array_filter($allSeasons, fn($s) => (int)$season === self::yearFromSeason($s));
        }

        $since = $this->option('since');
        $until = $this->option('until');

        return array_filter($allSeasons, function ($s) use ($since, $until) {
            $year = self::yearFromSeason($s);
            if ($since && $year < (int)$since) return false;
            if ($until && $year > (int)$until) return false;
            return true;
        });
    }

    private static function yearFromSeason(array $season): int
    {
        return (int) substr($season['startDate'], 0, 4);
    }

    private function ingestTeams(): void
    {
        $this->line("\nFetching teams...");
        $data = $this->api->getTeams(self::COMPETITION_CODE);
        $teams = $data['teams'] ?? [];

        foreach ($teams as $t) {
            ClTeam::updateOrCreate(
                ['id' => $t['id']],
                [
                    'name' => $t['name'] ?? '',
                    'short_name' => $t['shortName'] ?? null,
                    'tla' => $t['tla'] ?? null,
                    'crest_url' => $t['crest'] ?? null,
                    'address' => $t['address'] ?? null,
                    'website' => $t['website'] ?? null,
                    'founded' => $t['founded'] ?? null,
                    'club_colors' => $t['clubColors'] ?? null,
                    'venue' => $t['venue'] ?? null,
                    'country' => $t['area']['name'] ?? null,
                    'country_code' => $t['area']['code'] ?? null,
                ]
            );
            $this->teamsUpserted++;
        }
        $this->line("  {$this->teamsUpserted} teams upserted");
    }

    private function ingestSeason(array $seasonData): void
    {
        $seasonId = $seasonData['id'];
        $year = self::yearFromSeason($seasonData);
        $this->info("\nSeason {$year} (ID: {$seasonId})...");

        $winnerId = null;
        if (!empty($seasonData['winner'])) {
            $winnerId = $seasonData['winner']['id'];
            $this->ensureTeamExists($seasonData['winner']);
        }

        ClSeason::updateOrCreate(
            ['id' => $seasonId],
            [
                'name' => $year . '/' . substr((string)($year + 1), -2),
                'start_date' => $seasonData['startDate'],
                'end_date' => $seasonData['endDate'],
                'current_matchday' => $seasonData['currentMatchday'] ?? null,
                'winner_team_id' => $winnerId,
            ]
        );

        $this->ingestMatches($seasonId, $year);
        $this->ingestStandings($seasonId, $year);
    }

    private function ingestMatches(int $seasonId, int $year): void
    {
        $this->line('  Fetching matches...');
        $data = $this->api->getMatches(self::COMPETITION_CODE, $year);
        $matches = $data['matches'] ?? [];
        $count = 0;

        foreach ($matches as $m) {
            $this->ensureTeamExists($m['homeTeam']);
            $this->ensureTeamExists($m['awayTeam']);

            $score = $m['score'] ?? [];

            ClMatch::updateOrCreate(
                ['id' => $m['id']],
                [
                    'season_id' => $seasonId,
                    'utc_date' => str_replace(['T', 'Z'], [' ', ''], $m['utcDate']),
                    'status' => $m['status'] ?? 'SCHEDULED',
                    'matchday' => $m['matchday'] ?? null,
                    'stage' => $m['stage'] ?? null,
                    'group_name' => $m['group'] ?? null,
                    'home_team_id' => $m['homeTeam']['id'],
                    'away_team_id' => $m['awayTeam']['id'],
                    'home_score' => $score['fullTime']['home'] ?? null,
                    'away_score' => $score['fullTime']['away'] ?? null,
                    'home_score_ht' => $score['halfTime']['home'] ?? null,
                    'away_score_ht' => $score['halfTime']['away'] ?? null,
                    'winner' => $score['winner'] ?? null,
                    'duration' => $score['duration'] ?? null,
                ]
            );
            $count++;
        }

        $this->matchesUpserted += $count;
        $this->line("  {$count} matches upserted");
    }

    private function ingestStandings(int $seasonId, int $year): void
    {
        $this->line('  Fetching standings...');
        $data = $this->api->getStandings(self::COMPETITION_CODE, $year);
        $standingsList = $data['standings'] ?? [];
        $count = 0;

        foreach ($standingsList as $standing) {
            $stage = $standing['stage'] ?? null;
            $type = $standing['type'] ?? null;
            $group = $standing['group'] ?? null;

            foreach ($standing['table'] ?? [] as $row) {
                $this->ensureTeamExists($row['team']);

                ClStanding::updateOrCreate(
                    [
                        'season_id' => $seasonId,
                        'team_id' => $row['team']['id'],
                        'stage' => $stage,
                        'type' => $type,
                        'group_name' => $group,
                    ],
                    [
                        'position' => $row['position'],
                        'played_games' => $row['playedGames'] ?? 0,
                        'form' => $row['form'] ?? null,
                        'won' => $row['won'] ?? 0,
                        'draw' => $row['draw'] ?? 0,
                        'lost' => $row['lost'] ?? 0,
                        'points' => $row['points'] ?? 0,
                        'goals_for' => $row['goalsFor'] ?? 0,
                        'goals_against' => $row['goalsAgainst'] ?? 0,
                        'goal_difference' => $row['goalDifference'] ?? 0,
                    ]
                );
                $count++;
            }
        }

        $this->standingsUpserted += $count;
        $this->line("  {$count} standings entries upserted");
    }

    private function ensureTeamExists(array $teamData): void
    {
        ClTeam::withoutEvents(function () use ($teamData) {
            ClTeam::firstOrCreate(
                ['id' => $teamData['id']],
                [
                    'name' => $teamData['name'] ?? '',
                    'short_name' => $teamData['shortName'] ?? null,
                    'tla' => $teamData['tla'] ?? null,
                    'crest_url' => $teamData['crest'] ?? null,
                ]
            );
        });
    }
}
