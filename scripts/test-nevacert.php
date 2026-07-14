<?php

$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n", 'timeout' => 30]]);

$page1 = json_decode(file_get_contents('https://nevacert.ru/api/okdp-okdp2/search?type=13&page=1', false, $ctx), true);
echo 'Page1 items: '.count($page1['data']).PHP_EOL;
echo 'sort_index: '.json_encode($page1['sort_index']).PHP_EOL;
echo 'First id: '.$page1['data'][0]['id'].PHP_EOL;
echo 'Last id: '.$page1['data'][count($page1['data'])-1]['id'].PHP_EOL;

$sort = urlencode(json_encode($page1['sort_index']));
$page2url = "https://nevacert.ru/api/okdp-okdp2/search?type=13&sort_index={$sort}&page=2";
$page2 = json_decode(file_get_contents($page2url, false, $ctx), true);
echo 'Page2 items: '.count($page2['data']).PHP_EOL;
echo 'First id p2: '.$page2['data'][0]['id'].PHP_EOL;

$page2plain = json_decode(file_get_contents('https://nevacert.ru/api/okdp-okdp2/search?type=13&page=2', false, $ctx), true);
echo 'Page2 plain first id: '.$page2plain['data'][0]['id'].PHP_EOL;
