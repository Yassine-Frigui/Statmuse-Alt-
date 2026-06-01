<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Season;

// Check total steals across all data
$total = GamePlayerStat::sum('steals');
$entries = GamePlayerStat::count();
$withSteals = GamePlayerStat::where('steals', '>', 0)->count();
echo "Total steals across all entries: {$total}\n";
echo "Entries with steals > 0: {$withSteals} / {$entries}\n\n";

// Per season
$seasons = Season::where('year', '>=', 2015)->orderBy('year')->get();
foreach ($seasons as $s) {
    $steals = GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('steals');
    $pts = GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('points');
    echo "{$s->year}: {$steals} steals, {$pts} PTS (ratio: " . round($pts / max($steals, 1), 0) . " pts/stl)\n";
}

// Look at a specific recent game to debug
echo "\n=== Debugging a 2024 game ===\n";
$g = Game::whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
    ->whereHas('season', fn($q) => $q->where('year', 2024))
    ->first();

if ($g) {
    $stats = GamePlayerStat::where('game_id', $g->id)->get();
    $totalStl = $stats->sum('steals');
    $totalPts = $stats->sum('points');
    echo "Game {$g->id} ({$g->date}): {$g->homeTeam?->abbreviation} vs {$g->awayTeam?->abbreviation}\n";
    echo "Total steals recorded: {$totalStl}, Total PTS: {$totalPts}\n";
    
    // Fetch the PBP to look for steal events
    $client = new \GuzzleHttp\Client(['timeout' => 20, 'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'application/json',
    ]]);
    $url = "https://data.nba.com/data/10s/v2015/json/mobile_teams/nba/2024/scores/pbp/{$g->api_game_id}_full_pbp.json";
    try {
        $resp = $client->get($url);
        $data = json_decode($resp->getBody(), true);
        $stealEvents = [];
        $turnoverEvents = 0;
        foreach ($data['g']['pd'] as $period) {
            foreach ($period['pla'] as $event) {
                if (($event['etype'] ?? 0) == 6) {
                    $turnoverEvents++;
                    $de = $event['de'] ?? '';
                    $opid = $event['opid'] ?? 'none';
                    if (str_contains($de, 'Steal')) {
                        $stealEvents[] = ['de' => $de, 'opid' => $opid, 'pid' => $event['pid'] ?? 0];
                    } elseif (str_contains($de, 'steal')) {
                        $stealEvents[] = ['de' => $de, 'opid' => $opid, 'pid' => $event['pid'] ?? 0];
                    }
                }
            }
        }
        echo "Total turnover events: {$turnoverEvents}\n";
        echo "Steal events found: " . count($stealEvents) . "\n";
        foreach ($stealEvents as $i => $se) {
            echo "  {$i}: de='{$se['de']}', opid={$se['opid']}, pid={$se['pid']}\n";
            if ($i >= 5) { echo "  ...\n"; break; }
        }
    } catch (\Exception $e) {
        echo "PBP fetch failed: " . $e->getMessage() . "\n";
    }
}
