<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$maxPages = (int) ($argv[1] ?? 1000);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$page = 1;
$lastPage = 1;
$sortIndex = null;
$seenApiIds = [];
$seenValidPairs = [];
$seenRawPairs = [];

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

    foreach ($response['data'] ?? [] as $item) {
        $seenApiIds[(int) $item['id']] = true;

        $okpd2Raw = trim((string) ($item['code_of_type2'] ?? ''));
        $tnvedRaw = filled($item['code_search'] ?? null)
            ? (string) $item['code_search']
            : trim((string) ($item['code_of_type1'] ?? ''));

        $seenRawPairs[$okpd2Raw.'|'.$tnvedRaw] = true;

        $okpd2 = $importer->normalizeOkpd2Code($okpd2Raw);
        $tnved = $importer->normalizeTnvedFromMapping($tnvedRaw);

        if ($okpd2 !== '' && $tnved !== '' && $tnved !== '0000000000' && $importer->isValidOkpd2Code($okpd2)) {
            $seenValidPairs[$okpd2.'|'.$tnved] = true;
        }
    }

    $lastPage = (int) ($response['last_page'] ?? $page);
    $sortIndex = $response['sort_index'] ?? null;

    if ($page % 250 === 0) {
        echo "page={$page} api_ids=".count($seenApiIds).' raw_pairs='.count($seenRawPairs).' valid_pairs='.count($seenValidPairs).PHP_EOL;
    }

    $page++;
}

echo PHP_EOL;
echo "Pages: ".($page - 1).PHP_EOL;
echo 'Unique API ids: '.count($seenApiIds).PHP_EOL;
echo 'Unique raw pairs: '.count($seenRawPairs).PHP_EOL;
echo 'Unique valid normalized pairs: '.count($seenValidPairs).PHP_EOL;
