<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'DB: ' . DB::connection()->getDatabaseName() . PHP_EOL;
echo 'Seasons: ' . App\Models\Season::count() . PHP_EOL;
echo 'Years: ' . App\Models\Season::pluck('year')->implode(', ') . PHP_EOL;
echo 'Games: ' . App\Models\Game::count() . PHP_EOL;
