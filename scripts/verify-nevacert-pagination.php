<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(App\Services\NevacertApiClient::class);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$maxPages = (int) ($argv[1] ?? 200);
$seenIds = [];
$seenPairs = [];
$duplicateIds = 0;
$duplicatePairs = 0;
$total = 0;
$validPairs = [];

foreach ($client->fetchTnvedOkpd2Mappings(null, $maxPages) as $batch) {
    foreach ($batch as $row) {
        $total++;
        $id = ($row['okpd2'] ?? '').'|'.($row['tnved'] ?? '');

        $okpd2 = $importer->normalizeOkpd2Code($row['okpd2'] ?? '');
        $tnved = $importer->normalizeTnvedFromMapping($row['tnved'] ?? '');
        $normKey = $okpd2.'|'.$tnved;

        if (isset($seenIds[$id])) {
            $duplicateIds++;
        }
        $seenIds[$id] = true;

        if ($importer->isValidOkpd2Code($okpd2) && $tnved !== '' && $tnved !== '0000000000') {
            if (isset($seenPairs[$normKey])) {
                $duplicatePairs++;
            }
            $seenPairs[$normKey] = true;
            $validPairs[] = $normKey;
        }
    }
}

echo "Pages: {$maxPages}\n";
echo "Total rows: {$total}\n";
echo 'Unique raw pairs: '.count($seenIds)."\n";
echo "Duplicate raw rows: {$duplicateIds}\n";
echo 'Unique normalized valid pairs: '.count($seenPairs)."\n";
echo "Duplicate normalized pairs: {$duplicatePairs}\n";
