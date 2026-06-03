<?php

namespace App\Console\Commands;

use App\Models\ClMatch;
use App\Models\ClSeason;
use App\Models\ClStanding;
use App\Models\ClTeam;
use App\Services\SportsDbService;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class ClSportsDbIngestCommand extends Command
{
    protected $signature = 'cl:sportsdb-ingest
        {--since=2018 : First season year to ingest}
        {--until=2025 : Last season year to ingest}
        {--season= : Single season label (e.g. 2023-2024)}
        {--fresh : Truncate all cl_* tables first}
        ';

    protected $description = 'Ingest Champions League data from TheSportsDB (34 seasons)';

    private SportsDbService $service;

    private int $totalMatches = 0;
    private int $totalTeams = 0;
    private int $totalErrors = 0;
    private int $seasonsDone = 0;
    private int $seasonsSkipped = 0;

    public function handle(SportsDbService $service): int
    {
        $this->service = $service;

        if ($this->option('fresh')) {
            $this->truncateAll();
        }

        $seasons = $this->resolveSeasons();

        if (empty($seasons)) {
            $this->error('No seasons to process.');
            return 1;
        }

        $this->info("Processing " . count($seasons) . " season(s) from TheSportsDB...");

        foreach ($seasons as $seasonLabel) {
            $this->newLine();
            $this->line("--- Season {$seasonLabel} ---");

            try {
                $stats = $this->service->ingestSeason($seasonLabel);
                $this->line("  Matches: {$stats['matches_upserted']}, Teams: {$stats['teams_upserted']}, Rounds: {$stats['rounds_found']}");
                $this->totalMatches += $stats['matches_upserted'];
                $this->totalTeams += $stats['teams_upserted'];
                $this->totalErrors += $stats['errors'];
                $this->seasonsDone++;
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'Rate limited')) {
                    $this->warn("  Rate limited! Resuming will pick up here.");
                    $this->seasonsSkipped = count($seasons) - $this->seasonsDone;
                    break;
                }
                $this->error("  Error: {$e->getMessage()}");
                $this->totalErrors++;
            }
        }

        $this->newLine();
        $this->info('Done.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Seasons completed', $this->seasonsDone],
                ['Seasons remaining', $this->seasonsSkipped],
                ['Matches upserted', $this->totalMatches],
                ['Teams upserted', $this->totalTeams],
                ['Errors', $this->totalErrors],
            ]
        );

        return 0;
    }

    private function resolveSeasons(): array
    {
        if ($season = $this->option('season')) {
            return [$season];
        }

        $since = (int) $this->option('since');
        $until = (int) $this->option('until');

        $seasons = [];
        for ($year = $since; $year <= $until; $year++) {
            $seasons[] = "{$year}-" . ($year + 1);
        }

        return $seasons;
    }

    private function truncateAll(): void
    {
        $this->warn('Truncating all cl_* tables...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ClStanding::truncate();
        ClMatch::truncate();
        ClTeam::truncate();
        ClSeason::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('Done.');
    }
}
