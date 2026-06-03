<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClMatch extends Model
{
    protected $table = 'cl_matches';
    public $incrementing = false;
    protected $fillable = [
        'id', 'season_id', 'utc_date', 'status', 'matchday', 'stage', 'group_name',
        'home_team_id', 'away_team_id', 'home_score', 'away_score',
        'home_score_ht', 'away_score_ht', 'winner', 'duration',
    ];

    public function season()
    {
        return $this->belongsTo(ClSeason::class, 'season_id');
    }

    public function homeTeam()
    {
        return $this->belongsTo(ClTeam::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(ClTeam::class, 'away_team_id');
    }
}
