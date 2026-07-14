<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;

$code = $argv[1] ?? '6403590000';
echo "mapping {$code}: ".(Okpd2TnvedMapping::where('tnved_code', $code)->exists() ? 'Y' : 'N').PHP_EOL;
echo "tnved {$code}: ".(TnvedItem::where('code', $code)->exists() ? 'Y' : 'N').PHP_EOL;
echo "tnved like 640359%: ".TnvedItem::where('code', 'like', '640359%')->pluck('code')->implode(', ').PHP_EOL;
echo "tnved like 6403%59%: ".TnvedItem::where('code', 'like', '6403%59%')->orderBy('code')->limit(20)->pluck('code')->implode(', ').PHP_EOL;
