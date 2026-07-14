<?php

$file = glob(__DIR__.'/../'.'*.xlsx')[0] ?? null;
$zip = new ZipArchive;
$zip->open($file);

$shared = [];
$ssXml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
foreach ($ssXml->si as $si) {
    if (isset($si->t)) {
        $shared[] = (string) $si->t;
    } else {
        $text = '';
        foreach ($si->r ?? [] as $r) {
            $text .= (string) $r->t;
        }
        $shared[] = $text;
    }
}

function cellValue($cell, array $shared): string
{
    $type = (string) ($cell['t'] ?? '');
    $v = (string) ($cell->v ?? '');
    if ($type === 's') {
        return $shared[(int) $v] ?? $v;
    }
    if ($type === 'str') {
        return (string) ($cell->is->t ?? $cell->v ?? '');
    }

    return $v;
}

for ($i = 1; $i <= 110; $i++) {
    $path = "xl/worksheets/sheet{$i}.xml";
    if ($zip->getFromName($path) === false) {
        continue;
    }

    $xml = simplexml_load_string($zip->getFromName($path));
    $found = false;
    $sample = [];

    foreach ($xml->sheetData->row ?? [] as $row) {
        foreach ($row->c as $cell) {
            $val = cellValue($cell, $shared);
            if (str_contains($val, 'Код ТН ВЭД') || str_contains($val, 'Код ОКПД2')) {
                $found = true;
            }
        }

        if ($found && count($sample) < 8) {
            $cells = [];
            foreach ($row->c as $cell) {
                preg_match('/([A-Z]+)(\d+)/', (string) $cell['r'], $m);
                $cells[$m[1] ?? '?'] = cellValue($cell, $shared);
            }
            ksort($cells);
            $sample[] = 'R'.(string) $row['r'].': '.json_encode($cells, JSON_UNESCAPED_UNICODE);
        }
    }

    if ($found) {
        echo "sheet{$i} has mapping headers\n";
        foreach ($sample as $line) {
            echo $line."\n";
        }
        echo "\n";
    }
}

$zip->close();
