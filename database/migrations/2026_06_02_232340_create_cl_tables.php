<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cl_seasons', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name', 20)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('current_matchday')->nullable();
            $table->integer('winner_team_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cl_teams', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('tla', 5)->nullable();
            $table->string('crest_url')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('founded')->nullable();
            $table->string('club_colors')->nullable();
            $table->string('venue')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 5)->nullable();
            $table->timestamps();
        });

        Schema::create('cl_matches', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('season_id');
            $table->dateTime('utc_date');
            $table->string('status', 20)->default('SCHEDULED');
            $table->unsignedTinyInteger('matchday')->nullable();
            $table->string('stage', 50)->nullable();
            $table->string('group_name', 50)->nullable();
            $table->integer('home_team_id');
            $table->integer('away_team_id');
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->unsignedTinyInteger('home_score_ht')->nullable();
            $table->unsignedTinyInteger('away_score_ht')->nullable();
            $table->string('winner', 20)->nullable();
            $table->string('duration', 20)->nullable();
            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('cl_seasons')->cascadeOnDelete();
            $table->foreign('home_team_id')->references('id')->on('cl_teams')->cascadeOnDelete();
            $table->foreign('away_team_id')->references('id')->on('cl_teams')->cascadeOnDelete();
        });

        Schema::create('cl_standings', function (Blueprint $table) {
            $table->id();
            $table->integer('season_id');
            $table->string('stage', 50)->nullable();
            $table->string('type', 20)->nullable();
            $table->string('group_name', 50)->nullable();
            $table->integer('team_id');
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('played_games')->default(0);
            $table->string('form', 50)->nullable();
            $table->unsignedTinyInteger('won')->default(0);
            $table->unsignedTinyInteger('draw')->default(0);
            $table->unsignedTinyInteger('lost')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->smallInteger('goal_difference')->default(0);
            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('cl_seasons')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('cl_teams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_standings');
        Schema::dropIfExists('cl_matches');
        Schema::dropIfExists('cl_teams');
        Schema::dropIfExists('cl_seasons');
    }
};
