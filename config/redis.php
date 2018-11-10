<?php
$config = [
    'default' => [
        'ip' => env('REDIS_HOST'),
        'port' => env('REDIS_PORT'),
        'password' =>env('REDIS_PASSWORD'),
        'databases' => ['default' => env('REDIS_DATABASE')],
    ],
    'api' => [
        'ip' => env('REDIS_API_HOST'),
        'port' => env('REDIS_API_PORT'),
        'password' =>env('REDIS_API_PASSWORD'),
        'databases' => ['default' => env('REDIS_API_DATABASE')],
    ],
    'app' => [
        'ip' => env('REDIS_APP_HOST'),
        'port' => env('REDIS_APP_PORT'),
        'password' =>env('REDIS_APP_PASSWORD'),
        'databases' => ['default' => env('REDIS_APP_DATABASE')],
    ],
];

return $config;