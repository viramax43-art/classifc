<?php

namespace App\Console\Commands;

use App\Services\DataMosApiClient;
use App\Services\Okpd2SyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class CheckOkpd2Command extends Command
{
    protected $signature = 'okpd2:check';

    protected $description = 'Проверить доступность API data.mos.ru и DNS';

    public function handle(DataMosApiClient $api): int
    {
        $host = 'apidata.mos.ru';

        $this->info('Диагностика подключения к API Москвы');
        $this->newLine();

        $ips = gethostbynamel($host) ?: [];
        $this->line('  DNS '.($ips !== [] ? '<info>OK</info>' : '<fg=red>FAIL</info>')."  {$host}");
        $this->line('       '.($ips !== [] ? implode(', ', $ips) : 'хост не резолвится'));
        $this->newLine();

        $this->line('  Проверка базовых URL:');

        $anyOk = false;

        foreach ($api->getBaseUrls() as $baseUrl) {
            $result = $api->probeBaseUrl($baseUrl);

            if ($result['ok']) {
                $this->line("       {$baseUrl}  <info>OK</info> HTTP {$result['status']} ({$result['elapsed_ms']} ms)");
                $anyOk = true;
            } else {
                $this->line("       {$baseUrl}  <error>FAIL</error> {$result['error']} ({$result['elapsed_ms']} ms)");
            }
        }

        $this->newLine();

        if ($anyOk) {
            $this->components->info('API доступен. Запустите: php artisan okpd2');

            return self::SUCCESS;
        }

        $this->components->error('API недоступен: TCP-соединение с сервером не устанавливается.');
        $this->line('Это не связано с таймаутом ответа и не исправляется DATAMOS_TIMEOUT.');
        $this->newLine();
        $this->line('Варианты:');
        $this->line('  1. Другая сеть / VPN / корпоративный прокси');
        $this->line('  2. Скачайте JSON/CSV с https://data.mos.ru/opendata/2752');
        $this->line('  3. Импортируйте файл: php artisan okpd2:import storage/okpd2.json');

        return self::FAILURE;
    }
}
