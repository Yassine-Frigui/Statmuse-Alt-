<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionLog extends Model
{
    protected $fillable = [
        'source', 'type', 'records_processed',
        'records_inserted', 'records_skipped', 'errors', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
        ];
    }
}
