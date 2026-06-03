<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlayerSeasonStat extends Model
{
    use HasFactory;

    protected $table = 'nba_player_season_stats';

    protected $fillable = [
        'player_id', 'team_id', 'season_id', 'games_played',
        'points', 'rebounds', 'assists', 'steals', 'blocks',
        'minutes', 'fg_pct', 'three_pct', 'ft_pct',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
