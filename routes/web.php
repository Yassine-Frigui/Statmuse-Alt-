<?php

use App\Http\Controllers\ChampionshipController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CorpusEntryController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chatbot');

Route::get('/data', [App\Http\Controllers\DataController::class, 'index'])->name('data.index');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
});

Route::prefix('players')->group(function () {
    Route::get('/', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/{player}', [PlayerController::class, 'show'])->name('players.show');
});

Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/{team}', [TeamController::class, 'show'])->name('teams.show');
});

Route::prefix('seasons')->group(function () {
    Route::get('/', [SeasonController::class, 'index'])->name('seasons.index');
    Route::get('/{season}', [SeasonController::class, 'show'])->name('seasons.show');
});

Route::prefix('championships')->group(function () {
    Route::get('/', [ChampionshipController::class, 'index'])->name('championships.index');
});

Route::prefix('corpus')->group(function () {
    Route::get('/', [CorpusEntryController::class, 'index'])->name('corpus.index');
    Route::get('/{corpusEntry}', [CorpusEntryController::class, 'show'])->name('corpus.show');
});

Route::prefix('scenarios')->group(function () {
    Route::get('/', [ScenarioController::class, 'index'])->name('scenarios.index');
    Route::get('/create', [ScenarioController::class, 'create'])->name('scenarios.create');
    Route::post('/', [ScenarioController::class, 'store'])->name('scenarios.store');
    Route::get('/{scenario}', [ScenarioController::class, 'show'])->name('scenarios.show');
    Route::get('/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
    Route::put('/{scenario}', [ScenarioController::class, 'update'])->name('scenarios.update');
    Route::delete('/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');
});

require __DIR__.'/auth.php';
