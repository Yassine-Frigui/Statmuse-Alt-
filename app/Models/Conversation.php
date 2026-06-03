<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'nba_conversations';

    protected $fillable = ['user_id', 'messages'];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
