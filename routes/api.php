<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::post('/chatbot', [ChatbotController::class, 'ask']);
Route::get('/chatbot/history/{conversation}', [ChatbotController::class, 'history'])->middleware('auth');
