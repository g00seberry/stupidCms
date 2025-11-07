<?php

return [
    'reserved_routes' => [
        'paths' => [
            'admin', // строгое совпадение для "/admin"
        ],
        'prefixes' => [
            'admin', // префикс для "/admin/*"
            'api',   // префикс для "/api/*"
        ],
    ],
    'slug' => [
        'default' => [
            'delimiter' => env('SLUG_DELIMITER', '-'),
            'toLower' => env('SLUG_TO_LOWER', true),
            'asciiOnly' => env('SLUG_ASCII_ONLY', true),
            'maxLength' => env('SLUG_MAX_LENGTH', 120),
            'scheme' => env('SLUG_SCHEME', 'ru_basic'),
            'stopWords' => ['и', 'в', 'на'],
            'reserved' => [],
        ],
        'schemes' => [
            'ru_basic' => [
                'map' => [
                    'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
                    'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
                    'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
                    'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
                    'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch',
                    'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
                    'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
                ],
                'exceptions' => [
                    // 'йога' => 'yoga',
                    // 'Санкт-Петербург' => 'sankt-peterburg',
                ],
            ],
        ],
    ],
];

