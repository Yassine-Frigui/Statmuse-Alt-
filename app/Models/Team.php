<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'city', 'abbreviation', 'conference', 'division',
        'arena', 'founded_year', 'is_active',
        'nba_api_id', 'nba_api_abbreviation',
    ];

    public function homeGames()
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function awayGames()
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    public function championships()
    {
        return $this->hasMany(Championship::class, 'champion_team_id');
    }

    public function playerSeasonStats()
    {
        return $this->hasMany(PlayerSeasonStat::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->city} {$this->name}";
    }
}
