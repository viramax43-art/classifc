<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

function isRelaxedOkpd2(string $code): bool
{
    if ($code === '' || ! preg_match('/^\d+\.\d+\.\d+/', $code)) {
        return false;
    }

    return true;
}

$maxPages = (int) ($argv[1] ?? 1000);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$page = 1;
$lastPage = 1;
$sortIndex = null;
$strict = 0;
$relaxed = 0;
$emptyOkpd2 = 0;
$pairs = [];

while ($page <= $lastPage && $page <= $maxPages) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }

    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();

    foreach ($response['data'] ?? [] as $item) {
        $okpd2Raw = trim((string) ($item['code_of_type2'] ?? ''));
        $tnvedRaw = filled($item['code_search'] ?? null)
            ? (string) $item['code_search']
            : trim((string) ($item['code_of_type1'] ?? ''));

        if ($okpd2Raw === '') {
            $emptyOkpd2++;

            continue;
        }

        $okpd2 = $importer->normalizeOkpd2Code($okpd2Raw);
        $tnved = $importer->normalizeTnvedFromMapping($tnvedRaw);

        if ($tnved === '' || $tnved === '0000000000') {
            continue;
        }

        if ($importer->isValidOkpd2Code($okpd2)) {
            $strict++;
            $pairs[$okpd2.'|'.$tnved] = true;
        } elseif (isRelaxedOkpd2($okpd2)) {
            $relaxed++;
            $pairs[$okpd2.'|'.$tnved] = true;
        }
    }

    $lastPage = (int) ($response['last_page'] ?? $page);
    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

echo "strict_valid={$strict}\n";
echo "relaxed_only={$relaxed}\n";
echo "empty_okpd2={$emptyOkpd2}\n";
echo 'unique_pairs='.count($pairs)."\n";
echo 'projected_pairs='.round(count($pairs) / $maxPages * 6353)."\n";
