<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;

function showChildren(string $code): void
{
    $item = Okpd2Item::query()->where('code', $code)->first();
    if (! $item) {
        echo "{$code} NOT FOUND\n\n";
        return;
    }

    $children = Okpd2Item::query()->where('parent_code', $code)->orderBy('code')->get(['code', 'name']);
    echo "=== {$code} (parent={$item->parent_code}, level={$item->level}) — {$children->count()} children ===\n";
    foreach ($children as $child) {
        echo "  {$child->code} — {$child->name}\n";
    }
    echo "\n";
}

$checks = [
    '16', '16.2', '16.29', '16.29.2', '16.29.25', '16.29.25.130',
    '16.10.10', '16.10.10.110', '99', '99.00.10',
];

foreach ($checks as $code) {
    showChildren($code);
}

echo "resolveParentCode samples:\n";
$samples = ['16.1', '16.21', '16.29.1', '16.29.21', '16.29.25.110', '16.29.25.131', '16.10.10.111', '99.0', '99.00', '99.00.10.000'];
foreach ($samples as $code) {
    printf("  %s -> %s\n", $code, Okpd2Item::resolveParentCode($code) ?? 'null');
}
