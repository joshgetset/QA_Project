<?php

return [
    'ces' => [
        'api_key' => env('CES_API_KEY'),
        'url' => env('QAMS_CES_SOURCE_API_URL', rtrim((string) env('APP_URL', ''), '/').'/api/source/ces'),
        'page_size' => (int) env('QAMS_SOURCE_API_PAGE_SIZE', 1000),
    ],
    'hrmis' => [
        'api_key' => env('HRMIS_API_KEY'),
        'url' => env('QAMS_HRMIS_SOURCE_API_URL', rtrim((string) env('APP_URL', ''), '/').'/api/source/hrmis'),
        'page_size' => (int) env('QAMS_SOURCE_API_PAGE_SIZE', 1000),
    ],
];
