<?php

namespace App\Console\Commands;

use App\Models\TnvedItem;
use Illuminate\Console\Command;

class FixTnvedParentsCommand extends Command
{
    protected $signature = 'tnved:fix-parents';

    protected $description = 'Пересчитать parent_code, level, has_children и ancestors_path для ТН ВЭД';

    public function handle(): int
    {
        $this->info('Создание отсутствующих предков...');
        $created = $this->createMissingAncestors();
        $this->components->info("Добавлено предков: {$created}");

        $items = TnvedItem::query()->get(['id', 'code', 'display_code', 'name', 'parent_code', 'level', 'ancestors_path']);
        $byCode = $items->keyBy('code');

        $this->info('Пересчёт parent_code и level...');

        foreach ($items as $item) {
            $parentCode = TnvedItem::resolveParentCode($item->code);
            $level = TnvedItem::resolveLevel($item->code);

            TnvedItem::query()->whereKey($item->id)->update([
                'parent_code' => $parentCode,
                'level' => $level,
                'display_code' => TnvedItem::formatDisplayCode($item->code),
                'has_children' => false,
            ]);
        }

        $this->info('Построение ancestors_path...');

        $items = TnvedItem::query()->orderBy('level')->get(['id', 'code', 'display_code', 'name', 'parent_code', 'ancestors_path']);
        $byCode = $items->keyBy('code');

        foreach ($items as $item) {
            $parent = $item->parent_code ? $byCode->get($item->parent_code) : null;
            $path = TnvedItem::buildAncestorsPath($parent);

            TnvedItem::query()->whereKey($item->id)->update(['ancestors_path' => $path]);
            $item->ancestors_path = $path;
        }

        $this->info('Отметка has_children...');

        $parentCodes = TnvedItem::query()
            ->whereNotNull('parent_code')
            ->distinct()
            ->pluck('parent_code');

        TnvedItem::query()->update(['has_children' => false]);
        TnvedItem::query()->whereIn('code', $parentCodes)->update(['has_children' => true]);

        $this->components->info('Готово: '.TnvedItem::query()->count().' записей');

        return self::SUCCESS;
    }

    private function createMissingAncestors(): int
    {
        $created = 0;

        for ($pass = 0; $pass < 10; $pass++) {
            $missing = TnvedItem::query()
                ->whereNotNull('parent_code')
                ->distinct()
                ->pluck('parent_code')
                ->filter(fn (string $code) => ! TnvedItem::query()->where('code', $code)->exists());

            if ($missing->isEmpty()) {
                break;
            }

            foreach ($missing as $code) {
                if (TnvedItem::createMissingAncestor($code)) {
                    $created++;
                }
            }
        }

        return $created;
    }
}
