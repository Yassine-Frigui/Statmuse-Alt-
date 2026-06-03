<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Season extends Model
{
    use HasFactory;

    protected $table = 'nba_seasons';

    protected $fillable = ['year', 'label', 'start_date', 'end_date'];

    public function playerSeasonStats()
    {
        return $this->hasMany(PlayerSeasonStat::class);
    }

    public function championships()
    {
        return $this->hasMany(Championship::class);
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function playerAwards()
    {
        return $this->hasMany(PlayerAward::class);
    }
}
