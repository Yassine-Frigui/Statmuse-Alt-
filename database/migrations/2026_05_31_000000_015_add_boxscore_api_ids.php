<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('api_game_id', 20)->nullable()->unique()->after('id');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('nba_api_id')->nullable()->unique()->after('id');
            $table->index('nba_api_id');
        });

        Schema::table('game_player_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('fgm')->default(0)->after('ft_pct');
            $table->unsignedSmallInteger('fga')->default(0)->after('fgm');
            $table->unsignedSmallInteger('fg3m')->default(0)->after('fga');
            $table->unsignedSmallInteger('fg3a')->default(0)->after('fg3m');
            $table->unsignedSmallInteger('ftm')->default(0)->after('fg3a');
            $table->unsignedSmallInteger('fta')->default(0)->after('ftm');
            $table->unsignedSmallInteger('offensive_rebounds')->default(0)->after('fta');
            $table->unsignedSmallInteger('defensive_rebounds')->default(0)->after('offensive_rebounds');
            $table->unsignedSmallInteger('turnovers')->default(0)->after('blocks');
            $table->unsignedSmallInteger('personal_fouls')->default(0)->after('turnovers');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('api_game_id');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['nba_api_id']);
            $table->dropColumn('nba_api_id');
        });

        Schema::table('game_player_stats', function (Blueprint $table) {
            $table->dropColumn([
                'fgm', 'fga', 'fg3m', 'fg3a',
                'ftm', 'fta',
                'offensive_rebounds', 'defensive_rebounds',
                'turnovers', 'personal_fouls',
            ]);
        });
    }
};
