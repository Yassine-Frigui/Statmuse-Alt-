<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClTeam extends Model
{
    protected $table = 'cl_teams';
    public $incrementing = false;
    protected $fillable = [
        'id', 'name', 'short_name', 'tla', 'crest_url', 'address',
        'website', 'founded', 'club_colors', 'venue', 'country', 'country_code',
    ];

    public function homeMatches()
    {
        return $this->hasMany(ClMatch::class, 'home_team_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(ClMatch::class, 'away_team_id');
    }
}
