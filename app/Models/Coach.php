<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coach extends Model
{
    use HasFactory;

    protected $table = 'nba_coaches';

    protected $fillable = ['first_name', 'last_name'];

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'nba_team_season_coach')
            ->withPivot(['season_id', 'games', 'wins', 'losses']);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
