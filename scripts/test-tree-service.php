<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(App\Services\TksTreeClient::class);
$root = App\Services\TnvedTreeMapper::mapNodes($client->getRootNodes());
echo 'root count: '.count($root).PHP_EOL;
echo 'first: '.json_encode($root[0], JSON_UNESCAPED_UNICODE).PHP_EOL;

$branch = App\Services\TnvedTreeMapper::mapNodes($client->getBranch(4000));
echo 'group children: '.count($branch).PHP_EOL;
echo 'leaf sample: '.json_encode($branch[1] ?? null, JSON_UNESCAPED_UNICODE).PHP_EOL;
