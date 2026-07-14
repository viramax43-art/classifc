<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Console\Commands\ImportOkpd2TnvedMappingCommand;
use ReflectionClass;

$file = glob(__DIR__.'/../'.'*.xlsx')[0];
$cmd = new ImportOkpd2TnvedMappingCommand;
$ref = new ReflectionClass($cmd);
$read = $ref->getMethod('readRows');
$read->setAccessible(true);
$rows = $read->invoke($cmd, $file);

echo 'Parsed rows: '.count($rows).PHP_EOL;

foreach (array_slice($rows, 0, 10) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

$normalizeTnved = $ref->getMethod('normalizeTnvedFromMapping');
$normalizeTnved->setAccessible(true);
$normalizeOkpd2 = $ref->getMethod('normalizeOkpd2Code');
$normalizeOkpd2->setAccessible(true);

echo PHP_EOL.'Sample normalized:'.PHP_EOL;
foreach (array_slice($rows, 0, 5) as $row) {
    $o = $normalizeOkpd2->invoke($cmd, $row['okpd2'] ?? '');
    $t = $normalizeTnved->invoke($cmd, $row['tnved'] ?? '');
    echo "{$row['okpd2']} + {$row['tnved']} => {$o} -> {$t}".PHP_EOL;
}
