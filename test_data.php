<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Player;
use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use App\Models\PlayerSeasonStat;
use App\Models\Championship;
use App\Models\Award;
use App\Models\PlayerAward;
use App\Models\Coach;
use App\Models\CorpusEntry;

echo "=== Data Stats ===\n\n";
echo "Players: " . Player::count() . "\n";
echo "Teams: " . Team::count() . "\n";
echo "Seasons: " . Season::count() . " (" . Season::min('year') . " - " . Season::max('year') . ")\n";
echo "Games: " . Game::count() . "\n";
echo "PlayerSeasonStats: " . PlayerSeasonStat::count() . "\n";
echo "Championships: " . Championship::count() . "\n";
echo "Awards: " . Award::count() . "\n";
echo "PlayerAwards: " . PlayerAward::count() . "\n";
echo "Coaches: " . Coach::count() . "\n";
echo "CorpusEntries: " . CorpusEntry::count() . "\n";

echo "\n=== Sample Players ===\n";
Player::take(10)->get()->each(function ($p) {
    echo "  - {$p->first_name} {$p->last_name} ({$p->position})\n";
});

echo "\n=== Sample Teams ===\n";
Team::take(10)->get()->each(function ($t) {
    echo "  - {$t->city} {$t->name} ({$t->abbreviation})\n";
});

echo "\n=== Recent Champions ===\n";
Championship::with(['season', 'championTeam'])->latest()->take(5)->get()->each(function ($c) {
    echo "  - {$c->season->year}: {$c->championTeam->name}\n";
});

echo "\n=== Corpus Entries ===\n";
CorpusEntry::all()->each(function ($e) {
    echo "  - [{$e->category}] {$e->title}\n";
});

echo "\n=== Games ===\n";
Game::with(['homeTeam', 'awayTeam', 'season'])->take(5)->get()->each(function ($g) {
    echo "  - {$g->homeTeam->name} vs {$g->awayTeam->name} ({$g->date}, {$g->stage})\n";
});

echo "\n=== Players with Stats ===\n";
PlayerSeasonStat::with('player')->take(5)->get()->each(function ($s) {
    echo "  - {$s->player->full_name}: {$s->points} PPG, {$s->rebounds} RPG\n";
});
