<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = App\Models\GamePlayerStat::sum('steals');
$withSteals = App\Models\GamePlayerStat::where('steals', '>', 0)->count();
$totalTo = App\Models\GamePlayerStat::sum('turnovers');
$totalPf = App\Models\GamePlayerStat::sum('personal_fouls');
echo "Steals across all processed data: {$total}, entries with steals > 0: {$withSteals}\n";
echo "Total TO: {$totalTo}, Total PF: {$totalPf}\n";

// Check 2024 specifically
$s = App\Models\Season::where('year', 2024)->first();
$steals2024 = App\Models\GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('steals');
$to2024 = App\Models\GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('turnovers');
$pf2024 = App\Models\GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('personal_fouls');
$pts2024 = App\Models\GamePlayerStat::whereHas('game', fn($q) => $q->where('season_id', $s->id))->sum('points');
echo "2024: Steals={$steals2024}, TO={$to2024}, PF={$pf2024}, PTS={$pts2024}\n";

// Sample game verify
$g = App\Models\Game::where('season_id', $s->id)
    ->whereHas('gamePlayerStats', fn($q) => $q->where('points', '>', 0))
    ->first();
$stats = App\Models\GamePlayerStat::where('game_id', $g->id);
echo "Sample game {$g->id}: Stl=" . $stats->sum('steals') . " TO=" . $stats->sum('turnovers') . " PF=" . $stats->sum('personal_fouls') . " PTS=" . $stats->sum('points') . "\n";

// Leader in steals
$topStl = App\Models\GamePlayerStat::selectRaw('player_id, SUM(steals) as total_stl')
    ->whereHas('game', fn($q) => $q->where('season_id', $s->id))
    ->groupBy('player_id')
    ->orderByDesc('total_stl')
    ->first();
if ($topStl) {
    $p = App\Models\Player::find($topStl->player_id);
    echo "Steals leader: {$p->full_name} with {$topStl->total_stl}\n";
}
