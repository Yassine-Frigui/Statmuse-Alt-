<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClSeason extends Model
{
    protected $table = 'cl_seasons';
    public $incrementing = false;
    protected $fillable = ['id', 'name', 'start_date', 'end_date', 'current_matchday', 'winner_team_id'];

    public function matches()
    {
        return $this->hasMany(ClMatch::class, 'season_id');
    }

    public function standings()
    {
        return $this->hasMany(ClStanding::class, 'season_id');
    }

    public function winner()
    {
        return $this->belongsTo(ClTeam::class, 'winner_team_id');
    }
}
