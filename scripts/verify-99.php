<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2Item;

$codes = ['99', '99.0', '99.00', '99.00.1', '99.00.10', '99.00.10.000'];

foreach ($codes as $code) {
    $item = Okpd2Item::query()->where('code', $code)->first();

    if (! $item) {
        echo "{$code} NOT FOUND\n";
        continue;
    }

    $childCount = Okpd2Item::query()->where('parent_code', $code)->count();

    printf(
        "%s parent=%s level=%d resolved=%d children=%d\n",
        $code,
        $item->parent_code ?? 'null',
        $item->level,
        Okpd2Item::resolveLevel($code),
        $childCount,
    );
}

$item = Okpd2Item::query()->where('code', '99.00.10.000')->first();
if ($item) {
    echo "\nBreadcrumb:\n";
    foreach ($item->breadcrumb() as $crumb) {
        echo '  '.$crumb['code'].' — '.$crumb['name']."\n";
    }

    echo "\nChildren of 99.00.10:\n";
    $parent = Okpd2Item::query()->where('code', '99.00.10')->first();
    if ($parent) {
        foreach ($parent->children()->get() as $child) {
            echo '  '.$child->code."\n";
        }
    }
}
