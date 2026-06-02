<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::post('/chatbot', [ChatbotController::class, 'ask']);
Route::get('/chatbot/history/{conversation}', [ChatbotController::class, 'history'])->middleware('auth');
Route::post('/compare', [CompareController::class, 'compare']);
Route::get('/ranking', [DataController::class, 'ranking']);

Route::get('/players/search', [PlayerController::class, 'search']);
Route::get('/teams/search', [TeamController::class, 'search']);
