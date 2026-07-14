<?php

namespace App\Console\Commands;

use App\Services\AltaTamdocClient;
use App\Services\AltaTamdocMappingParser;
use App\Services\Okpd2TnvedMappingImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncOkpd2TnvedFromAltaCommand extends Command
{
    protected $signature = 'mapping:sync-alta
        {--url=https://www.alta.ru/tamdoc/22bn0218/ : URL документа на alta.ru}
        {--file= : Локальный HTML или MD файл вместо загрузки}
        {--replace : Очистить существующие соответствия перед импортом}';

    protected $description = 'Обновить соответствия ОКПД2 ↔ ТН ВЭД из alta.ru (22bn0218)';

    public function handle(
        AltaTamdocClient $client,
        AltaTamdocMappingParser $parser,
        Okpd2TnvedMappingImporter $importer,
    ): int {
        $file = $this->option('file');

        if ($file) {
            if (! is_file($file)) {
                $this->error("Файл не найден: {$file}");

                return self::FAILURE;
            }

            $this->line("Чтение файла: {$file}");
            $content = File::get($file);
        } else {
            $url = (string) $this->option('url');
            $this->line("Загрузка: {$url}");
            $content = $client->fetch($url);
        }

        $rows = str_contains($content, 'ordw-table-1')
            ? $parser->parseHtml($content)
            : $parser->parseText($content);

        if ($rows === []) {
            $this->error('Не удалось извлечь соответствия из документа.');

            return self::FAILURE;
        }

        $this->line('Найдено пар в документе: '.number_format(count($rows), 0, ',', ' '));

        $result = $importer->import($rows, 'alta:22bn0218', (bool) $this->option('replace'));

        $this->components->info(
            'Импортировано: '.number_format($result['imported'], 0, ',', ' ')
            .', пропущено: '.$result['skipped']
            .', уникальных в базе: '.number_format($result['unique'], 0, ',', ' ')
        );

        return self::SUCCESS;
    }
}
