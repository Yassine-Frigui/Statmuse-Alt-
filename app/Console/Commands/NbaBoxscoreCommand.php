<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Season;
use App\Services\BoxscoreReconstructionService;
use Illuminate\Console\Command;

class NbaBoxscoreCommand extends Command
{
    protected $signature = 'nba:boxscore
        {--season= : Season year to process (e.g., 2024)}
        {--all : Process all seasons with PBP data (2015-2024)}
        {--force : Re-process games even if they already have stats}
        {--game= : Process a single game by ID}';

    protected $description = 'Reconstruct boxscores from NBA PBP data';

    public function handle(BoxscoreReconstructionService $service): int
    {
        if ($this->option('force')) {
            $service->setForce(true);
        }

        if ($this->option('game')) {
            return $this->handleSingleGame($service);
        }

        $seasons = $this->resolveSeasons();

        if (empty($seasons)) {
            $this->error('No seasons to process.');
            return 1;
        }

        $this->info('Boxscore Reconstruction');
        $this->newLine();
        $this->line('Seasons to process: ' . implode(', ', $seasons));
        $this->line('Force re-process: ' . ($this->option('force') ? 'Yes' : 'No'));

        if (!$this->option('no-interaction') && !$this->confirm('Continue with reconstruction?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $start = now();

        foreach ($seasons as $year) {
            $this->newLine();
            $this->info("Processing season {$year}...");
            $bar = $this->output->createProgressBar(
                Game::whereHas('season', fn($q) => $q->where('year', $year))
                    ->whereNotNull('api_game_id')
                    ->count()
            );
            $bar->start();

            $result = $service->reconstructSeason($year);
            $bar->finish();

            $this->newLine(2);

            if (!empty($result['error'])) {
                $this->warn("  {$result['error']}");
            } else {
                $this->line("  Processed: {$result['processed']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");
            }
        }

        $duration = now()->diffInSeconds($start);
        $this->newLine();
        $this->info("Done in {$duration}s");

        return 0;
    }

    private function handleSingleGame(BoxscoreReconstructionService $service): int
    {
        $gameId = (int)$this->option('game');
        $game = Game::with('season')->find($gameId);

        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return 1;
        }

        if (!$game->api_game_id) {
            $this->warn("Game has no api_game_id. Run schedule mapping first.");
            $this->warn("Falling back: attempting to map from schedule...");

            $seasonYear = $game->season?->year;
            if (!$seasonYear) {
                $this->error("Game has no season.");
                return 1;
            }

            $this->call('nba:boxscore', ['--season' => $seasonYear]);
            return 0;
        }

        $this->info("Reconstructing boxscore for game {$game->id} ({$game->api_game_id})...");

        try {
            $service->reconstructGame($game);
            $stats = $game->gamePlayerStats()->with('player')->get();
            $this->info('Done. Players with stats: ' . $stats->count());

            foreach ($stats as $stat) {
                $this->line("  {$stat->player?->full_name}: {$stat->points}PTS, {$stat->rebounds}REB, {$stat->assists}AST");
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function resolveSeasons(): array
    {
        if ($this->option('all')) {
            return [2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024];
        }

        if ($this->option('season')) {
            return [(int)$this->option('season')];
        }

        $years = Season::whereHas('games')->orderBy('year')->pluck('year')->toArray();
        return array_filter($years, fn($y) => $y >= 2015);
    }
}
