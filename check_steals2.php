<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\GamePlayerStat::count();
echo "Total GPS: {$count}\n";

$s = App\Models\Season::where('year', 2025)->first();
if (!$s) {
    $s = App\Models\Season::where('year', 2024)->first();
}
if (!$s) {
    echo "No season found for 2024!\n";
    print_r(App\Models\Season::where('year', '>=', 2020)->pluck('year')->toArray());
    exit;
}

$gps = App\Models\GamePlayerStat::whereHas('game', function($q) use ($s) {
    $q->where('season_id', $s->id);
});
echo "GPS for {$s->year}: {$gps->count()} rows\n";
echo "Steals sum: " . $gps->sum('steals') . "\n";
echo "TO sum: " . $gps->sum('turnovers') . "\n";
echo "PF sum: " . $gps->sum('personal_fouls') . "\n";
echo "PTS sum: " . $gps->sum('points') . "\n";

// Sample a few rows
echo "\nSample rows:\n";
$rows = App\Models\GamePlayerStat::whereHas('game', function($q) use ($s) {
    $q->where('season_id', $s->id);
})->where('points', '>', 0)->take(3)->get();
foreach ($rows as $r) {
    echo "  GPS #{$r->id}: game={$r->game_id}, player={$r->player_id}, pts={$r->points}, stl={$r->steals}, to={$r->turnovers}, pf={$r->personal_fouls}\n";
}
