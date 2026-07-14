<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = config('tks.api_key');
$base = rtrim((string) config('tks.tree_base_url', 'https://api1.tks.ru/tree.json/json'), '/');

$urls = [
    "{$base}/{$key}/00000010.json",
    "{$base}/{$key}/00004000.json",
    "{$base}/{$key}/search/?code=0101210000",
];

foreach ($urls as $url) {
    echo "\n=== {$url} ===\n";
    $r = Illuminate\Support\Facades\Http::timeout(30)->get($url);
    echo "status={$r->status()}\n";
    echo substr($r->body(), 0, 1200)."\n";
}
