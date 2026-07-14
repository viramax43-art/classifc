<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;
use Illuminate\Support\Facades\Http;

$raw = $argv[1] ?? '30.12.2019';
$parts = explode('.', preg_replace('/[^0-9\.]/', '', $raw));
$candidates = [$raw];

if (count($parts) === 3 && strlen($parts[2]) === 4) {
    $y = $parts[2];
    $candidates[] = sprintf('%02d.%02d.%02d', (int) $parts[0], (int) $parts[1], (int) substr($y, 0, 2));
    $candidates[] = sprintf('%02d.%02d.%02d', (int) $parts[0], (int) $parts[1], (int) substr($y, 2, 2));
    $candidates[] = sprintf('%02d.%02d.%s', (int) $parts[0], (int) $parts[1], ltrim(substr($y, 2, 2), '0') ?: '0');
    $candidates[] = sprintf('%02d.%02d.%s', (int) $parts[0], (int) $parts[1], substr($y, 0, 2));
}

foreach (array_unique($candidates) as $c) {
    $hit = Okpd2Item::query()->where('code', $c)->exists();
    echo ($hit ? '[Y] ' : '[ ] ').$c.PHP_EOL;
}

// sample API record
$r = Http::timeout(30)->get('https://nevacert.ru/api/okdp-okdp2/search', ['type' => 13, 'page' => 1])->json();
echo PHP_EOL.json_encode($r['data'][0] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
