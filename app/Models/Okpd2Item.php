<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Okpd2Item extends Model
{
    protected $fillable = [
        'global_id',
        'number',
        'name',
        'idx',
        'section',
        'code',
        'description',
        'ancestors_path',
        'parent_code',
        'level',
        'has_children',
    ];

    protected function casts(): array
    {
        return [
            'has_children' => 'boolean',
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

    public function sectionChildren(): HasMany
    {
        return $this->hasMany(self::class, 'section', 'section')
            ->whereNull('parent_code')
            ->orderBy('code');
    }

    /**
     * OKPD2 hierarchy (см. дерево.docx / classifikators.ru):
     *
     * 16 → 16.1, 16.2
     * 16.2 → 16.21 … 16.29
     * 16.29 → 16.29.1, 16.29.2, 16.29.9
     * 16.29.2 → 16.29.21 … 16.29.25
     * 16.29.25 → 16.29.25.110, .120, .130, .140
     * 16.29.25.130 → (лист)
     *
     * Правила parent_code:
     * 1. XX — класс, parent null
     * 2. Прочие коды — удалить последнюю цифру (16.21 → 16.2)
     * 3. XX.XX.XX.000 → parent XX.XX.XX
     * 4. XX.XX.XX.N0 (N кратен 10) → parent XX.XX.XX
     * 5. XX.XX.XX.Nx (остальные) → parent XX.XX.XX.(N−N%10)
     */
    public static function resolveParentCode(string $code): ?string
    {
        // XX.XX.XX.000 → XX.XX.XX (leaf category)
        if (preg_match('/\.000$/', $code)) {
            $parent = preg_replace('/\.000$/', '', $code) ?? '';

            return $parent !== '' ? $parent : null;
        }

        // XX.XX.XX.NNN — категории и подкатегории (110→вид, 111→110, 120→вид, 121→120)
        if (preg_match('/^(\d{2}\.\d{2}\.\d{2})\.(\d+)$/', $code, $matches)) {
            $kind = $matches[1];
            $suffix = (int) $matches[2];

            if ($suffix % 10 === 0) {
                return $kind;
            }

            if ($suffix >= 10) {
                return $kind.'.'.($suffix - ($suffix % 10));
            }

            return $kind;
        }

        // XX → top-level class, no parent
        if (strlen($code) <= 2) {
            return null;
        }

        // For all other codes, remove last digit; if that leaves a trailing dot, remove it
        // XX.X → XX, XX.XX → XX.X, XX.XX.X → XX.XX, XX.XX.XX → XX.XX.X
        $parent = substr($code, 0, -1);
        $parent = rtrim($parent, '.');

        return $parent !== '' ? $parent : null;
    }

    public static function resolveLevel(string $code): int
    {
        if (preg_match('/\.000$/', $code)) {
            return 6;
        }

        // XX=1, XX.X=2, XX.XX=3, XX.XX.X=4, XX.XX.XX=5, XX.XX.XX.XXX=6
        if (preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d+$/', $code)) {
            if (preg_match('/^(\d{2}\.\d{2}\.\d{2})\.(\d+)$/', $code, $matches)) {
                $suffix = (int) $matches[2];

                if ($suffix % 10 !== 0 && $suffix >= 10) {
                    return 7;
                }
            }

            return 6;
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code)) {
            return 5;
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d$/', $code)) {
            return 4;
        }
        if (preg_match('/^\d{2}\.\d{2}$/', $code)) {
            return 3;
        }
        if (preg_match('/^\d{2}\.\d$/', $code)) {
            return 2;
        }

        return 1;
    }

    public static function resolveLevelName(int $level): string
    {
        return match ($level) {
            1 => 'класс',
            2 => 'подкласс',
            3 => 'группа',
            4 => 'подгруппа',
            5 => 'вид',
            6 => 'категория',
            7 => 'подкатегория',
            default => 'уровень',
        };
    }

    public static function normalizeCodeQuery(string $query): string
    {
        return preg_replace('/[^0-9A-Za-z\.]/', '', str_replace(' ', '', $query)) ?? '';
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

        $parts[] = $parent->code.' — '.$parent->name;

        return implode(' › ', $parts);
    }

    public function breadcrumb(): array
    {
        $trail = [];
        $current = $this;

        while ($current) {
            array_unshift($trail, [
                'code' => $current->code,
                'name' => $current->name,
                'section' => $current->section,
            ]);

            $current = $current->parent_code
                ? self::query()->where('code', $current->parent_code)->first()
                : null;
        }

        $sectionName = config('okpd2.sections.'.$this->section);

        if ($sectionName) {
            array_unshift($trail, [
                'code' => $this->section,
                'name' => $sectionName,
                'section' => $this->section,
                'is_section' => true,
            ]);
        }

        return $trail;
    }
}
