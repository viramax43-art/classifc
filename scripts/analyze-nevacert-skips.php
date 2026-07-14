<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(App\Services\NevacertApiClient::class);
$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$maxPages = (int) ($argv[1] ?? 100);
$total = 0;
$skipped = 0;
$valid = 0;
$reasons = [];
$invalidSamples = [];

foreach ($client->fetchTnvedOkpd2Mappings(null, $maxPages) as $batch) {
    foreach ($batch as $row) {
        $total++;
        $okpd2 = $importer->normalizeOkpd2Code($row['okpd2'] ?? '');
        $tnved = $importer->normalizeTnvedFromMapping($row['tnved'] ?? '');

        if ($okpd2 === '' || $tnved === '' || $tnved === '0000000000') {
            $skipped++;
            $reasons['empty'] = ($reasons['empty'] ?? 0) + 1;

            continue;
        }

        if (! $importer->isValidOkpd2Code($okpd2)) {
            $skipped++;
            $reasons['invalid_okpd2'] = ($reasons['invalid_okpd2'] ?? 0) + 1;

            if (count($invalidSamples) < 20) {
                $invalidSamples[] = $row['okpd2'].' => '.$okpd2;
            }

            continue;
        }

        $valid++;
    }
}

echo "Pages: {$maxPages}\n";
echo "Total API rows: {$total}\n";
echo "Valid: {$valid}\n";
echo "Skipped: {$skipped}\n";
echo 'Skip reasons: '.json_encode($reasons, JSON_UNESCAPED_UNICODE)."\n";
echo "Invalid OKPD2 samples:\n";

foreach ($invalidSamples as $sample) {
    echo "  - {$sample}\n";
}
