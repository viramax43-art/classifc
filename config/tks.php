<?php

return [
    'api_key' => env('TKS_API_KEY', ''),
    'base_url' => env('TKS_BASE_URL', 'https://api1.tks.ru/tnved.json/json'),
    'tree_base_url' => env('TKS_TREE_BASE_URL', 'https://api1.tks.ru/tree.json/json'),
    'tree_cache_ttl' => (int) env('TKS_TREE_CACHE_TTL', 3600),
    'timeout' => (int) env('TKS_TIMEOUT', 120),
    'concurrency' => (int) env('TKS_CONCURRENCY', 4),
    'retries' => (int) env('TKS_RETRIES', 3),
    'retry_delay' => (int) env('TKS_RETRY_DELAY', 2000),
];
