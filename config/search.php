<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search Driver Toggle
    |--------------------------------------------------------------------------
    |
    | Флаг включения полнотекстового поиска. В тестовой среде можно отключить,
    | чтобы не дергать внешний кластер.
    |
    */

    'enabled' => env('SEARCH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Client Configuration
    |--------------------------------------------------------------------------
    |
    | Список хостов (через запятую) и базовые параметры подключения.
    |
    */

    'client' => [
        'hosts' => array_filter(explode(',', env('SEARCH_HOSTS', 'http://127.0.0.1:9200'))),
        'username' => env('SEARCH_USERNAME'),
        'password' => env('SEARCH_PASSWORD'),
        'verify_ssl' => (bool) env('SEARCH_VERIFY_SSL', true),
        'timeout' => (float) env('SEARCH_TIMEOUT', 2.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Definitions
    |--------------------------------------------------------------------------
    |
    | Описание алиасов и схемы индекса для документов entries.
    |
    */

    'indexes' => [
        'entries' => [
            'read_alias' => env('SEARCH_ENTRIES_READ_ALIAS', 'entries_read'),
            'write_alias' => env('SEARCH_ENTRIES_WRITE_ALIAS', 'entries_write'),
            'name_prefix' => env('SEARCH_ENTRIES_PREFIX', 'entries'),
            'settings' => [
                'number_of_shards' => (int) env('SEARCH_ENTRIES_SHARDS', 1),
                'number_of_replicas' => (int) env('SEARCH_ENTRIES_REPLICAS', 0),
                'analysis' => [
                    'filter' => [
                        'ru_stop' => [
                            'type' => 'stop',
                            'stopwords' => '_russian_',
                        ],
                        'ru_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'russian',
                        ],
                        'en_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'english',
                        ],
                    ],
                    'analyzer' => [
                        'ru_en' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => [
                                'lowercase',
                                'ru_stop',
                                'ru_stemmer',
                                'en_stemmer',
                            ],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'dynamic' => false,
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'post_type' => ['type' => 'keyword'],
                    'slug' => ['type' => 'keyword'],
                    'title' => ['type' => 'text', 'analyzer' => 'ru_en'],
                    'excerpt' => ['type' => 'text', 'analyzer' => 'ru_en'],
                    'body_plain' => ['type' => 'text', 'analyzer' => 'ru_en'],
                    'terms' => [
                        'type' => 'nested',
                        'properties' => [
                            'taxonomy' => ['type' => 'keyword'],
                            'slug' => ['type' => 'keyword'],
                        ],
                    ],
                    'published_at' => ['type' => 'date'],
                    'boost' => ['type' => 'float'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapping Shortcut For Docs Generator
    |--------------------------------------------------------------------------
    |
    | docs:search использует этот ключ, чтобы построить артефакты.
    |
    */

    'mappings' => [
        'entries' => [
            'properties' => [
                'id' => ['type' => 'keyword'],
                'post_type' => ['type' => 'keyword'],
                'slug' => ['type' => 'keyword'],
                'title' => ['type' => 'text', 'analyzer' => 'ru_en'],
                'excerpt' => ['type' => 'text', 'analyzer' => 'ru_en'],
                'body_plain' => ['type' => 'text', 'analyzer' => 'ru_en'],
                'terms' => [
                    'type' => 'nested',
                    'properties' => [
                        'taxonomy' => ['type' => 'keyword'],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
                'published_at' => ['type' => 'date'],
                'boost' => ['type' => 'float'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Size For Reindex
    |--------------------------------------------------------------------------
    |
    | Размер партии bulk-запроса при переиндексации.
    |
    */

    'batch' => [
        'size' => (int) env('SEARCH_BATCH_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],
];


