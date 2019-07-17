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

    'storage' => [
        'backend' => env('FILE_STORAGE_BACKEND', 's3'),
        'root' => env('FILE_STORAGE_ROOT', 'files'),
    ],

    's3' => [
        'driver' => 's3',
        'key' => env('FILE_S3_ACCESS_KEY_ID'),
        'secret' => env('FILE_S3_SECRET_ACCESS_KEY'),
        'region' => env('FILE_S3_DEFAULT_REGION'),
        'bucket' => env('FILE_S3_BUCKET'),
        'endpoint' => env('FILE_S3_URL'),
        'root' => env('FILE_S3_ROOT', env('APP_NAME', 'app').'-storage')
    ]
];

