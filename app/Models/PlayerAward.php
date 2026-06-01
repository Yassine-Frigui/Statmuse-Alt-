<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlayerAward extends Model
{
    use HasFactory;

    protected $fillable = ['player_id', 'award_id', 'season_id'];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function award()
    {
        return $this->belongsTo(Award::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
