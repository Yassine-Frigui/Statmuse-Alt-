<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Award extends Model
{
    use HasFactory;

    protected $table = 'nba_awards';

    protected $fillable = ['name', 'description'];

    public function playerAwards()
    {
        return $this->hasMany(PlayerAward::class);
    }
}
