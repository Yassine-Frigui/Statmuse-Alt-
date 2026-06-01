<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\PlayerSeasonStat;
use App\Models\Season;
use App\Models\Team;

echo "╔══════════════════════════════════════════════════╗\n";
echo "║       NBA QUERY ENGINE - DATA VALIDATION        ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

echo "=== 1. DATABASE OVERVIEW ===\n";
echo str_pad('Teams:', 25) . str_pad(Team::count(), 10) . "\n";
echo str_pad('Players:', 25) . str_pad(Player::count(), 10) . "\n";
echo str_pad('Seasons:', 25) . str_pad(Season::count(), 10) . "\n";
echo str_pad('Games:', 25) . str_pad(Game::count(), 10) . "\n";
echo str_pad('GamePlayerStats:', 25) . str_pad(GamePlayerStat::count(), 10) . "\n";
echo str_pad('PlayerSeasonStats:', 25) . str_pad(PlayerSeasonStat::count(), 10) . "\n\n";

echo "=== 2. SEASON COVERAGE ===\n";
$header = str_pad('Season', 8) . str_pad('Games', 7) . str_pad('With Stats', 12) . str_pad('Pct', 6) . str_pad('Total PTS', 12) . str_pad('Players', 9) . str_pad('Accuracy', 10);
echo "$header\n" . str_repeat('─', 64) . "\n";

$seasons = Season::where('year', '>=', 2015)->orderBy('year')->get();
$totalGames = 0;
$totalWithStats = 0;
$totalPerfect = 0;
$totalChecked = 0;

foreach ($seasons as $s) {
    $games = Game::where('season_id', $s->id);
    $total = $games->count();
    $withStats = $games->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))->count();
    $pct = $total > 0 ? round($withStats / $total * 100, 1) : 0;

    $totalPts = GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('points');
    $players = PlayerSeasonStat::where('season_id', $s->id)->count();

    // Accuracy spot-check
    $sample = $games->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
        ->inRandomOrder()->take(min(20, $withStats))->get();
    $perfect = 0;
    foreach ($sample as $g) {
        $pbp = GamePlayerStat::where('game_id', $g->id)->sum('points');
        $actual = $g->home_score + $g->away_score;
        if ($pbp === $actual) $perfect++;
    }
    $accStr = $sample->count() > 0 ? "{$perfect}/{$sample->count()}" : 'N/A';

    echo str_pad($s->year, 8) . str_pad($total, 7) . str_pad($withStats, 12) . str_pad($pct . '%', 6) . str_pad(number_format($totalPts), 12) . str_pad($players, 9) . str_pad($accStr, 10) . "\n";

    $totalGames += $total;
    $totalWithStats += $withStats;
    $totalPerfect += $perfect;
    $totalChecked += $sample->count();
}

echo str_repeat('─', 64) . "\n";
echo str_pad('TOTAL', 8) . str_pad($totalGames, 7) . str_pad($totalWithStats, 12) . str_pad(round($totalWithStats/$totalGames*100,1).'%', 6) . str_pad('', 12) . str_pad('', 9) . str_pad("{$totalPerfect}/{$totalChecked}", 10) . "\n\n";

echo "=== 3. STAT CONSISTENCY CHECK ===\n";
$inconsistencies = 0;

// Check: FGM <= FGA
$bad = GamePlayerStat::whereColumn('fgm', '>', 'fga')->count();
if ($bad > 0) { echo "  ✗ FGM > FGA: {$bad} rows\n"; $inconsistencies += $bad; }

// Check: FG3M <= FG3A
$bad = GamePlayerStat::whereColumn('fg3m', '>', 'fg3a')->count();
if ($bad > 0) { echo "  ✗ FG3M > FG3A: {$bad} rows\n"; $inconsistencies += $bad; }

// Check: FTM <= FTA
$bad = GamePlayerStat::whereColumn('ftm', '>', 'fta')->count();
if ($bad > 0) { echo "  ✗ FTM > FTA: {$bad} rows\n"; $inconsistencies += $bad; }

// Check: points = 2*(FGM-FG3M) + 3*FG3M + FTM
$bad = GamePlayerStat::whereRaw('points != (2 * (fgm - fg3m) + 3 * fg3m + ftm)')->count();
if ($bad > 0) { echo "  ✗ Points formula mismatch: {$bad} rows\n"; $inconsistencies += $bad; }

// Check: negative values
$bad = GamePlayerStat::where('points', '<', 0)->count()
    + GamePlayerStat::where('fgm', '<', 0)->count()
    + GamePlayerStat::where('fga', '<', 0)->count();
if ($bad > 0) { echo "  ✗ Negative values: {$bad} rows\n"; $inconsistencies += $bad; }

if ($inconsistencies === 0) {
    echo "  ✓ ALL STATS CONSISTENT (no formula violations)\n";
} else {
    echo "  Total inconsistencies: {$inconsistencies}\n";
}

echo "\n=== 4. PLAYER DATA VALIDATION ===\n";
echo str_pad('Players with Unknown first name:', 40) . Player::where('first_name', 'Unknown')->count() . "\n";
echo str_pad('Players with null nba_api_id:', 40) . Player::whereNull('nba_api_id')->count() . "\n";
echo str_pad('Players with stats but Unknown:', 40) . GamePlayerStat::whereHas('player', fn($q) => $q->where('first_name', 'Unknown'))->count() . "\n";

echo "\n=== 5. TOP 10 SCORERS (All Seasons) ===\n";
$top = PlayerSeasonStat::selectRaw('player_id, SUM(points) as total_pts, SUM(games_played) as total_games, AVG(points/games_played) as ppg')
    ->groupBy('player_id')
    ->orderByDesc('total_pts')
    ->limit(10)
    ->get();
$r = 1;
foreach ($top as $t) {
    $p = Player::find($t->player_id);
    echo str_pad("{$r}. {$p->full_name}", 42) . " " . str_pad(number_format($t->total_pts) . ' PTS', 14) . " " . str_pad("{$t->total_games} GP", 8) . " " . number_format($t->ppg, 1) . " PPG\n";
    $r++;
}

echo "\n=== 6. SAMPLE GAME DETAIL (random from each season) ===\n";
foreach ($seasons->sortBy('year') as $s) {
    $g = Game::where('season_id', $s->id)
        ->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
        ->inRandomOrder()->first();
    if (!$g) { echo "  {$s->year}: No games with stats\n"; continue; }

    $pbp = GamePlayerStat::where('game_id', $g->id)->sum('points');
    $actual = $g->home_score + $g->away_score;
    $diff = $pbp - $actual;
    $icon = $diff === 0 ? '✓' : '✗';
    echo "  {$icon} {$s->year} Game {$g->id} ({$g->date}): {$g->homeTeam?->abbreviation} {$g->home_score} vs {$g->awayTeam?->abbreviation} {$g->away_score} | PBP={$pbp} vs Actual={$actual} (diff={$diff})\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";
