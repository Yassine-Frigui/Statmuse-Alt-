<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('championships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('champion_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('runner_up_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('mvp_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('result_label');
            $table->timestamps();

            $table->index('season_id');
            $table->index('champion_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('championships');
    }
};
