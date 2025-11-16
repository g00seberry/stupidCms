<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Media disks routing
    |--------------------------------------------------------------------------
    |
    | Конфигурация маршрутизации медиа по дискам.
    |
    | - default: диск по умолчанию для всех медиа (если не переопределён).
    | - collections: маппинг коллекций (payload.collection) на диски.
    | - kinds: маппинг типов медиа (image, video, audio, document) на диски.
    |
    | Все имена дисков должны существовать в config/filesystems.php.
    |
    */
    'disks' => [
        // Основной диск по умолчанию.
        'default' => env('MEDIA_DEFAULT_DISK', env('MEDIA_DISK', 'media')),

        // Маршрутизация по коллекциям (например, videos → media_videos).
        'collections' => [
            // 'videos' => 'media_videos',
            // 'documents' => 'media_documents',
        ],

        // Маршрутизация по типу медиа (image/video/audio/document).
        'kinds' => [
            // 'image' => 'media_images',
            // 'video' => 'media_videos',
        ],
    ],

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

