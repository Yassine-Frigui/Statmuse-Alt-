<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DataController;
use Illuminate\Support\Facades\Route;

Route::post('/chatbot', [ChatbotController::class, 'ask']);
Route::get('/chatbot/history/{conversation}', [ChatbotController::class, 'history'])->middleware('auth');
Route::get('/ranking', [DataController::class, 'ranking']);
