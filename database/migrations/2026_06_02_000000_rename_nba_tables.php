<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $renameMap = [
        'players' => 'nba_players',
        'teams' => 'nba_teams',
        'seasons' => 'nba_seasons',
        'games' => 'nba_games',
        'game_player_stats' => 'nba_game_player_stats',
        'player_season_stats' => 'nba_player_season_stats',
        'championships' => 'nba_championships',
        'awards' => 'nba_awards',
        'player_awards' => 'nba_player_awards',
        'corpus_entries' => 'nba_corpus_entries',
        'coaches' => 'nba_coaches',
        'team_season_coach' => 'nba_team_season_coach',
        'ingestion_logs' => 'nba_ingestion_logs',
        'conversations' => 'nba_conversations',
    ];

    public function up(): void
    {
        foreach ($this->renameMap as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->renameMap as $old => $new) {
            if (Schema::hasTable($new) && !Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};
