<?php

namespace App\Services\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SportsDbClient
{
    private Client $client;
    private string $baseUrl = 'https://www.thesportsdb.com/api/v1/json/3';
    private int $leagueId = 4480;

    private int $callCount = 0;
    private int $lastCallTime = 0;
    private string $cacheDir;

    private int $maxRetries = 5;
    private int $retryDelay = 30;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ],
        ]);

        $this->cacheDir = storage_path('app/sportsdb_cache');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getLeagueInfo(): array
    {
        return $this->get("lookupleague.php?id={$this->leagueId}", 'league_info');
    }

    public function getEventsByRound(int $round, string $season): array
    {
        $cacheKey = "events_round_{$round}_{$season}";
        return $this->get("eventsround.php?id={$this->leagueId}&r={$round}&s={$season}", $cacheKey);
    }

    public function getTeam(int $teamId): array
    {
        $cacheKey = "team_{$teamId}";
        return $this->get("lookupteam.php?id={$teamId}", $cacheKey);
    }

    public function clearCache(): void
    {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $f) {
            unlink($f);
        }
    }

    public function cacheSize(): int
    {
        return count(glob($this->cacheDir . '/*.json'));
    }

    private function get(string $path, string $cacheKey): array
    {
        $cacheFile = "{$this->cacheDir}/{$cacheKey}.json";

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $this->rateLimit();

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $url = "{$this->baseUrl}/{$path}";
                $response = $this->client->get($url);
                $body = $response->getBody()->getContents();
                $this->callCount++;

                $data = json_decode($body, true) ?? [];

                file_put_contents($cacheFile, json_encode($data));

                return $data;
            } catch (GuzzleException $e) {
                $code = $e->getCode();
                $msg = $e->getMessage();

                if ($code === 429 || $code === 503 || str_contains($msg, '1015')) {
                    if ($attempt < $this->maxRetries) {
                        $wait = $this->retryDelay * $attempt;
                        Log::warning("SportsDB rate limited (attempt {$attempt}/{$this->maxRetries}), waiting {$wait}s");
                        sleep($wait);
                        continue;
                    }
                    throw new \RuntimeException("Rate limited by TheSportsDB after {$this->callCount} calls (tried {$this->maxRetries}x)");
                }

                throw $e;
            }
        }

        return [];
    }

    private function rateLimit(): void
    {
        $now = (int) (microtime(true) * 1000);
        $elapsed = $now - $this->lastCallTime;

        if ($elapsed < 1600) {
            usleep((int) ((1600 - $elapsed) * 1000));
        }

        $this->lastCallTime = (int) (microtime(true) * 1000);
    }
}
