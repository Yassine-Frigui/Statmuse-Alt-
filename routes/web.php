<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ScenarioController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chatbot');

Route::get('/compare', [App\Http\Controllers\CompareController::class, 'index'])->name('compare.index');

Route::get('/data', [DataController::class, 'index'])->name('data.index');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');

    // Scenarios routes
    Route::prefix('scenarios')->group(function () {
        Route::get('/', [ScenarioController::class, 'index'])->name('scenarios.index');
        Route::get('/create', [ScenarioController::class, 'create'])->name('scenarios.create');
        Route::post('/', [ScenarioController::class, 'store'])->name('scenarios.store');
        Route::get('/{scenario}', [ScenarioController::class, 'show'])->name('scenarios.show');
        Route::get('/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
        Route::put('/{scenario}', [ScenarioController::class, 'update'])->name('scenarios.update');
        Route::delete('/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');
    });
});

Route::prefix('chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/ask', [ChatbotController::class, 'ask'])->name('chatbot.ask');
    Route::get('/insight', [ChatbotController::class, 'insight'])->name('chatbot.insight');
});

require __DIR__.'/auth.php';
