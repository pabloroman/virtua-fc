<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST'],
    'allowed_origins' => [
        'https://virtuafc.com',
        'https://www.virtuafc.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
