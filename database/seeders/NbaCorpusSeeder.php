<?php

namespace Database\Seeders;

use App\Services\DataIngestionService;
use Illuminate\Database\Seeder;

class NbaCorpusSeeder extends Seeder
{
    public function run(DataIngestionService $ingestionService): void
    {
        $dataDir = database_path('data');

        $files = [
            'teams.csv',
            'seasons.csv',
            'players_historical.csv',
            'coaches.csv',
            'awards.csv',
        ];

        $dependentFiles = [
            'player_season_stats.csv',
            'championships.csv',
            'player_awards.csv',
            'team_season_coach.csv',
            'games.csv',
        ];

        $this->command?->info('=== Phase 1: Independent tables ===');
        foreach ($files as $file) {
            $path = $dataDir . '/' . $file;
            if (file_exists($path)) {
                $this->command?->line("Importing {$file}...");
                $result = $ingestionService->fromCsv($path);
                $this->command?->line("  -> {$result['inserted']} inserted, {$result['skipped']} skipped");
            }
        }

        $this->command?->info('=== Phase 2: Dependent tables (FK) ===');
        foreach ($dependentFiles as $file) {
            $path = $dataDir . '/' . $file;
            if (file_exists($path)) {
                $this->command?->line("Importing {$file}...");
                $result = $ingestionService->fromCsv($path);
                $this->command?->line("  -> {$result['inserted']} inserted, {$result['skipped']} skipped");
            }
        }

        $jsonFile = $dataDir . '/corpus_entries.json';
        if (file_exists($jsonFile)) {
            $this->command?->info('=== Phase 3: Corpus entries ===');
            $this->command?->line('Importing corpus_entries.json...');
            $result = $ingestionService->fromJson($jsonFile);
            $this->command?->line("  -> {$result['inserted']} inserted, {$result['skipped']} skipped");
        }

        $this->command?->info('NBA Corpus seeding complete!');
    }
}
