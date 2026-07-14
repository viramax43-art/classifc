<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$maxPages = (int) ($argv[1] ?? 500);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$page = 1;
$lastPage = 1;
$sortIndex = null;
$stats = [
    'total' => 0,
    'valid' => 0,
    'empty_okpd2' => 0,
    'empty_tnved' => 0,
    'invalid_okpd2' => 0,
    'invalid_samples' => [],
];

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
        $stats['total']++;

        $okpd2Raw = trim((string) ($item['code_of_type2'] ?? ''));
        $tnvedRaw = filled($item['code_search'] ?? null)
            ? (string) $item['code_search']
            : trim((string) ($item['code_of_type1'] ?? ''));

        if ($okpd2Raw === '') {
            $stats['empty_okpd2']++;

            continue;
        }

        if ($tnvedRaw === '') {
            $stats['empty_tnved']++;

            continue;
        }

        $okpd2 = $importer->normalizeOkpd2Code($okpd2Raw);
        $tnved = $importer->normalizeTnvedFromMapping($tnvedRaw);

        if ($tnved === '' || $tnved === '0000000000') {
            $stats['empty_tnved']++;

            continue;
        }

        if (! $importer->isValidOkpd2Code($okpd2)) {
            $stats['invalid_okpd2']++;

            if (count($stats['invalid_samples']) < 15) {
                $stats['invalid_samples'][] = [
                    'id' => $item['id'],
                    'okpd2' => $okpd2Raw,
                    'tnved' => $tnvedRaw,
                    'name2' => $item['name_of_type2'] ?? '',
                    'comment' => $item['comment'] ?? '',
                ];
            }

            continue;
        }

        $stats['valid']++;
    }

    $lastPage = (int) ($response['last_page'] ?? $page);
    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

$ratio = $stats['valid'] / max($stats['total'], 1);
echo PHP_EOL.'Projected valid for 31761 total: '.round(31761 * $ratio).PHP_EOL;
