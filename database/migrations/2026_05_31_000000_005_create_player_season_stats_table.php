<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('games_played')->default(0);
            $table->float('points', 8, 1)->default(0);
            $table->float('rebounds', 8, 1)->default(0);
            $table->float('assists', 8, 1)->default(0);
            $table->float('steals', 8, 1)->default(0);
            $table->float('blocks', 8, 1)->default(0);
            $table->float('minutes', 8, 1)->default(0);
            $table->float('fg_pct', 5, 3)->nullable();
            $table->float('three_pct', 5, 3)->nullable();
            $table->float('ft_pct', 5, 3)->nullable();
            $table->timestamps();

            $table->index(['player_id', 'season_id']);
            $table->index('points');
            $table->index('rebounds');
            $table->index('assists');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_season_stats');
    }
};
