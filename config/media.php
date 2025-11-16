<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'max_upload_mb' => env('MEDIA_MAX_UPLOAD_MB', 25),
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'image/heic',
        'image/heif',
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

    'image' => [
        'driver' => env('MEDIA_IMAGE_DRIVER', 'gd'), // gd | glide | imagick | external
        'quality' => (int) env('MEDIA_IMAGE_QUALITY', 82), // 0-100
        // Настройки для Glide/Intervention
        'glide_driver' => env('MEDIA_GLIDE_DRIVER', 'gd'), // gd | imagick
    ],

    'metadata' => [
        'ffprobe' => [
            'enabled' => env('MEDIA_FFPROBE_ENABLED', true),
            'binary' => env('MEDIA_FFPROBE_BINARY', null),
        ],
    ],
];

