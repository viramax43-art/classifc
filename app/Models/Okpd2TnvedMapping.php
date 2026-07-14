<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Okpd2TnvedMapping extends Model
{
    protected $fillable = [
        'okpd2_code',
        'tnved_code',
        'source',
        'note',
    ];

    public function okpd2Item(): BelongsTo
    {
        return $this->belongsTo(Okpd2Item::class, 'okpd2_code', 'code');
    }

    public function tnvedItem(): BelongsTo
    {
        return $this->belongsTo(TnvedItem::class, 'tnved_code', 'code');
    }
}
