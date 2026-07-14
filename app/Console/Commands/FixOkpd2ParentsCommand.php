<?php

namespace App\Console\Commands;

use App\Models\Okpd2Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOkpd2ParentsCommand extends Command
{
    protected $signature = 'okpd2:fix-parents';
    protected $description = 'Recalculate parent_code, level, and has_children for all OKPD2 items';

    public function handle(): int
    {
        $total = Okpd2Item::query()->count();
        $this->info("Recalculating parent_code & level for {$total} items...");

        $bar = $this->output->createProgressBar($total);
        $updated = 0;

        Okpd2Item::query()->orderBy('id')->chunk(500, function ($items) use ($bar, &$updated) {
            foreach ($items as $item) {
                $newParent = Okpd2Item::resolveParentCode($item->code);
                $newLevel = Okpd2Item::resolveLevel($item->code);

                if ($item->parent_code !== $newParent || $item->level !== $newLevel) {
                    $item->update([
                        'parent_code' => $newParent,
                        'level' => $newLevel,
                    ]);
                    $updated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} items.");

        $this->info('Recalculating has_children...');
        Okpd2Item::query()->update(['has_children' => false]);

        $parentCodes = Okpd2Item::query()
            ->whereNotNull('parent_code')
            ->distinct()
            ->pluck('parent_code');

        $count = Okpd2Item::query()
            ->whereIn('code', $parentCodes)
            ->update(['has_children' => true]);

        $this->info("Marked {$count} items as has_children.");

        $this->info('Rebuilding ancestors_path...');
        $items = Okpd2Item::query()
            ->orderBy('level')
            ->get(['id', 'code', 'name', 'parent_code', 'ancestors_path']);

        $byCode = $items->keyBy('code');
        $pathUpdated = 0;

        foreach ($items as $item) {
            $parent = $item->parent_code ? $byCode->get($item->parent_code) : null;
            $path = Okpd2Item::buildAncestorsPath($parent);

            if ($path !== $item->ancestors_path) {
                DB::table('okpd2_items')
                    ->where('id', $item->id)
                    ->update(['ancestors_path' => $path]);
                $item->ancestors_path = $path;
                $pathUpdated++;
            }
        }

        $this->info("Updated {$pathUpdated} ancestors_path values.");
        $this->info('Done!');

        return self::SUCCESS;
    }
}
