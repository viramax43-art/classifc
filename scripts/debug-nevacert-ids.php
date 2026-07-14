<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$maxPages = (int) ($argv[1] ?? 300);
$page = 1;
$lastPage = 1;
$sortIndex = null;
$seenApiIds = [];
$duplicateApiIds = 0;
$totalItems = 0;
$emptyPages = 0;
$firstIdByPage = [];

while ($page <= $lastPage && $page <= $maxPages) {
    $query = ['type' => 13, 'page' => $page];

    if ($sortIndex !== null) {
        $query['sort_index'] = is_string($sortIndex)
            ? $sortIndex
            : json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }

    $response = Http::timeout(60)
        ->withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; ClassificatorBot/1.0)',
            'Referer' => 'https://nevacert.ru/dokumenty/perekhodnye-klyuchi/tnved-okpd2',
        ])
        ->get('https://nevacert.ru/api/okdp-okdp2/search', $query)
        ->json();

    $items = $response['data'] ?? [];
    $lastPage = (int) ($response['last_page'] ?? $page);
    $sortIndex = $response['sort_index'] ?? null;

    if ($items === []) {
        $emptyPages++;
    }

    $firstId = $items[0]['id'] ?? null;
    $firstIdByPage[$page] = $firstId;

    foreach ($items as $item) {
        $totalItems++;
        $id = (int) $item['id'];

        if (isset($seenApiIds[$id])) {
            $duplicateApiIds++;
        }

        $seenApiIds[$id] = $page;
    }

    if ($page % 50 === 0 || $page <= 5) {
        echo "page={$page} items=".count($items)." first_id={$firstId} sort=".json_encode($sortIndex)." unique_ids=".count($seenApiIds).PHP_EOL;
    }

    $page++;
}

echo PHP_EOL;
echo "Pages fetched: ".($page - 1).PHP_EOL;
echo "Total items: {$totalItems}".PHP_EOL;
echo 'Unique API ids: '.count($seenApiIds).PHP_EOL;
echo "Duplicate API ids: {$duplicateApiIds}".PHP_EOL;
echo "Empty pages: {$emptyPages}".PHP_EOL;

// detect cycle: same first_id repeats
$firstIdCounts = array_count_values(array_filter($firstIdByPage));
arsort($firstIdCounts);
echo 'Most repeated first_id on page start: '.json_encode(array_slice($firstIdCounts, 0, 5, true)).PHP_EOL;
