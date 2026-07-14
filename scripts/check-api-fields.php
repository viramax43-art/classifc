<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$maxPages = (int) ($argv[1] ?? 50);
$page = 1;
$sortIndex = null;
$stats = ['total' => 0, 'has_okpd2' => 0, 'has_tnved' => 0, 'both' => 0, 'empty_okpd2_has_tnved' => 0];

while ($page <= $maxPages) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }
    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();

    foreach ($response['data'] ?? [] as $item) {
        $stats['total']++;
        $okpd2 = trim((string) ($item['code_of_type2'] ?? ''));
        $tnved = trim((string) ($item['code_of_type1'] ?? ''));
        $search = trim((string) ($item['code_search'] ?? ''));

        if ($okpd2 !== '') {
            $stats['has_okpd2']++;
        }
        if ($tnved !== '' || $search !== '') {
            $stats['has_tnved']++;
        }
        if ($okpd2 !== '' && ($tnved !== '' || $search !== '')) {
            $stats['both']++;
        }
        if ($okpd2 === '' && ($tnved !== '' || $search !== '')) {
            $stats['empty_okpd2_has_tnved']++;
            if ($stats['empty_okpd2_has_tnved'] <= 3) {
                $stats['empty_samples'][] = $item;
            }
        }
    }

    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
