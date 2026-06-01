<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NbaApiService
{
    private Client $client;
    private string $baseUrl = 'https://data.nba.com/data/10s/v2015/json/mobile_teams/nba';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function fetchSeasonGames(int $seasonYear): Collection
    {
        $url = "{$this->baseUrl}/{$seasonYear}/league/00_full_schedule.json";
        $data = $this->fetchJson($url);

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
                    'home_team_api_id' => (int) $game['h']['tid'],
                    'away_team_api_id' => (int) $game['v']['tid'],
                    'home_team_abbr' => $game['h']['ta'],
                    'away_team_abbr' => $game['v']['ta'],
                    'home_team_name' => $game['h']['tn'],
                    'away_team_name' => $game['v']['tn'],
                    'home_score' => (int) ($game['h']['s'] ?? 0),
                    'away_score' => (int) ($game['v']['s'] ?? 0),
                    'arena' => $game['an'] ?? null,
                    'status' => $game['st'] ?? null,
                    'status_text' => $game['stt'] ?? null,
                    'season_year' => $seasonYear,
                    'stage' => $game['seri'] ?? null,
                    'player_scoring_leaders' => $this->extractScoringLeaders($game),
                ]);
            }
        }

        return $games;
    }

    public function fetchTeamIds(): Collection
    {
        return collect([
            ['id' => 1610612737, 'abbreviation' => 'ATL', 'name' => 'Hawks', 'city' => 'Atlanta'],
            ['id' => 1610612738, 'abbreviation' => 'BOS', 'name' => 'Celtics', 'city' => 'Boston'],
            ['id' => 1610612751, 'abbreviation' => 'BKN', 'name' => 'Nets', 'city' => 'Brooklyn'],
            ['id' => 1610612766, 'abbreviation' => 'CHA', 'name' => 'Hornets', 'city' => 'Charlotte'],
            ['id' => 1610612741, 'abbreviation' => 'CHI', 'name' => 'Bulls', 'city' => 'Chicago'],
            ['id' => 1610612739, 'abbreviation' => 'CLE', 'name' => 'Cavaliers', 'city' => 'Cleveland'],
            ['id' => 1610612742, 'abbreviation' => 'DAL', 'name' => 'Mavericks', 'city' => 'Dallas'],
            ['id' => 1610612743, 'abbreviation' => 'DEN', 'name' => 'Nuggets', 'city' => 'Denver'],
            ['id' => 1610612765, 'abbreviation' => 'DET', 'name' => 'Pistons', 'city' => 'Detroit'],
            ['id' => 1610612744, 'abbreviation' => 'GSW', 'name' => 'Warriors', 'city' => 'Golden State'],
            ['id' => 1610612745, 'abbreviation' => 'HOU', 'name' => 'Rockets', 'city' => 'Houston'],
            ['id' => 1610612754, 'abbreviation' => 'IND', 'name' => 'Pacers', 'city' => 'Indiana'],
            ['id' => 1610612746, 'abbreviation' => 'LAC', 'name' => 'Clippers', 'city' => 'LA Clippers'],
            ['id' => 1610612747, 'abbreviation' => 'LAL', 'name' => 'Lakers', 'city' => 'Los Angeles'],
            ['id' => 1610612763, 'abbreviation' => 'MEM', 'name' => 'Grizzlies', 'city' => 'Memphis'],
            ['id' => 1610612748, 'abbreviation' => 'MIA', 'name' => 'Heat', 'city' => 'Miami'],
            ['id' => 1610612749, 'abbreviation' => 'MIL', 'name' => 'Bucks', 'city' => 'Milwaukee'],
            ['id' => 1610612750, 'abbreviation' => 'MIN', 'name' => 'Timberwolves', 'city' => 'Minnesota'],
            ['id' => 1610612740, 'abbreviation' => 'NOP', 'name' => 'Pelicans', 'city' => 'New Orleans'],
            ['id' => 1610612752, 'abbreviation' => 'NYK', 'name' => 'Knicks', 'city' => 'New York'],
            ['id' => 1610612760, 'abbreviation' => 'OKC', 'name' => 'Thunder', 'city' => 'Oklahoma City'],
            ['id' => 1610612753, 'abbreviation' => 'ORL', 'name' => 'Magic', 'city' => 'Orlando'],
            ['id' => 1610612755, 'abbreviation' => 'PHI', 'name' => '76ers', 'city' => 'Philadelphia'],
            ['id' => 1610612756, 'abbreviation' => 'PHX', 'name' => 'Suns', 'city' => 'Phoenix'],
            ['id' => 1610612757, 'abbreviation' => 'POR', 'name' => 'Trail Blazers', 'city' => 'Portland'],
            ['id' => 1610612758, 'abbreviation' => 'SAC', 'name' => 'Kings', 'city' => 'Sacramento'],
            ['id' => 1610612759, 'abbreviation' => 'SAS', 'name' => 'Spurs', 'city' => 'San Antonio'],
            ['id' => 1610612761, 'abbreviation' => 'TOR', 'name' => 'Raptors', 'city' => 'Toronto'],
            ['id' => 1610612762, 'abbreviation' => 'UTA', 'name' => 'Jazz', 'city' => 'Utah'],
            ['id' => 1610612764, 'abbreviation' => 'WAS', 'name' => 'Wizards', 'city' => 'Washington'],
        ]);
    }

    private function extractScoringLeaders(array $game): array
    {
        $leaders = [];

        if (isset($game['ptsls']['pl'])) {
            foreach ($game['ptsls']['pl'] as $player) {
                $leaders[] = [
                    'player_api_id' => (int) $player['pid'],
                    'first_name' => $player['fn'],
                    'last_name' => $player['ln'],
                    'points' => (int) ($player['val'] ?? 0),
                    'team_api_id' => (int) ($player['tid'] ?? 0),
                    'team_abbr' => $player['ta'] ?? null,
                ];
            }
        }

        return $leaders;
    }

    private function fetchJson(string $url): ?array
    {
        try {
            $response = $this->client->get($url);
            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        } catch (GuzzleException $e) {
            Log::error("NBA API fetch failed: {$url}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
