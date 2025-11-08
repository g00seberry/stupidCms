<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'max_upload_mb' => env('MEDIA_MAX_UPLOAD_MB', 25),
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'audio/mpeg',
        'application/pdf',
    ],
    'variants' => [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
    ],
    'signed_ttl' => env('MEDIA_SIGNED_TTL', 300),
    'path_strategy' => env('MEDIA_PATH_STRATEGY', 'by-date'), // by-date | hash-shard
];

