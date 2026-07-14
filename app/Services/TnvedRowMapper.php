<?php

namespace App\Services;

use App\Models\TnvedItem;

class TnvedRowMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function map(array $payload): ?array
    {
        $code = self::extractCode($payload['CODE'] ?? '');

        if ($code === null) {
            return null;
        }

        $name = trim((string) ($payload['KR_NAIM'] ?? $payload['NAIM'] ?? ''));

        if ($name === '') {
            return null;
        }

        $section = substr($code, 0, 2);
        $rates = is_array($payload['TNVED'] ?? null) ? $payload['TNVED'] : null;

        return [
            'code' => $code,
            'display_code' => TnvedItem::formatDisplayCode($code),
            'name' => $name,
            'idx' => $code,
            'section' => $section,
            'description' => filled($payload['PRIM'] ?? null) ? (string) $payload['PRIM'] : null,
            'parent_code' => TnvedItem::resolveParentCode($code),
            'level' => TnvedItem::resolveLevel($code),
            'has_children' => false,
            'date_begin' => self::parseDate($payload['DBEGIN'] ?? null),
            'date_end' => self::parseDate($payload['DEND'] ?? null),
            'rates' => $rates ? json_encode($rates, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public static function normalizeCode(string $code): string
    {
        return TnvedItem::normalizeCode($code);
    }

    public static function extractCode(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        return self::normalizeCode($digits);
    }

    private static function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
