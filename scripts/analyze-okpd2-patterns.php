<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$importer = app(App\Services\Okpd2TnvedMappingImporter::class);

$page = 1;
$sortIndex = null;
$patterns = [];

while ($page <= 200) {
    $query = ['type' => 13, 'page' => $page];
    if ($sortIndex !== null) {
        $query['sort_index'] = json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
    }

    $response = Http::timeout(60)->get('https://nevacert.ru/api/okdp-okdp2/search', $query)->json();

    foreach ($response['data'] ?? [] as $item) {
        $raw = trim((string) ($item['code_of_type2'] ?? ''));
        if ($raw === '') {
            $patterns['<empty>'] = ($patterns['<empty>'] ?? 0) + 1;

            continue;
        }

        $norm = $importer->normalizeOkpd2Code($raw);
        $strict = $importer->isValidOkpd2Code($norm);
        $relaxed = (bool) preg_match('/^\d+\.\d+\.\d+/', $norm);

        if ($strict) {
            $patterns['strict_ok'] = ($patterns['strict_ok'] ?? 0) + 1;
        } elseif ($relaxed) {
            $patterns['relaxed_only'] = ($patterns['relaxed_only'] ?? 0) + 1;
            $patterns['relaxed_samples'][] = $raw;
        } else {
            $key = preg_match('/^\d+\.\d+$/', $norm) ? 'two_part' : 'other';
            $patterns[$key] = ($patterns[$key] ?? 0) + 1;
            if (($patterns[$key.'_samples'] ?? []) === [] || count($patterns[$key.'_samples']) < 10) {
                $patterns[$key.'_samples'][] = $raw;
            }
        }
    }

    $sortIndex = $response['sort_index'] ?? null;
    $page++;
}

unset($patterns['relaxed_samples']);
echo json_encode($patterns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
