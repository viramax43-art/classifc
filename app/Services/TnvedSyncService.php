<?php

namespace App\Services;

use App\Models\TnvedItem;
use App\Models\TnvedMeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class TnvedSyncService
{
    public function __construct(
        private readonly TksApiClient $api,
        private readonly TnvedRowMapper $mapper,
    ) {}

    public function sync(?callable $onProgress = null, bool $useArchive = true): int
    {
        $startedAt = microtime(true);

        $this->report($onProgress, ['phase' => 'metadata', 'started_at' => $startedAt]);

        $version = $this->api->getVersion();
        $versionNumber = $this->normalizeVersionValue($version['version'] ?? $version['VER'] ?? $version['VERSION'] ?? null);
        $versionDate = $this->normalizeVersionValue($version['date'] ?? $version['DATE'] ?? null);

        $this->report($onProgress, [
            'phase' => 'metadata_done',
            'started_at' => $startedAt,
            'version_number' => $versionNumber,
            'version_date' => $versionDate,
            'base_url' => $this->api->getActiveBaseUrl(),
        ]);

        $this->report($onProgress, ['phase' => 'clear', 'started_at' => $startedAt]);
        TnvedItem::query()->delete();

        $imported = $useArchive
            ? $this->importFromArchive($onProgress, $startedAt)
            : $this->importFromCodeList($onProgress, $startedAt);

        return $this->finalizeImport($versionNumber, $versionDate, $imported, $onProgress, $startedAt);
    }

    /**
     * @return array{
     *   should_sync: bool,
     *   reason: string,
     *   remote_version_number: ?string,
     *   remote_version_date: ?string,
     *   local_version_number: ?string,
     *   local_version_date: ?string
     * }
     */
    public function checkForUpdates(): array
    {
        $version = $this->api->getVersion();
        $localMeta = TnvedMeta::query()->first();
        $isEmpty = ! TnvedItem::query()->exists();

        $remoteVersionNumber = $this->normalizeVersionValue($version['version'] ?? $version['VER'] ?? $version['VERSION'] ?? null);
        $remoteVersionDate = $this->normalizeVersionValue($version['date'] ?? $version['DATE'] ?? null);
        $localVersionNumber = $this->normalizeVersionValue($localMeta?->version_number);
        $localVersionDate = $this->normalizeVersionValue($localMeta?->version_date);

        if ($isEmpty) {
            return [
                'should_sync' => true,
                'reason' => 'local_empty',
                'remote_version_number' => $remoteVersionNumber,
                'remote_version_date' => $remoteVersionDate,
                'local_version_number' => $localVersionNumber,
                'local_version_date' => $localVersionDate,
            ];
        }

        if ($localMeta === null) {
            return [
                'should_sync' => true,
                'reason' => 'meta_missing',
                'remote_version_number' => $remoteVersionNumber,
                'remote_version_date' => $remoteVersionDate,
                'local_version_number' => $localVersionNumber,
                'local_version_date' => $localVersionDate,
            ];
        }

        if ($remoteVersionDate !== $localVersionDate || $remoteVersionNumber !== $localVersionNumber) {
            return [
                'should_sync' => true,
                'reason' => 'remote_changed',
                'remote_version_number' => $remoteVersionNumber,
                'remote_version_date' => $remoteVersionDate,
                'local_version_number' => $localVersionNumber,
                'local_version_date' => $localVersionDate,
            ];
        }

        return [
            'should_sync' => false,
            'reason' => 'up_to_date',
            'remote_version_number' => $remoteVersionNumber,
            'remote_version_date' => $remoteVersionDate,
            'local_version_number' => $localVersionNumber,
            'local_version_date' => $localVersionDate,
        ];
    }

    public function importFromDirectory(string $directory, ?callable $onProgress = null, bool $clear = true, bool $finalize = false): int
    {
        $startedAt = microtime(true);

        if ($clear) {
            $this->report($onProgress, ['phase' => 'clear', 'started_at' => $startedAt]);
            TnvedItem::query()->delete();
        }

        $files = collect(File::allFiles($directory))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'json');

        $total = $files->count();
        $imported = 0;
        $batch = [];

        $this->report($onProgress, [
            'phase' => 'download_start',
            'started_at' => $startedAt,
            'rows_total' => $total,
        ]);

        foreach ($files as $index => $file) {
            $payload = json_decode(File::get($file->getPathname()), true);

            if (! is_array($payload)) {
                continue;
            }

            $mapped = $this->mapper->map($payload);

            if ($mapped !== null) {
                $batch[] = $mapped;
            }

            if (count($batch) >= 500) {
                TnvedItem::query()->insert($batch);
                $imported += count($batch);
                $batch = [];
            }

            if (($index + 1) % 500 === 0 || ($index + 1) === $total) {
                $this->report($onProgress, [
                    'phase' => 'download_page',
                    'started_at' => $startedAt,
                    'imported' => $imported + count($batch),
                    'rows_total' => $total,
                    'pages_done' => $index + 1,
                    'pages_total' => $total,
                ]);
            }
        }

        if ($batch !== []) {
            TnvedItem::query()->insert($batch);
            $imported += count($batch);
        }

        if ($finalize) {
            return $this->finalizeImport(null, null, $imported, $onProgress, $startedAt);
        }

        return $imported;
    }

    private function importFromArchive(?callable $onProgress, float $startedAt): int
    {
        $archivePath = storage_path('app/tnved-archive.zip');
        $extractPath = storage_path('app/tnved-archive');

        File::ensureDirectoryExists(dirname($archivePath));
        File::deleteDirectory($extractPath);

        $this->report($onProgress, ['phase' => 'archive_download', 'started_at' => $startedAt]);

        $this->api->downloadArchive($archivePath);

        $this->report($onProgress, ['phase' => 'archive_extract', 'started_at' => $startedAt]);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Не удалось открыть ZIP-архив ТН ВЭД.');
        }

        File::ensureDirectoryExists($extractPath);
        $zip->extractTo($extractPath);
        $zip->close();

        return $this->importFromDirectory($extractPath, $onProgress, clear: false, finalize: false);
    }

    private function importFromCodeList(?callable $onProgress, float $startedAt): int
    {
        $codes = $this->api->getCodeList();
        $total = count($codes);
        $imported = 0;
        $batch = [];
        $concurrency = max(1, (int) config('tks.concurrency', 4));

        $this->report($onProgress, [
            'phase' => 'download_start',
            'started_at' => $startedAt,
            'rows_total' => $total,
            'pages_total' => $total,
        ]);

        foreach (array_chunk($codes, $concurrency) as $chunkIndex => $chunk) {
            foreach ($chunk as $code) {
                $payload = $this->api->getCode($code);
                $mapped = $this->mapper->map($payload);

                if ($mapped !== null) {
                    $batch[] = $mapped;
                }
            }

            if ($batch !== []) {
                TnvedItem::query()->insert($batch);
                $imported += count($batch);
                $batch = [];
            }

            $this->report($onProgress, [
                'phase' => 'download_page',
                'started_at' => $startedAt,
                'imported' => $imported,
                'rows_total' => $total,
                'pages_done' => min(($chunkIndex + 1) * $concurrency, $total),
                'pages_total' => $total,
            ]);
        }

        return $imported;
    }

    private function finalizeImport(
        ?string $versionNumber,
        ?string $versionDate,
        int $imported,
        ?callable $onProgress,
        float $startedAt,
    ): int {
        $this->report($onProgress, [
            'phase' => 'download_done',
            'started_at' => $startedAt,
            'imported' => $imported,
        ]);

        DB::transaction(function () use ($versionNumber, $versionDate, $imported, $onProgress, $startedAt) {
            $this->buildAncestorsPaths($onProgress, $startedAt);
            $this->markParentsWithChildren($onProgress, $startedAt);

            $this->report($onProgress, ['phase' => 'meta', 'started_at' => $startedAt]);

            TnvedMeta::query()->delete();
            TnvedMeta::query()->create([
                'version_number' => $versionNumber,
                'version_date' => $versionDate,
                'items_count' => $imported,
                'synced_at' => now(),
            ]);
        });

        $this->report($onProgress, [
            'phase' => 'done',
            'started_at' => $startedAt,
            'imported' => $imported,
            'elapsed' => microtime(true) - $startedAt,
        ]);

        return $imported;
    }

    private function buildAncestorsPaths(?callable $onProgress, float $startedAt): void
    {
        $items = TnvedItem::query()
            ->orderBy('level')
            ->get(['id', 'code', 'display_code', 'name', 'parent_code', 'ancestors_path']);

        $total = $items->count();
        $done = 0;

        $this->report($onProgress, [
            'phase' => 'ancestors_start',
            'started_at' => $startedAt,
            'items_total' => $total,
        ]);

        $byCode = $items->keyBy('code');

        foreach ($items as $item) {
            $parent = $item->parent_code ? $byCode->get($item->parent_code) : null;
            $path = TnvedItem::buildAncestorsPath($parent);

            if ($path !== $item->ancestors_path) {
                TnvedItem::query()->whereKey($item->id)->update(['ancestors_path' => $path]);
            }

            $item->ancestors_path = $path;
            $done++;

            if ($done % 2500 === 0 || $done === $total) {
                $this->report($onProgress, [
                    'phase' => 'ancestors',
                    'started_at' => $startedAt,
                    'items_done' => $done,
                    'items_total' => $total,
                ]);
            }
        }
    }

    private function markParentsWithChildren(?callable $onProgress, float $startedAt): void
    {
        $this->report($onProgress, ['phase' => 'parents', 'started_at' => $startedAt]);

        $parentCodes = TnvedItem::query()
            ->whereNotNull('parent_code')
            ->distinct()
            ->pluck('parent_code');

        TnvedItem::query()
            ->whereIn('code', $parentCodes)
            ->update(['has_children' => true]);
    }

    private function report(?callable $onProgress, array $state): void
    {
        if ($onProgress) {
            $onProgress($state);
        }
    }

    private function normalizeVersionValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
