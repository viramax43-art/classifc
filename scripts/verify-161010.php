<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;

$parent = '16.10.10';
$children = Okpd2Item::query()->where('parent_code', $parent)->orderBy('code')->get(['code', 'name']);

echo "Current DB: direct children of {$parent}: {$children->count()}\n";
foreach ($children as $child) {
    echo "  {$child->code} — {$child->name}\n";
}

echo "\nExpected resolveParentCode:\n";
$codes = ['16.10.10.110', '16.10.10.111', '16.10.10.120', '16.10.10.121', '16.10.10.131', '16.10.10.143'];
foreach ($codes as $code) {
    echo "  {$code} -> ".(Okpd2Item::resolveParentCode($code) ?? 'null')."\n";
}

$children = Okpd2Item::query()->where('parent_code', $parent)->orderBy('code')->pluck('code');
echo "\nAfter fix would be {$children->count()} direct children (run okpd2:fix-parents)\n";
