<?php

namespace App\Console\Commands;

use App\Services\Okpd2SyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncOkpd2Command extends Command
{
    protected $signature = 'okpd2:sync
        {--c|concurrency= : Параллельных HTTP-запросов к API (по умолчанию из DATAMOS_CONCURRENCY)}
        {--sequential : Последовательная загрузка без параллелизма}
        {--force : Принудительно обновить, даже если версия не изменилась}';

    protected $description = 'Загрузить классификатор ОКПД 2 из API data.mos.ru (параллельный режим по умолчанию)';

    private ?ProgressBar $downloadBar = null;

    private ?ProgressBar $ancestorsBar = null;

    public function handle(Okpd2SyncService $syncService): int
    {
        $parallel = ! $this->option('sequential');
        $concurrency = $this->option('concurrency') !== null
            ? max(1, (int) $this->option('concurrency'))
            : null;

        $mode = $parallel
            ? 'параллельно, '.($concurrency ?? (int) config('datamos.concurrency', 6)).' потоков'
            : 'последовательно';

        $this->info("Проверка актуальности ОКПД 2 на data.mos.ru ({$mode})");
        $this->newLine();

        try {
            $updateCheck = $syncService->checkForUpdates();
            $remoteVersion = $updateCheck['remote_version_number'] ?? '—';
            $remoteDate = $updateCheck['remote_version_date'] ?? '—';
            $localVersion = $updateCheck['local_version_number'] ?? '—';
            $localDate = $updateCheck['local_version_date'] ?? '—';

            $this->line("Удаленная версия: <info>{$remoteVersion}</info> от {$remoteDate}");
            $this->line("Локальная версия: <info>{$localVersion}</info> от {$localDate}");

            if (! $this->option('force') && ! $updateCheck['should_sync']) {
                $this->newLine();
                $this->components->info('Данные уже актуальны. Импорт пропущен.');
                $this->line('Для принудительного обновления: php artisan okpd2 --force');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->line('Запуск синхронизации...');
            $this->newLine();

            $imported = $syncService->sync(
                fn (array $state) => $this->handleProgress($state),
                $parallel,
                $concurrency,
            );
        } catch (ConnectionException) {
            $this->abortBars();
            $this->newLine(2);
            $this->error('Соединение с API прервано при загрузке страниц.');
            $this->line('Попробуйте: php artisan okpd2 --sequential');
            $this->line('Или импорт из файла: php artisan okpd2:import storage/okpd2.json');

            return self::FAILURE;
        } catch (RequestException $e) {
            $this->abortBars();
            $this->newLine(2);
            $status = $e->response?->status();
            $this->error('Ошибка API data.mos.ru'.($status ? " (HTTP {$status})" : '').'.');
            $this->line(mb_substr($e->response?->body() ?? $e->getMessage(), 0, 300));
            $this->newLine();
            $this->line('Попробуйте меньше потоков: php artisan okpd2 -c 2');

            return self::FAILURE;
        }

        $this->finishBars();
        $this->newLine(2);
        $this->components->info("Импортировано записей: {$imported}");

        return self::SUCCESS;
    }

    private function handleProgress(array $state): void
    {
        match ($state['phase']) {
            'metadata' => $this->line('  <fg=gray>1/5</> Получение метаданных набора #'.config('datamos.dataset_id').'...'),
            'metadata_done' => $this->showMetadata($state),
            'clear' => $this->line('  <fg=gray>2/5</> Очистка локальной базы...'),
            'download_start' => $this->startDownloadBar($state),
            'download_wave' => $this->showWave($state),
            'download_wave_fallback' => $this->showWaveFallback($state),
            'download_page' => $this->advanceDownloadBar($state),
            'download_done' => $this->finishDownloadBar($state),
            'ancestors_start' => $this->startAncestorsBar($state),
            'ancestors' => $this->advanceAncestorsBar($state),
            'parents' => $this->line('       Отметка родительских кодов...'),
            'meta' => $this->line('  <fg=gray>5/5</> Сохранение версии классификатора...'),
            'done' => $this->showSummary($state),
            default => null,
        };
    }

    private function showMetadata(array $state): void
    {
        $dataset = $state['dataset'] ?? [];
        $version = $dataset['VersionNumber'] ?? '—';
        $versionDate = $dataset['VersionDate'] ?? '—';
        $rowsTotal = number_format($state['rows_total'], 0, ',', ' ');
        $pagesTotal = $state['pages_total'];
        $pageSize = $state['page_size'];
        $mode = ($state['parallel'] ?? true)
            ? "параллельно ×{$state['concurrency']} ({$state['waves_total']} волн)"
            : 'последовательно';

        $this->line("       Версия: <info>{$version}</info> от {$versionDate}");
        $this->line("       API: <info>{$state['base_url']}</info>");
        $this->line("       Записей в API: <info>{$rowsTotal}</info> · страниц по {$pageSize}: <info>{$pagesTotal}</info>");
        $this->line("       Режим загрузки: <info>{$mode}</info>");
        $this->newLine();
    }

    private function startDownloadBar(array $state): void
    {
        $this->line('  <fg=gray>3/5</> Загрузка страниц из API:');

        $this->downloadBar = $this->output->createProgressBar($state['pages_total']);
        $this->downloadBar->setBarCharacter('█');
        $this->downloadBar->setEmptyBarCharacter('░');
        $this->downloadBar->setProgressCharacter('█');
        $this->downloadBar->setFormat(
            "       %current%/%max% [%bar%] %percent:3s%% | %message%"
        );
        $this->downloadBar->setMessage('подготовка...');
        $this->downloadBar->start();
    }

    private function showWave(array $state): void
    {
        if ($this->downloadBar === null) {
            return;
        }

        $from = min($state['skips']);
        $to = max($state['skips']) + (int) config('datamos.page_size', 1000) - 1;
        $wave = $state['wave'];
        $wavesTotal = $state['waves_total'];

        $this->downloadBar->setMessage("волна {$wave}/{$wavesTotal}: запрос записей {$from}–{$to}...");
    }

    private function showWaveFallback(array $state): void
    {
        if ($this->downloadBar === null) {
            return;
        }

        $wave = $state['wave'];
        $wavesTotal = $state['waves_total'];

        $this->downloadBar->setMessage("волна {$wave}/{$wavesTotal}: параллель не удался, по одной странице...");
    }

    private function advanceDownloadBar(array $state): void
    {
        if ($this->downloadBar === null) {
            return;
        }

        $elapsed = max(0.001, microtime(true) - $state['started_at']);
        $imported = $state['imported'];
        $rowsTotal = $state['rows_total'];
        $speed = (int) round($imported / $elapsed);
        $percentRows = $rowsTotal > 0 ? round($imported / $rowsTotal * 100, 1) : 0;
        $eta = $this->formatEta($imported, $rowsTotal, $elapsed);

        $pageInfo = isset($state['wave_seconds'])
            ? sprintf('волна %d: %.1f с', $state['wave'], $state['wave_seconds'])
            : sprintf('стр. %d: %.1f с', ($state['skip'] ?? 0) + 1, $state['page_seconds'] ?? 0);

        $message = sprintf(
            '%s записей (%s%%) · %d зап/с · ETA %s · +%d · %s',
            number_format($imported, 0, ',', ' '),
            $percentRows,
            $speed,
            $eta,
            $state['batch_size'],
            $pageInfo,
        );

        $this->downloadBar->setProgress($state['pages_done']);
        $this->downloadBar->setMessage($message);
    }

    private function finishDownloadBar(array $state): void
    {
        if ($this->downloadBar === null) {
            return;
        }

        $elapsed = microtime(true) - $state['started_at'];
        $imported = number_format($state['imported'], 0, ',', ' ');
        $seconds = round($elapsed, 1);

        $this->downloadBar->setMessage("готово: {$imported} записей за {$seconds} с");
        $this->downloadBar->finish();
        $this->newLine();
        $this->downloadBar = null;
    }

    private function startAncestorsBar(array $state): void
    {
        $this->line('  <fg=gray>4/5</> Постобработка: пути предков...');

        $total = $state['items_total'];

        $this->ancestorsBar = $this->output->createProgressBar($total);
        $this->ancestorsBar->setFormat(
            '       %current%/%max% [%bar%] %percent:3s%% | построение путей предков'
        );
        $this->ancestorsBar->start();
    }

    private function advanceAncestorsBar(array $state): void
    {
        if ($this->ancestorsBar === null) {
            return;
        }

        $this->ancestorsBar->setProgress($state['items_done']);

        if ($state['items_done'] >= $state['items_total']) {
            $this->ancestorsBar->finish();
            $this->newLine();
            $this->ancestorsBar = null;
        }
    }

    private function showSummary(array $state): void
    {
        $elapsed = round($state['elapsed'], 1);
        $imported = number_format($state['imported'], 0, ',', ' ');
        $avgSpeed = $state['elapsed'] > 0
            ? number_format((int) round($state['imported'] / $state['elapsed']), 0, ',', ' ')
            : '0';

        $this->components->twoColumnDetail('Время', "{$elapsed} с");
        $this->components->twoColumnDetail('Скорость', "{$avgSpeed} записей/с");
        $this->components->twoColumnDetail('Импортировано', $imported);
    }

    private function formatEta(int $done, int $total, float $elapsed): string
    {
        if ($done <= 0 || $total <= $done) {
            return '—';
        }

        $remaining = ($elapsed / $done) * ($total - $done);

        if ($remaining < 60) {
            return round($remaining).' с';
        }

        if ($remaining < 3600) {
            return round($remaining / 60).' мин';
        }

        return round($remaining / 3600, 1).' ч';
    }

    private function finishBars(): void
    {
        if ($this->downloadBar) {
            $this->downloadBar->finish();
            $this->downloadBar = null;
        }

        if ($this->ancestorsBar) {
            $this->ancestorsBar->finish();
            $this->ancestorsBar = null;
        }
    }

    private function abortBars(): void
    {
        if ($this->downloadBar) {
            $this->downloadBar->clear();
            $this->downloadBar = null;
        }

        if ($this->ancestorsBar) {
            $this->ancestorsBar->clear();
            $this->ancestorsBar = null;
        }
    }
}
