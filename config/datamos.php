<?php

return [
    'api_key' => env('DATAMOS_API_KEY'),
    'base_url' => env('DATAMOS_BASE_URL', 'https://apidata.mos.ru/v1'),
    'fallback_base_urls' => [
        'https://api.data.mos.ru/v1',
    ],
    'dataset_id' => (int) env('DATAMOS_DATASET_ID', 2752),
    'page_size' => (int) env('DATAMOS_PAGE_SIZE', 1000),
    'timeout' => (int) env('DATAMOS_TIMEOUT', 120),
    'concurrency' => (int) env('DATAMOS_CONCURRENCY', 6),
    'retries' => (int) env('DATAMOS_RETRIES', 5),
    'retry_delay' => (int) env('DATAMOS_RETRY_DELAY', 2000),
];
