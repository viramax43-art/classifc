<?php

require __DIR__.'/../vendor/autoload.php';

$file = glob(__DIR__.'/../'.'*.xlsx')[0];
$zip = new ZipArchive;
$zip->open($file);

$shared = [];
$ssXml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
foreach ($ssXml->si as $si) {
    $shared[] = trim((string) ($si->t ?? ''));
}

function cellValue($cell, array $shared): string {
    $type = (string) ($cell['t'] ?? '');
    $v = (string) ($cell->v ?? '');
    if ($type === 's') return trim($shared[(int)$v] ?? $v);
    if ($type === 'str') return trim((string)($cell->is->t ?? $v));
    return trim($v);
}

foreach (['xl/worksheets/sheet98.xml'] as $path) {
    $xml = simplexml_load_string($zip->getFromName($path));
    foreach ($xml->sheetData->row ?? [] as $row) {
        $rn = (int)$row['r'];
        if ($rn > 6) {
            continue;
        }
        $cells = [];
        foreach ($row->c as $cell) {
            preg_match('/([A-Z]+)/', (string)$cell['r'], $m);
            $cells[$m[1]] = cellValue($cell, $shared);
        }
        ksort($cells);
        echo "R{$rn}: ".json_encode($cells, JSON_UNESCAPED_UNICODE)."\n";
    }
}

$zip->close();
