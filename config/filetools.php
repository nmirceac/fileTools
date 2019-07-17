<?php

return [
    'router'=> [
        'includeRoutes'=>true,
        'returnRelativeUrls'=>true,
        'prefix'=>'files',
        'namedPrefix'=>'file',
        'guestMiddleware'=>'web',
        'authMiddleware'=>'auth'
    ],

    'backend' => env('FILE_STORAGE_BACKEND', 's3'),
    'root' => env('FILE_STORAGE_ROOT', 'app'),

    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'endpoint' => env('AWS_URL'),
        'root' => env('AWS_ROOT', 'apps')
    ]
];

