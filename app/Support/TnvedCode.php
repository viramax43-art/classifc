<?php

namespace App\Support;

use App\Models\TnvedItem;

/**
 * Централизованное форматирование и определение уровня кодов ТН ВЭД.
 */
final class TnvedCode
{
    public static function normalize(string $code): string
    {
        return TnvedItem::normalizeCode($code);
    }

    public static function formatDisplay(string $code): string
    {
        return TnvedItem::formatDisplayCode($code);
    }

    public static function resolveLevel(string $code): int
    {
        return TnvedItem::resolveLevel($code);
    }

    public static function resolveLevelName(int $level): string
    {
        return TnvedItem::resolveLevelName($level);
    }

    public static function isFullProduct(string $code): bool
    {
        return TnvedItem::isFullProductCode($code);
    }

    public static function findExact(string $code): ?TnvedItem
    {
        return TnvedItem::findByExactCode($code);
    }
}
