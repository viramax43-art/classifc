<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$html = file_get_contents(__DIR__.'/../storage/app/alta-22bn0218.html');
$parser = app(App\Services\AltaTamdocMappingParser::class);
$ref = new ReflectionClass($parser);
$isHeader = $ref->getMethod('isOkpd2Header');
$isHeader->setAccessible(true);

$dom = new DOMDocument;
@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
$xpath = new DOMXPath($dom);
$row1 = $xpath->query("//table[contains(@class,'ordw-table-1')]//tr[contains(@class,'ordw-r')]")->item(1);
$text = trim(preg_replace('/\s+/u', ' ', $row1->getElementsByTagName('td')->item(0)->textContent));

echo "Text: [{$text}]".PHP_EOL;
echo 'isOkpd2Header: '.($isHeader->invoke($parser, $text) ? 'yes' : 'no').PHP_EOL;
echo 'preg: '.preg_match('/окpd|okpd/u', mb_strtolower($text)).PHP_EOL;

$parsed = $parser->parseHtml($html);
echo 'Parsed: '.count($parsed).PHP_EOL;
echo json_encode(array_slice($parsed, 0, 3), JSON_UNESCAPED_UNICODE).PHP_EOL;
