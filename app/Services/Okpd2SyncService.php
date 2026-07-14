<?php

namespace App\Services;

use App\Models\Okpd2Item;
use App\Models\Okpd2Meta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

class Okpd2SyncService
{
    public function __construct(
        private readonly DataMosApiClient $api,
    ) {}

    public function sync(?callable $onProgress = null, bool $parallel = true, ?int $concurrency = null): int
    {
        $startedAt = microtime(true);
        $pageSize = (int) config('datamos.page_size', 1000);
        $concurrency = max(1, $concurrency ?? (int) config('datamos.concurrency', 6));

        $this->report($onProgress, [
            'phase' => 'metadata',
            'started_at' => $startedAt,
        ]);

        $dataset = $this->api->getDataset();
        $total = (int) ($dataset['ItemsCount'] ?? $this->api->getCount());

        $skips = [];

        for ($page = 0; $page * $pageSize < $total; $page++) {
            $skips[] = $page * $pageSize + 1;
        }

        $pageCount = count($skips);
        $wavesTotal = (int) ceil($pageCount / $concurrency);

        $this->report($onProgress, [
            'phase' => 'metadata_done',
            'started_at' => $startedAt,
            'dataset' => $dataset,
            'rows_total' => $total,
            'page_size' => $pageSize,
            'pages_total' => $pageCount,
            'waves_total' => $wavesTotal,
            'concurrency' => $concurrency,
            'parallel' => $parallel,
            'base_url' => $this->api->getActiveBaseUrl(),
        ]);

        $this->report($onProgress, ['phase' => 'clear', 'started_at' => $startedAt]);

        Okpd2Item::query()->delete();

        $this->report($onProgress, [
            'phase' => 'download_start',
            'started_at' => $startedAt,
            'pages_total' => $pageCount,
            'rows_total' => $total,
        ]);

        $imported = $parallel
            ? $this->importParallel($skips, $pageSize, $concurrency, $onProgress, $total, $startedAt)
            : $this->importSequential($skips, $pageSize, $onProgress, $total, $startedAt);

        return $this->finalizeImport($dataset, $imported, $onProgress, $startedAt, $pageCount, $total);
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
        $dataset = $this->api->getDataset();
        $localMeta = Okpd2Meta::query()->first();
        $isEmpty = ! Okpd2Item::query()->exists();

        $remoteVersionNumber = $this->normalizeVersionValue($dataset['VersionNumber'] ?? null);
        $remoteVersionDate = $this->normalizeVersionValue($dataset['VersionDate'] ?? null);
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

    public function importFromFile(string $path, ?callable $onProgress = null): int
    {
        $startedAt = microtime(true);

        $this->report($onProgress, [
            'phase' => 'file_read',
            'started_at' => $startedAt,
            'path' => $path,
        ]);

        $batch = Okpd2RowMapper::fromFile($path);

        $this->report($onProgress, [
            'phase' => 'file_read_done',
            'started_at' => $startedAt,
            'path' => $path,
            'rows_total' => count($batch),
        ]);

        $this->report($onProgress, ['phase' => 'clear', 'started_at' => $startedAt]);

        Okpd2Item::query()->delete();

        $this->report($onProgress, [
            'phase' => 'insert_start',
            'started_at' => $startedAt,
            'rows_total' => count($batch),
        ]);

        $imported = 0;
        $chunks = array_chunk($batch, 1000);
        $chunkTotal = count($chunks);

        foreach ($chunks as $index => $chunk) {
            Okpd2Item::query()->insert($chunk);
            $imported += count($chunk);

            $this->report($onProgress, [
                'phase' => 'insert_chunk',
                'started_at' => $startedAt,
                'chunk' => $index + 1,
                'chunks_total' => $chunkTotal,
                'imported' => $imported,
                'rows_total' => count($batch),
            ]);
        }

        return $this->finalizeImport(null, $imported, $onProgress, $startedAt, 0, count($batch), $path);
    }

    private function finalizeImport(
        ?array $dataset,
        int $imported,
        ?callable $onProgress,
        float $startedAt,
        int $pageCount,
        int $total,
        ?string $sourceFile = null,
    ): int {
        $this->report($onProgress, [
            'phase' => 'download_done',
            'started_at' => $startedAt,
            'imported' => $imported,
            'pages_total' => $pageCount,
            'rows_total' => $total,
        ]);

        DB::transaction(function () use ($dataset, $imported, $onProgress, $startedAt, $sourceFile) {
            $this->buildAncestorsPaths($onProgress, $startedAt);
            $this->markParentsWithChildren($onProgress, $startedAt);

            $this->report($onProgress, ['phase' => 'meta', 'started_at' => $startedAt]);

            Okpd2Meta::query()->delete();
            Okpd2Meta::query()->create([
                'version_number' => $dataset['VersionNumber'] ?? ($sourceFile ? basename($sourceFile) : null),
                'version_date' => $dataset['VersionDate'] ?? null,
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

    /**
     * @param  list<int>  $skips
     */
    private function importParallel(
        array $skips,
        int $pageSize,
        int $concurrency,
        ?callable $onProgress,
        int $total,
        float $startedAt,
    ): int {
        $imported = 0;
        $pagesDone = 0;
        $pageCount = count($skips);
        $wavesTotal = (int) ceil($pageCount / $concurrency);
        $waveIndex = 0;

        foreach (array_chunk($skips, $concurrency) as $chunk) {
            $waveIndex++;

            $this->report($onProgress, [
                'phase' => 'download_wave',
                'started_at' => $startedAt,
                'wave' => $waveIndex,
                'waves_total' => $wavesTotal,
                'skips' => $chunk,
                'pages_done' => $pagesDone,
                'pages_total' => $pageCount,
                'imported' => $imported,
                'rows_total' => $total,
            ]);

            $waveStarted = microtime(true);
            $pages = $this->fetchWavePages($chunk, $pageSize, $onProgress, $startedAt, $waveIndex, $wavesTotal);
            $waveSeconds = microtime(true) - $waveStarted;

            foreach ($pages as $skip => $rows) {
                $batch = $this->mapRowsToBatch($rows);
                $batchSize = count($batch);

                if ($batch !== []) {
                    Okpd2Item::query()->insert($batch);
                    $imported += $batchSize;
                }

                $pagesDone++;

                $this->report($onProgress, [
                    'phase' => 'download_page',
                    'started_at' => $startedAt,
                    'skip' => $skip,
                    'batch_size' => $batchSize,
                    'wave' => $waveIndex,
                    'waves_total' => $wavesTotal,
                    'wave_seconds' => $waveSeconds,
                    'pages_done' => $pagesDone,
                    'pages_total' => $pageCount,
                    'imported' => $imported,
                    'rows_total' => $total,
                ]);
            }
        }

        return $imported;
    }

    /**
     * @param  list<int>  $chunk
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchWavePages(
        array $chunk,
        int $pageSize,
        ?callable $onProgress,
        float $startedAt,
        int $waveIndex,
        int $wavesTotal,
    ): array {
        if (count($chunk) === 1) {
            $skip = $chunk[0];

            return [$skip => $this->api->getRows($skip, $pageSize)];
        }

        try {
            return $this->api->getRowsConcurrent($chunk, $pageSize);
        } catch (ConnectionException|RequestException) {
            $this->report($onProgress, [
                'phase' => 'download_wave_fallback',
                'started_at' => $startedAt,
                'wave' => $waveIndex,
                'waves_total' => $wavesTotal,
                'skips' => $chunk,
            ]);

            $pages = [];

            foreach ($chunk as $skip) {
                $pages[$skip] = $this->api->getRows($skip, $pageSize);
            }

            return $pages;
        }
    }

    /**
     * @param  list<int>  $skips
     */
    private function importSequential(
        array $skips,
        int $pageSize,
        ?callable $onProgress,
        int $total,
        float $startedAt,
    ): int {
        $imported = 0;
        $pagesDone = 0;
        $pageCount = count($skips);

        foreach ($skips as $skip) {
            $pageStarted = microtime(true);
            $rows = $this->api->getRows($skip, $pageSize);
            $pageSeconds = microtime(true) - $pageStarted;

            $batch = $this->mapRowsToBatch($rows);
            $batchSize = count($batch);

            if ($batch !== []) {
                Okpd2Item::query()->insert($batch);
                $imported += $batchSize;
            }

            $pagesDone++;

            $this->report($onProgress, [
                'phase' => 'download_page',
                'started_at' => $startedAt,
                'skip' => $skip,
                'batch_size' => $batchSize,
                'page_seconds' => $pageSeconds,
                'pages_done' => $pagesDone,
                'pages_total' => $pageCount,
                'imported' => $imported,
                'rows_total' => $total,
            ]);
        }

        return $imported;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRowsToBatch(array $rows): array
    {
        $batch = [];

        foreach ($rows as $row) {
            $mapped = Okpd2RowMapper::fromApiRow($row);

            if ($mapped !== null) {
                $batch[] = $mapped;
            }
        }

        return $batch;
    }

    private function buildAncestorsPaths(?callable $onProgress, float $startedAt): void
    {
        $items = Okpd2Item::query()
            ->orderBy('level')
            ->get(['id', 'code', 'name', 'parent_code', 'ancestors_path']);

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
            $path = Okpd2Item::buildAncestorsPath($parent);

            if ($path !== $item->ancestors_path) {
                Okpd2Item::query()->whereKey($item->id)->update(['ancestors_path' => $path]);
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

        $parentCodes = Okpd2Item::query()
            ->whereNotNull('parent_code')
            ->distinct()
            ->pluck('parent_code');

        Okpd2Item::query()
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
