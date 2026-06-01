<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GamePlayerStat extends Model
{
    use HasFactory;

    protected $table = 'game_player_stats';

    protected $fillable = [
        'game_id', 'player_id', 'team_id',
        'points', 'rebounds', 'assists', 'steals', 'blocks',
        'minutes', 'fg_pct', 'three_pct', 'ft_pct',
        'fgm', 'fga', 'fg3m', 'fg3a', 'ftm', 'fta',
        'offensive_rebounds', 'defensive_rebounds',
        'turnovers', 'personal_fouls',
        'is_scoring_leader',
    ];

    protected function casts(): array
    {
        return [
            'is_scoring_leader' => 'boolean',
        ];
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
