<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class AltaTamdocClient
{
    public function fetch(string $url = 'https://www.alta.ru/tamdoc/22bn0218/'): string
    {
        $response = Http::timeout(120)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ClassificatorBot/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($url);

        $response->throw();

        return $response->body();
    }
}
