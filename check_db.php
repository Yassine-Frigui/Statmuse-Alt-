<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'Season count: ' . App\Models\Season::count() . PHP_EOL;
echo 'Game count: ' . App\Models\Game::count() . PHP_EOL;
echo 'GPS count: ' . App\Models\GamePlayerStat::count() . PHP_EOL;

// Check the DB connection
echo 'DB name: ' . DB::connection()->getDatabaseName() . PHP_EOL;

// Check tables
$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo 'Tables: ' . implode(', ', array_column($tables, 'name')) . PHP_EOL;

// Check latest seasons
$seasons = DB::table('seasons')->orderBy('year', 'desc')->limit(5)->get();
echo 'Latest seasons: ' . $seasons->pluck('year')->implode(', ') . PHP_EOL;
