<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorpusEntry extends Model
{
    use HasFactory;

    protected $table = 'nba_corpus_entries';

    protected $fillable = ['title', 'content', 'category', 'tags', 'source'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
