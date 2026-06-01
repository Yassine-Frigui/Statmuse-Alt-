<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('home_score');
            $table->unsignedSmallInteger('away_score');
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 50)->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index(['home_team_id', 'away_team_id']);
            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
