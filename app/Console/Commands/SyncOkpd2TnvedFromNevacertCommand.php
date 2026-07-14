<?php

namespace App\Console\Commands;

use App\Models\Okpd2TnvedMapping;
use App\Services\NevacertApiClient;
use App\Services\Okpd2TnvedMappingImporter;
use Illuminate\Console\Command;

class SyncOkpd2TnvedFromNevacertCommand extends Command
{
    protected $signature = 'mapping:sync-nevacert
        {--replace : Очистить существующие соответствия перед импортом}
        {--refresh-nevacert : Удалить только записи nevacert и загрузить заново}
        {--max-pages= : Ограничить число страниц API (для отладки)}';

    protected $description = 'Загрузить переходные ключи ТН ВЭД ↔ ОКПД2 с nevacert.ru (type=13)';

    public function handle(NevacertApiClient $client, Okpd2TnvedMappingImporter $importer): int
    {
        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;
        $replace = (bool) $this->option('replace');
        $refreshNevacert = (bool) $this->option('refresh-nevacert');

        if ($replace) {
            Okpd2TnvedMapping::query()->delete();
            $this->components->warn('Существующие соответствия удалены (--replace).');
        } elseif ($refreshNevacert) {
            $deleted = Okpd2TnvedMapping::query()->where('source', 'nevacert:tnved-okpd2')->delete();
            $this->components->warn('Удалено nevacert-записей: '.number_format($deleted, 0, ',', ' '));
        }

        $this->line('Загрузка ключей с https://nevacert.ru/api/okdp-okdp2/search (type=13)');
        $this->newLine();

        $bar = null;
        $imported = 0;
        $skipped = 0;
        $buffer = [];

        foreach ($client->fetchTnvedOkpd2Mappings(function (int $page, int $lastPage) use (&$bar) {
            if ($bar === null) {
                $bar = $this->output->createProgressBar($lastPage);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | стр. %current%');
                $bar->start();
            }

            $bar->setProgress($page);
        }, $maxPages) as $batch) {
            $buffer = array_merge($buffer, $batch);

            if (count($buffer) >= 500) {
                $result = $importer->import($buffer, 'nevacert:tnved-okpd2', false, bulk: true);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $result = $importer->import($buffer, 'nevacert:tnved-okpd2', false, bulk: true);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        if ($bar) {
            $bar->finish();
            $this->newLine(2);
        }

        $unique = Okpd2TnvedMapping::query()->count();

        $this->components->info(
            'Обработано: '.number_format($imported, 0, ',', ' ')
            .', пропущено: '.number_format($skipped, 0, ',', ' ')
            .', уникальных в базе: '.number_format($unique, 0, ',', ' ')
            .', из них nevacert: '.number_format(Okpd2TnvedMapping::query()->where('source', 'nevacert:tnved-okpd2')->count(), 0, ',', ' ')
        );

        return self::SUCCESS;
    }
}
