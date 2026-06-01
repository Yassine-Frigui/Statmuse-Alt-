<?php

namespace App\Console\Commands;

use App\Services\DataIngestionService;
use App\Services\NbaApiService;
use Illuminate\Console\Command;

class NbaIngestCommand extends Command
{
    protected $signature = 'nba:ingest
        {--source=csv : Data source (csv|json|api)}
        {--file= : Path to the data file}
        {--type= : Data type (auto-detected from filename if omitted)}
        {--all : Import all files from database/data/}
        {--dir= : Directory containing data files}
        {--season= : Season year for API ingestion (e.g. 2024)}
        {--seasons= : Comma-separated list of seasons for API ingestion (e.g. 2021,2022,2023,2024)}
        {--sync-teams : Sync NBA API team IDs to local teams}';

    protected $description = 'Import NBA/ABA data from CSV, JSON, or NBA API into the database';

    public function handle(DataIngestionService $ingestionService, NbaApiService $apiService): int
    {
        $source = $this->option('source');

        if ($source === 'api') {
            return $this->handleApi($ingestionService, $apiService);
        }

        if ($this->option('sync-teams')) {
            return $this->syncApiTeams($ingestionService, $apiService);
        }

        if ($this->option('all')) {
            return $this->importAll($ingestionService);
        }

        $file = $this->option('file');
        $dir = $this->option('dir');

        if ($dir) {
            return $this->importDirectory($ingestionService, $dir);
        }

        if (!$file) {
            $this->error('Specify --file, --dir, --all, --source=api, or --sync-teams');
            return self::FAILURE;
        }

        return $this->importFile($ingestionService, $file);
    }

    private function handleApi(DataIngestionService $service, NbaApiService $api): int
    {
        $exitCode = self::SUCCESS;

        if ($this->option('sync-teams')) {
            $result = $this->syncApiTeams($service, $api);
            if ($result !== self::SUCCESS) {
                $exitCode = $result;
            }
        }

        $seasons = $this->parseSeasons();

        foreach ($seasons as $seasonYear) {
            $this->info("Fetching games for season {$seasonYear}...");

            try {
                $result = $service->fromApiGames($api, $seasonYear);
                $this->displayResult($result);

                usleep(500000);
            } catch (\Exception $e) {
                $this->error("Season {$seasonYear} failed: {$e->getMessage()}");
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function syncApiTeams(DataIngestionService $service, NbaApiService $api): int
    {
        $this->info('Syncing NBA API team IDs...');
        $result = $service->fromApiTeams($api);
        $this->displayResult($result);
        return self::SUCCESS;
    }

    private function parseSeasons(): array
    {
        if ($season = $this->option('season')) {
            return [(int) $season];
        }

        if ($seasons = $this->option('seasons')) {
            return array_map('intval', explode(',', $seasons));
        }

        return [2024, 2023, 2022, 2021];
    }

    private function importFile(DataIngestionService $service, string $file): int
    {
        $path = $this->resolvePath($file);

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_FILENAME);

        $this->info("Importing {$filename}...");

        try {
            $result = $ext === 'json'
                ? $service->fromJson($path)
                : $service->fromCsv($path);

            $this->displayResult($result);

            if ($result['errors'] > 0 && isset($result['log_id'])) {
                $this->warn("Check ingestion_logs table (id: {$result['log_id']}) for error details.");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function importDirectory(DataIngestionService $service, string $dir): int
    {
        $path = $this->resolvePath($dir);

        if (!is_dir($path)) {
            $this->error("Directory not found: {$path}");
            return self::FAILURE;
        }

        $files = glob($path . DIRECTORY_SEPARATOR . '*.{csv,json}', GLOB_BRACE);

        if (empty($files)) {
            $this->warn("No CSV or JSON files found in {$path}");
            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;

        foreach ($files as $file) {
            $result = $this->importFile($service, $file);
            if ($result !== self::SUCCESS) {
                $exitCode = $result;
            }
        }

        return $exitCode;
    }

    private function importAll(DataIngestionService $service): int
    {
        $dir = database_path('data');
        return $this->importDirectory($service, $dir);
    }

    private function resolvePath(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        $dataPath = database_path('data/' . $path);
        if (file_exists($dataPath)) {
            return $dataPath;
        }

        return $path;
    }

    private function displayResult(array $result): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Source', $result['source']],
                ['Type', $result['type']],
                ['Processed', $result['processed']],
                ['Inserted', $result['inserted']],
                ['Skipped', $result['skipped']],
                ['Errors', $result['errors']],
                ['Duration', $result['duration_ms'] . ' ms'],
            ]
        );
    }
}
