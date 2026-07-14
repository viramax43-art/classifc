<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TnvedMeta extends Model
{
    protected $table = 'tnved_meta';

    protected $fillable = [
        'version_number',
        'version_date',
        'items_count',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
