<?php

namespace App\Console\Commands;

use App\Services\TnvedSyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncTnvedCommand extends Command
{
    protected $signature = 'tnved:sync
        {--force : Принудительно обновить, даже если версия не изменилась}
        {--no-archive : Загружать коды по одному через API вместо ZIP-архива}
        {--dir= : Импорт из локальной папки с JSON-файлами}';

    protected $description = 'Загрузить классификатор ТН ВЭД из API TKS (archive.zip)';

    private ?ProgressBar $progressBar = null;

    public function handle(TnvedSyncService $syncService): int
    {
        if ($dir = $this->option('dir')) {
            return $this->importFromDirectory($syncService, $dir);
        }

        $this->info('Проверка актуальности ТН ВЭД на api1.tks.ru');
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

                return self::SUCCESS;
            }

            $this->newLine();
            $this->line('Запуск синхронизации...');
            $this->newLine();

            $imported = $syncService->sync(
                fn (array $state) => $this->handleProgress($state),
                ! $this->option('no-archive'),
            );
        } catch (ConnectionException) {
            $this->abortBar();
            $this->newLine(2);
            $this->error('Соединение с API TKS прервано.');
            $this->line('Попробуйте: php artisan tnved:sync --no-archive');

            return self::FAILURE;
        } catch (RequestException $e) {
            $this->abortBar();
            $this->newLine(2);
            $status = $e->response?->status();
            $this->error('Ошибка API TKS'.($status ? " (HTTP {$status})" : '').'.');
            $this->line(mb_substr($e->response?->body() ?? $e->getMessage(), 0, 300));

            return self::FAILURE;
        }

        $this->finishBar();
        $this->newLine(2);
        $this->components->info("Импортировано записей: {$imported}");

        return self::SUCCESS;
    }

    private function importFromDirectory(TnvedSyncService $syncService, string $dir): int
    {
        if (! is_dir($dir)) {
            $this->error("Папка не найдена: {$dir}");

            return self::FAILURE;
        }

        $imported = $syncService->importFromDirectory($dir, fn (array $state) => $this->handleProgress($state), clear: true, finalize: true);

        $this->newLine(2);
        $this->components->info("Импортировано записей: {$imported}");

        return self::SUCCESS;
    }

    private function handleProgress(array $state): void
    {
        match ($state['phase']) {
            'metadata' => $this->line('  <fg=gray>1/4</> Получение версии TKS...'),
            'metadata_done' => $this->line('       Версия: <info>'.($state['version_number'] ?? '—').'</info> от '.($state['version_date'] ?? '—')),
            'clear' => $this->line('  <fg=gray>2/4</> Очистка локальной базы...'),
            'archive_download' => $this->line('  <fg=gray>3/4</> Скачивание archive.zip...'),
            'archive_extract' => $this->line('       Распаковка архива...'),
            'download_start' => $this->startBar($state['rows_total'] ?? $state['pages_total'] ?? 0),
            'download_page' => $this->advanceBar($state),
            'download_done' => $this->finishBar(),
            'ancestors_start' => $this->line('  <fg=gray>4/4</> Постобработка: пути предков...'),
            'ancestors' => null,
            'parents' => $this->line('       Отметка родительских кодов...'),
            'meta' => $this->line('       Сохранение версии...'),
            'done' => $this->showSummary($state),
            default => null,
        };
    }

    private function startBar(int $total): void
    {
        if ($total <= 0) {
            return;
        }

        $this->progressBar = $this->output->createProgressBar($total);
        $this->progressBar->start();
    }

    private function advanceBar(array $state): void
    {
        if ($this->progressBar === null) {
            return;
        }

        $done = $state['pages_done'] ?? $state['imported'] ?? 0;
        $this->progressBar->setProgress(min($done, $this->progressBar->getMaxSteps()));
    }

    private function finishBar(): void
    {
        if ($this->progressBar === null) {
            return;
        }

        $this->progressBar->finish();
        $this->newLine();
        $this->progressBar = null;
    }

    private function abortBar(): void
    {
        if ($this->progressBar) {
            $this->progressBar->clear();
            $this->progressBar = null;
        }
    }

    private function showSummary(array $state): void
    {
        $elapsed = round($state['elapsed'] ?? 0, 1);
        $imported = number_format($state['imported'] ?? 0, 0, ',', ' ');

        $this->components->twoColumnDetail('Время', "{$elapsed} с");
        $this->components->twoColumnDetail('Импортировано', $imported);
    }
}
