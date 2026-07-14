<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$page = 1;
$sortIndex = null;
$emptySamples = [];

while (count($emptySamples) < 10 && $page <= 50) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }

    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();

    foreach ($response['data'] ?? [] as $item) {
        if (trim((string) ($item['code_of_type2'] ?? '')) === '') {
            $emptySamples[] = $item;
            if (count($emptySamples) >= 10) {
                break 2;
            }
        }
    }

    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

echo json_encode($emptySamples, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
