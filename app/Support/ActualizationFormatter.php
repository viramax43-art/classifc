<?php

namespace App\Support;

use App\Models\Okpd2TnvedMapping;
use Carbon\Carbon;
use Illuminate\Support\Carbon as IlluminateCarbon;

class ActualizationFormatter
{
    public static function fromMeta(?object $meta): ?string
    {
        if ($meta === null) {
            return null;
        }

        $versionDate = $meta->version_date ?? null;
        $syncedAt = $meta->synced_at ?? null;

        if (filled($versionDate)) {
            return self::formatDateTime($versionDate);
        }

        if ($syncedAt instanceof IlluminateCarbon || is_string($syncedAt)) {
            return self::formatDateTime($syncedAt);
        }

        return null;
    }

    public static function mappings(): ?string
    {
        $updatedAt = Okpd2TnvedMapping::query()->max('updated_at');

        return $updatedAt ? self::formatDateTime($updatedAt) : null;
    }

    public static function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->timezone('Europe/Moscow')
                ->format('d.m.Y H:i');
        } catch (\Throwable) {
            return is_string($value) ? trim($value) : null;
        }
    }
}
