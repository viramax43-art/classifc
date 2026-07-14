<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TnvedItem;

$item = TnvedItem::where('code', '6403590500')->first();
if ($item) {
    echo "code={$item->code} parent={$item->parent_code} display={$item->display_code}\n";
}

$parent = TnvedItem::where('code', '6403590000')->first();
echo 'parent 6403590000 exists: '.($parent ? 'Y' : 'N')."\n";
