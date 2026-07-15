<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TnvedItem extends Model
{
    protected $fillable = [
        'code',
        'display_code',
        'name',
        'idx',
        'section',
        'description',
        'ancestors_path',
        'parent_code',
        'level',
        'has_children',
        'date_begin',
        'date_end',
        'rates',
    ];

    protected function casts(): array
    {
        return [
            'has_children' => 'boolean',
            'date_begin' => 'date',
            'date_end' => 'date',
            'rates' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_code', 'code');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code')
            ->orderBy('code');
    }

    public static function normalizeCode(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) < 10) {
            return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_RIGHT);
        }

        return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_LEFT);
    }

    /**
     * Найти запись по коду или ближайшему существующему предку.
     */
    public static function findResolvable(string $code): ?self
    {
        $normalized = self::normalizeCode($code);
        $current = $normalized;

        while ($current !== '') {
            $item = self::query()->where('code', $current)->first();

            if ($item) {
                return $item;
            }

            $parent = self::resolveParentCode($current);

            if ($parent === null || $parent === $current) {
                break;
            }

            $current = $parent;
        }

        return null;
    }

    public static function createMissingAncestor(string $code): ?self
    {
        $code = self::normalizeCode($code);

        if ($code === '' || self::query()->where('code', $code)->exists()) {
            return self::query()->where('code', $code)->first();
        }

        $child = self::query()->where('parent_code', $code)->orderBy('code')->first();
        $parentCode = self::resolveParentCode($code);

        if ($parentCode && ! self::query()->where('code', $parentCode)->exists()) {
            self::createMissingAncestor($parentCode);
        }

        return self::query()->create([
            'code' => $code,
            'display_code' => self::formatDisplayCode($code),
            'name' => $child?->name ?? '—',
            'idx' => $code,
            'section' => substr($code, 0, 2),
            'parent_code' => $parentCode,
            'level' => self::resolveLevel($code),
            'has_children' => true,
        ]);
    }

    public static function normalizeCodeQuery(string $query): string
    {
        return preg_replace('/\D/', '', str_replace(' ', '', $query)) ?? '';
    }

    /**
     * Уровни ТН ВЭД (10-значный код):
     * 1 — раздел (XX00000000)
     * 2 — группа (XXXX000000)
     * 3 — товарная позиция (XXXXXX0000)
     * 4 — субпозиция (XXXXXXXX00)
     * 5 — подсубпозиция (XXXXXXXXXX)
     */
    public static function resolveLevel(string $code): int
    {
        $code = self::normalizeCode($code);

        if (substr($code, 8) !== '00') {
            return 5;
        }

        if (substr($code, 6, 2) !== '00') {
            return 4;
        }

        if (substr($code, 4, 2) !== '00') {
            return 3;
        }

        if (substr($code, 2, 2) !== '00') {
            return 2;
        }

        return 1;
    }

    public static function resolveParentCode(string $code): ?string
    {
        $code = self::normalizeCode($code);

        return match (self::resolveLevel($code)) {
            1 => null,
            2 => substr($code, 0, 2).'00000000',
            3 => substr($code, 0, 4).'000000',
            4 => substr($code, 0, 6).'0000',
            5 => substr($code, 0, 8).'00',
            default => null,
        };
    }

    public static function resolveLevelName(int $level): string
    {
        return match ($level) {
            1 => 'раздел',
            2 => 'группа',
            3 => 'товарная позиция',
            4 => 'субпозиция',
            5 => 'подсубпозиция',
            default => 'уровень',
        };
    }

    public static function formatDisplayCode(string $code): string
    {
        $code = self::normalizeCode($code);

        if (preg_match('/^\d{10}$/', $code) && substr($code, 4, 2) !== '00') {
            return substr($code, 0, 4).' '
                .substr($code, 4, 2).' '
                .substr($code, 6, 3).' '
                .substr($code, 9, 1);
        }

        return match (self::resolveLevel($code)) {
            1 => substr($code, 0, 2),
            2 => substr($code, 0, 4),
            3 => substr($code, 0, 4).' '.substr($code, 4, 2),
            4 => substr($code, 0, 4).' '.substr($code, 4, 2).' '.substr($code, 6, 2),
            5 => substr($code, 0, 4).' '.substr($code, 4, 2).' '.substr($code, 6, 3).' '.substr($code, 9, 1),
            default => $code,
        };
    }

    public static function buildAncestorsPath(?self $parent): ?string
    {
        if (! $parent) {
            return null;
        }

        $parts = [];

        if ($parent->ancestors_path) {
            $parts[] = $parent->ancestors_path;
        }

        $parts[] = $parent->display_code.' — '.$parent->name;

        return implode(' › ', $parts);
    }

    public function breadcrumb(): array
    {
        $trail = [];
        $current = $this;

        while ($current) {
            array_unshift($trail, [
                'code' => $current->code,
                'display_code' => $current->display_code,
                'name' => $current->name,
                'section' => $current->section,
            ]);

            $current = $current->parent_code
                ? self::query()->where('code', $current->parent_code)->first()
                : null;
        }

        $sectionName = config('tnved.sections.'.$this->section);

        if ($sectionName) {
            array_unshift($trail, [
                'code' => $this->section,
                'display_code' => $this->section,
                'name' => $sectionName,
                'section' => $this->section,
                'is_section' => true,
            ]);
        }

        return $trail;
    }
}
