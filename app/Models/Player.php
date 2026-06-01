<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'nba_api_id', 'first_name', 'last_name', 'position', 'height', 'weight',
        'birth_date', 'college', 'drafted_year', 'bio',
    ];

    public function seasonStats()
    {
        return $this->hasMany(PlayerSeasonStat::class);
    }

    public function awards()
    {
        return $this->hasMany(PlayerAward::class);
    }

    public function championships()
    {
        return $this->hasMany(Championship::class, 'mvp_player_id');
    }

    public function scopeTopScorers($query, ?int $seasonId = null, int $limit = 10)
    {
        $query = $query->select('players.*')
            ->selectRaw('COALESCE(SUM(player_season_stats.points), 0) as total_points')
            ->join('player_season_stats', 'players.id', '=', 'player_season_stats.player_id');

        if ($seasonId) {
            $query->where('player_season_stats.season_id', $seasonId);
        }

        return $query->groupBy('players.id')
            ->orderByDesc('total_points')
            ->limit($limit);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
