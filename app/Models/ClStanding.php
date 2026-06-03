<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClStanding extends Model
{
    protected $table = 'cl_standings';
    protected $fillable = [
        'season_id', 'stage', 'type', 'group_name', 'team_id',
        'position', 'played_games', 'form', 'won', 'draw', 'lost',
        'points', 'goals_for', 'goals_against', 'goal_difference',
    ];

    public function season()
    {
        return $this->belongsTo(ClSeason::class, 'season_id');
    }

    public function team()
    {
        return $this->belongsTo(ClTeam::class, 'team_id');
    }
}
