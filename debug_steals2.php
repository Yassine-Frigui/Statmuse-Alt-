<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = new \GuzzleHttp\Client(['timeout' => 20, 'headers' => [
    'User-Agent' => 'Mozilla/5.0',
    'Accept' => 'application/json',
]]);

$g = App\Models\Game::whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
    ->whereHas('season', fn($q) => $q->where('year', 2024))
    ->first();

$url = "https://data.nba.com/data/10s/v2015/json/mobile_teams/nba/2024/scores/pbp/{$g->api_game_id}_full_pbp.json";
$resp = $client->get($url);
$data = json_decode($resp->getBody(), true);

echo "Sample turnover events (etype=6):\n";
$count = 0;
foreach ($data['g']['pd'] as $period) {
    foreach ($period['pla'] as $event) {
        if ((isset($event['etype']) ? $event['etype'] : 0) == 6 && $count < 10) {
            $count++;
            echo "  #{$count}: etype={$event['etype']}, mtype=" . ($event['mtype'] ?? 0) . ", pid=" . ($event['pid'] ?? 0) . ", opid=" . ($event['opid'] ?? 'N/A') . ", tid=" . ($event['tid'] ?? 0) . "\n";
            echo "          de='{$event['de']}'\n";
        }
    }
}
echo "\nTotal turnover events: {$count}\n";

// Also check if steals come from etype=5 (fouls) or other types
echo "\nChecking ALL event types that mention 'Steal' in description:\n";
$count = 0;
foreach ($data['g']['pd'] as $period) {
    foreach ($period['pla'] as $event) {
        $de = $event['de'] ?? '';
        if (stripos($de, 'steal') !== false && $count < 10) {
            $count++;
            echo "  #{$count}: etype=" . ($event['etype'] ?? 0) . ", mtype=" . ($event['mtype'] ?? 0) . ", pid=" . ($event['pid'] ?? 0) . ", opid=" . ($event['opid'] ?? 'N/A') . "\n";
            echo "          de='{$de}'\n";
        }
        // Also check for "stl" field
        if (isset($event['stl'])) {
            echo "  FOUND stl field! etype={$event['etype']}, value={$event['stl']}\n";
        }
    }
}
echo "\nTotal steal mentions found: {$count}\n";

// Check field names in the first few events
echo "\n=== Available fields in events ===\n";
$firstEvent = $data['g']['pd'][0]['pla'][0] ?? null;
if ($firstEvent) {
    echo "Fields: " . implode(', ', array_keys($firstEvent)) . "\n";
}

// Check if there's a steals tracking in the game data
echo "\n=== Game-level stats fields ===\n";
if (isset($data['g']['vsts'])) {
    echo "Visitor team stats keys: " . implode(', ', array_keys($data['g']['vsts'][0])) . "\n";
}
echo "\nTop-level keys: " . implode(', ', array_keys($data['g'])) . "\n";
