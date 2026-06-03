<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Championship extends Model
{
    use HasFactory;

    protected $table = 'nba_championships';

    protected $fillable = [
        'season_id', 'champion_team_id', 'runner_up_team_id',
        'mvp_player_id', 'result_label',
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function championTeam()
    {
        return $this->belongsTo(Team::class, 'champion_team_id');
    }

    public function runnerUpTeam()
    {
        return $this->belongsTo(Team::class, 'runner_up_team_id');
    }

    public function mvpPlayer()
    {
        return $this->belongsTo(Player::class, 'mvp_player_id');
    }
}
