<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(App\Services\NevacertApiClient::class);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$maxPages = (int) ($argv[1] ?? 200);
$total = 0;
$skipped = 0;
$pairs = [];

foreach ($client->fetchTnvedOkpd2Mappings(null, $maxPages) as $batch) {
    foreach ($batch as $row) {
        $total++;
        $okpd2 = $importer->normalizeOkpd2ForNevacert($row['okpd2'] ?? '');
        $tnved = $importer->normalizeTnvedFromMapping($row['tnved'] ?? '');

        if ($okpd2 === '' || $tnved === '' || $tnved === '0000000000') {
            $skipped++;
            continue;
        }

        if ($importer->normalizeOkpd2ForNevacert($row['okpd2'] ?? '') === '') {
            $skipped++;
            continue;
        }

        $pairs[$importer->normalizeOkpd2ForNevacert($row['okpd2']).'|'.$tnved] = true;
    }
}

echo "pages={$maxPages} rows={$total} valid=".count($pairs)." skipped={$skipped}\n";
echo 'projected_valid_pairs='.round(count($pairs) / $maxPages * 6353)."\n";
