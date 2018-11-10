<?php

$config = [
    'default' => [
        'broker' => env('KAFKA_BROKER'),
        'prefix' => env('KAFKA_PREFIX'),
        'topic' => ['default' => env('KAFKA_TOPIC')],
    ],
    'app' => [
        'broker' => env('KAFKA_APP_BROKER'),
        'prefix' => env('KAFKA_APP_PREFIX'),
        'topic' => ['default' => env('KAFKA_APP_TOPIC')],
    ],
    'log' => [
        'broker' => env('KAFKA_LOG_BROKER'),
        'prefix' => env('KAFKA_LOG_PREFIX'),
        'topic' => ['default' => env('KAFKA_LOG_TOPIC')],
    ],
];

return $config;