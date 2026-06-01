<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('rebounds')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('steals')->default(0);
            $table->unsignedSmallInteger('blocks')->default(0);
            $table->float('minutes', 5, 1)->default(0);
            $table->float('fg_pct', 5, 3)->nullable();
            $table->float('three_pct', 5, 3)->nullable();
            $table->float('ft_pct', 5, 3)->nullable();
            $table->boolean('is_scoring_leader')->default(false);
            $table->timestamps();

            $table->index(['game_id', 'player_id']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_stats');
    }
};
