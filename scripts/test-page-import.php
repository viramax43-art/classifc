<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2TnvedMapping;
use Illuminate\Support\Facades\Http;

$targetPage = (int) ($argv[1] ?? 3000);
$page = 1;
$sortIndex = null;

while ($page <= $targetPage) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }
    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();
    if ($page === $targetPage) {
        break;
    }
    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

$importer = app(App\Services\Okpd2TnvedMappingImporter::class);
$client = app(App\Services\NevacertApiClient::class);

$before = Okpd2TnvedMapping::count();
$rows = [];

foreach ($response['data'] ?? [] as $item) {
    $okpd2 = trim((string) ($item['code_of_type2'] ?? ''));
    $tnved = filled($item['code_search'] ?? null)
        ? (string) $item['code_search']
        : trim((string) ($item['code_of_type1'] ?? ''));

    if ($okpd2 === '' || $tnved === '') {
        continue;
    }

    $rows[] = ['okpd2' => $okpd2, 'tnved' => $tnved, 'note' => null];
}

$result = $importer->import($rows, 'nevacert:tnved-okpd2', false, bulk: true);
$after = Okpd2TnvedMapping::count();

echo "page={$targetPage} rows=".count($rows)." imported={$result['imported']} skipped={$result['skipped']}\n";
echo "db: {$before} -> {$after} (delta ".($after - $before).")\n";

foreach ($rows as $row) {
    $ok = $importer->normalizeOkpd2ForNevacert($row['okpd2']);
    $tn = $importer->normalizeTnvedFromMapping($row['tnved']);
    echo "  {$row['okpd2']} + {$row['tnved']} => {$ok}|{$tn} exists=".(Okpd2TnvedMapping::where('okpd2_code', $ok)->where('tnved_code', $tn)->exists() ? 'Y' : 'N')."\n";
}
