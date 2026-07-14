<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;

$codes = ['01.11.11', '01.43.10', '16.10.10'];

foreach ($codes as $okpd2) {
    echo "OKPD2 {$okpd2}:\n";
    foreach (Okpd2TnvedMapping::query()->where('okpd2_code', $okpd2)->get() as $m) {
        echo '  -> '.$m->tnved_code.' ('.TnvedItem::formatDisplayCode($m->tnved_code).")\n";
    }
}

echo 'Total mappings: '.Okpd2TnvedMapping::query()->count()."\n";
