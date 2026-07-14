<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Okpd2Meta extends Model
{
    protected $table = 'okpd2_meta';

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
