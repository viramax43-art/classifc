<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('okpd2 {--c|concurrency= : Параллельных запросов} {--sequential : Без параллелизма} {--force : Принудительное обновление} {--file= : Импорт из локального JSON/CSV}', function () {
    if ($this->option('file')) {
        return $this->call('okpd2:import', ['file' => $this->option('file')]);
    }

    $options = [];

    if ($this->option('concurrency') !== null) {
        $options['--concurrency'] = $this->option('concurrency');
    }

    if ($this->option('sequential')) {
        $options['--sequential'] = true;
    }

    if ($this->option('force')) {
        $options['--force'] = true;
    }

    return $this->call('okpd2:sync', $options);
})->purpose('Синхронизировать ОКПД 2 или импортировать из файла');

Artisan::command('tnved {--force : Принудительное обновление} {--no-archive : Без ZIP-архива} {--dir= : Импорт из папки JSON}', function () {
    $options = [];

    if ($this->option('force')) {
        $options['--force'] = true;
    }

    if ($this->option('no-archive')) {
        $options['--no-archive'] = true;
    }

    if ($this->option('dir')) {
        $options['--dir'] = $this->option('dir');
    }

    return $this->call('tnved:sync', $options);
})->purpose('Синхронизировать ТН ВЭД из API TKS');

Artisan::command('mapping:alta {--replace : Очистить перед импортом} {--file= : Локальный HTML/MD} {--url= : URL alta.ru}', function () {
    $options = ['--replace' => (bool) $this->option('replace')];

    if ($this->option('file')) {
        $options['--file'] = $this->option('file');
    }

    if ($this->option('url')) {
        $options['--url'] = $this->option('url');
    }

    return $this->call('mapping:sync-alta', $options);
})->purpose('Обновить соответствия ОКПД2 ↔ ТН ВЭД из alta.ru');

Artisan::command('mapping:nevacert {--replace : Очистить перед импортом} {--refresh-nevacert : Перезагрузить только nevacert} {--max-pages= : Лимит страниц}', function () {
    $options = [];

    if ($this->option('replace')) {
        $options['--replace'] = true;
    }

    if ($this->option('refresh-nevacert')) {
        $options['--refresh-nevacert'] = true;
    }

    if ($this->option('max-pages') !== null) {
        $options['--max-pages'] = $this->option('max-pages');
    }

    return $this->call('mapping:sync-nevacert', $options);
})->purpose('Загрузить ключи ТН ВЭД ↔ ОКПД2 с nevacert.ru');
