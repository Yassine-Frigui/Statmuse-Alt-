<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballDataService
{
    private string $baseUrl = 'https://api.football-data.org/v4/';
    private string $apiKey;

    public function __construct()
    {
        $keyPath = base_path('footbal_org_api');
        $this->apiKey = file_exists($keyPath) ? trim(file_get_contents($keyPath)) : '';
    }

    public function getCompetition(string $code = 'CL'): array
    {
        return $this->get("competitions/{$code}");
    }

    public function getTeams(string $code = 'CL'): array
    {
        return $this->get("competitions/{$code}/teams");
    }

    public function getMatches(string $code = 'CL', ?int $season = null): array
    {
        $params = [];
        if ($season) $params['season'] = $season;
        return $this->get("competitions/{$code}/matches", $params);
    }

    public function getStandings(string $code = 'CL', ?int $season = null): array
    {
        $params = [];
        if ($season) $params['season'] = $season;
        return $this->get("competitions/{$code}/standings", $params);
    }

    private function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($query)) $url .= '?' . http_build_query($query);

        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->get($url);

        if ($response->failed()) {
            Log::error("FootballData API error: {$response->status()} {$response->body()}");
            $response->throw();
        }

        return $response->json();
    }
}
