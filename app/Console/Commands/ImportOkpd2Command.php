<?php

namespace App\Console\Commands;

use App\Services\Okpd2SyncService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportOkpd2Command extends Command
{
    protected $signature = 'okpd2:import
        {file : Путь к JSON или CSV, скачанному с data.mos.ru}';

    protected $description = 'Импортировать ОКПД 2 из локального файла (без API)';

    private ?ProgressBar $insertBar = null;

    private ?ProgressBar $ancestorsBar = null;

    public function handle(Okpd2SyncService $syncService): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Файл не найден: {$file}");

            return self::FAILURE;
        }

        $this->info("Импорт ОКПД 2 из файла: {$file}");
        $this->newLine();

        $imported = $syncService->importFromFile(
            $file,
            fn (array $state) => $this->handleProgress($state),
        );

        $this->finishBars();
        $this->newLine(2);
        $this->components->info("Импортировано записей: {$imported}");

        return self::SUCCESS;
    }

    private function handleProgress(array $state): void
    {
        match ($state['phase']) {
            'file_read' => $this->line('  <fg=gray>1/4</> Чтение файла...'),
            'file_read_done' => $this->line('       Найдено записей: <info>'.number_format($state['rows_total'], 0, ',', ' ').'</info>'),
            'clear' => $this->line('  <fg=gray>2/4</> Очистка локальной базы...'),
            'insert_start' => $this->startInsertBar($state),
            'insert_chunk' => $this->advanceInsertBar($state),
            'ancestors_start' => $this->startAncestorsBar($state),
            'ancestors' => $this->advanceAncestorsBar($state),
            'parents' => $this->line('  <fg=gray>4/4</> Отметка родительских кодов...'),
            'meta' => $this->line('       Сохранение метаданных...'),
            'done' => $this->showSummary($state),
            default => null,
        };
    }

    private function startInsertBar(array $state): void
    {
        $this->line('  <fg=gray>3/4</> Запись в базу:');

        $this->insertBar = $this->output->createProgressBar($state['chunks_total']);
        $this->insertBar->setFormat('       %current%/%max% [%bar%] %percent:3s%% | %message%');
        $this->insertBar->setMessage('вставка...');
        $this->insertBar->start();
    }

    private function advanceInsertBar(array $state): void
    {
        if ($this->insertBar === null) {
            return;
        }

        $this->insertBar->setProgress($state['chunk']);
        $this->insertBar->setMessage(
            number_format($state['imported'], 0, ',', ' ').' / '.number_format($state['rows_total'], 0, ',', ' ').' записей'
        );

        if ($state['chunk'] >= $state['chunks_total']) {
            $this->insertBar->finish();
            $this->newLine();
            $this->insertBar = null;
        }
    }

    private function startAncestorsBar(array $state): void
    {
        $this->ancestorsBar = $this->output->createProgressBar($state['items_total']);
        $this->ancestorsBar->setFormat('       %current%/%max% [%bar%] %percent:3s%% | пути предков');
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

        $this->components->twoColumnDetail('Время', "{$elapsed} с");
        $this->components->twoColumnDetail('Импортировано', $imported);
    }

    private function finishBars(): void
    {
        if ($this->insertBar) {
            $this->insertBar->finish();
            $this->insertBar = null;
        }

        if ($this->ancestorsBar) {
            $this->ancestorsBar->finish();
            $this->ancestorsBar = null;
        }
    }
}
