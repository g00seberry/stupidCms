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
    | - kinds: маппинг типов медиа (image, video, audio, document) на диски.
    |
    | Все имена дисков должны существовать в config/filesystems.php.
    |
    */
    'disks' => [
        // Основной диск по умолчанию.
        'default' => env('MEDIA_DEFAULT_DISK', env('MEDIA_DISK', 'media')),

        // Маршрутизация по типу медиа (image/video/audio/document).
        'kinds' => [
            // 'image' => 'media_images',
            // 'video' => 'media_videos',
        ],
    ],

    'max_upload_mb' => env('MEDIA_MAX_UPLOAD_MB', 1024),
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'image/heic',
        'image/heif',
        'video/mp4',
        'audio/mp4',
        'audio/mpeg',
        'audio/aiff',
        'audio/x-aiff',
        'application/pdf',
    ],
    /*
    |--------------------------------------------------------------------------
    | Image variants
    |--------------------------------------------------------------------------
    |
    | Конфигурация вариантов изображений (превью, миниатюры).
    |
    | Обязательные варианты (должны быть всегда настроены):
    | - thumbnail: миниатюра (максимальный размер 320px)
    | - medium: средний размер (максимальный размер 1024px)
    | - large: большой размер (максимальный размер 2048px)
    |
    | Дополнительные варианты могут быть добавлены, но эти три обязательны.
    | Каждый вариант должен содержать ключ 'max' с максимальным размером в пикселях.
    | Опционально можно указать 'format' (webp, jpg, png) и 'quality' (0-100).
    |
    */
    'variants' => [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024],
        'large' => ['max' => 2048],
    ],
    'signed_ttl' => env('MEDIA_SIGNED_TTL', 300),
    'public_signed_ttl' => env('MEDIA_PUBLIC_SIGNED_TTL', 3600), // TTL для публичных подписанных URL (по умолчанию 1 час)
    'path_strategy' => env('MEDIA_PATH_STRATEGY', 'by-date'), // by-date | hash-shard

    'image' => [
        'driver' => env('MEDIA_IMAGE_DRIVER', 'gd'), // gd | glide | imagick | external
        'quality' => (int) env('MEDIA_IMAGE_QUALITY', 82), // 0-100
        // Настройки для Glide/Intervention
        'glide_driver' => env('MEDIA_GLIDE_DRIVER', 'gd'), // gd | imagick
    ],

    'metadata' => [
        'getid3' => [
            'enabled' => env('MEDIA_GETID3_ENABLED', true),
        ],
        'ffprobe' => [
            'enabled' => env('MEDIA_FFPROBE_ENABLED', true),
            'binary' => env('MEDIA_FFPROBE_BINARY', null),
        ],
        'mediainfo' => [
            'enabled' => env('MEDIA_MEDIAINFO_ENABLED', false),
            'binary' => env('MEDIA_MEDIAINFO_BINARY', null),
        ],
        'exiftool' => [
            'enabled' => env('MEDIA_EXIFTOOL_ENABLED', false),
            'binary' => env('MEDIA_EXIFTOOL_BINARY', null),
        ],
        'cache_ttl' => (int) env('MEDIA_METADATA_CACHE_TTL', 3600),
    ],


    /*
    |--------------------------------------------------------------------------
    | EXIF management
    |--------------------------------------------------------------------------
    |
    | Настройки управления EXIF данными изображений.
    |
    */
    'exif' => [
        'auto_rotate' => env('MEDIA_EXIF_AUTO_ROTATE', true),
        'strip' => env('MEDIA_EXIF_STRIP', false),
        'whitelist' => env('MEDIA_EXIF_WHITELIST', null) ? explode(',', env('MEDIA_EXIF_WHITELIST')) : [],
        'preserve_color_profile' => env('MEDIA_EXIF_PRESERVE_COLOR_PROFILE', true),
    ],
];

