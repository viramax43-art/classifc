<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;
use Illuminate\Support\Facades\Http;

$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

function candidates(string $raw): array
{
    $norm = preg_replace('/[^0-9A-Za-z\.]/', '', str_replace(' ', '', trim($raw))) ?? '';
    $out = [$norm];
    $parts = explode('.', $norm);

    if (count($parts) === 3 && strlen($parts[2]) === 4 && ctype_digit($parts[2])) {
        $year = $parts[2];
        $out[] = sprintf('%02d.%02d.%02d', (int) $parts[0], (int) $parts[1], (int) substr($year, 0, 2));
        $out[] = sprintf('%02d.%02d.%02d', (int) $parts[0], (int) $parts[1], (int) substr($year, 2, 2));
        $out[] = sprintf('%02d.%02d.%s', (int) $parts[0], (int) $parts[1], substr($year, 0, 2));
        $out[] = sprintf('%02d.%02d.%s', (int) $parts[0], (int) $parts[1], ltrim(substr($year, 2, 2), '0') ?: '0');
    }

    return array_values(array_unique($out));
}

$page = 1;
$sortIndex = null;
$resolved = 0;
$unresolved = 0;
$unresolvedSamples = [];

while ($page <= 500) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }

    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();

    foreach ($response['data'] ?? [] as $item) {
        $raw = trim((string) ($item['code_of_type2'] ?? ''));
        if ($raw === '') {
            continue;
        }

        $norm = $importer->normalizeOkpd2Code($raw);
        if ($importer->isValidOkpd2Code($norm)) {
            continue;
        }

        $hit = null;
        foreach (candidates($raw) as $try) {
            if (Okpd2Item::query()->where('code', $try)->exists()) {
                $hit = $try;
                break;
            }
        }

        if ($hit) {
            $resolved++;
        } else {
            $unresolved++;
            if (count($unresolvedSamples) < 15) {
                $unresolvedSamples[] = ['raw' => $raw, 'candidates' => candidates($raw), 'tnved' => $item['code_search'] ?? ''];
            }
        }
    }

    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

echo "resolved={$resolved}\n";
echo "unresolved={$unresolved}\n";
echo json_encode($unresolvedSamples, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
