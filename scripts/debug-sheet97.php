<?php

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

foreach (['xl/worksheets/sheet97.xml'] as $path) {
    $xml = simplexml_load_string($zip->getFromName($path));
    $grid = [];
    foreach ($xml->sheetData->row ?? [] as $row) {
        $rn = (int)$row['r'];
        foreach ($row->c as $cell) {
            preg_match('/([A-Z]+)/', (string)$cell['r'], $m);
            $grid[$rn][$m[1]] = cellValue($cell, $shared);
        }
    }
    ksort($grid);
    foreach ($grid as $rn => $cells) {
        if ($rn > 12) break;
        ksort($cells);
        echo "R{$rn}: ".json_encode($cells, JSON_UNESCAPED_UNICODE)."\n";
    }
}
$zip->close();
