<?php

namespace App\Providers;

use App\Services\Contracts\LLMProvider;
use App\Services\GeminiService;
use App\Services\GroqService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LLMProvider::class, function ($app) {
            $provider = config('services.llm.default', 'groq');

            return match ($provider) {
                'gemini' => new GeminiService(),
                default => new GroqService(),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
