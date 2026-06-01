<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['seasons','teams','players','coaches','awards','championships','player_awards','player_season_stats','games','game_player_stats','team_season_coach','corpus_entries','ingestion_logs'];
foreach ($tables as $table) {
    try {
        $count = DB::table($table)->count();
        echo $table . ': ' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $table . ': ERR ' . $e->getMessage() . PHP_EOL;
    }
}
