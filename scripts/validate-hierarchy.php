<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;

$mismatches = [];

Okpd2Item::query()->orderBy('code')->chunk(1000, function ($items) use (&$mismatches) {
    foreach ($items as $item) {
        $expected = Okpd2Item::resolveParentCode($item->code);

        if ($item->parent_code !== $expected) {
            $mismatches[] = [
                'code' => $item->code,
                'stored' => $item->parent_code,
                'expected' => $expected,
            ];
        }
    }
});

echo 'Mismatches: '.count($mismatches)."\n";
foreach (array_slice($mismatches, 0, 30) as $row) {
    echo "  {$row['code']}: stored={$row['stored']} expected={$row['expected']}\n";
}

$docCases = [
    '16' => ['16.1', '16.2'],
    '16.2' => ['16.21', '16.22', '16.23', '16.24', '16.29'],
    '16.29' => ['16.29.1', '16.29.2', '16.29.9'],
    '16.29.2' => ['16.29.21', '16.29.22', '16.29.23', '16.29.24', '16.29.25'],
    '16.29.25' => ['16.29.25.110', '16.29.25.120', '16.29.25.130', '16.29.25.140'],
    '16.29.25.130' => [],
];

echo "\nDoc cases:\n";
foreach ($docCases as $parent => $expectedChildren) {
    $actual = Okpd2Item::query()->where('parent_code', $parent)->orderBy('code')->pluck('code')->all();
    $ok = $actual === $expectedChildren;
    echo ($ok ? 'OK' : 'FAIL')." {$parent}: expected ".count($expectedChildren).", got ".count($actual)."\n";
    if (! $ok) {
        echo '  expected: '.implode(', ', $expectedChildren)."\n";
        echo '  actual:   '.implode(', ', $actual)."\n";
    }
}
