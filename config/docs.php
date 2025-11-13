<?php

declare(strict_types=1);

return [
    'types' => [
        'model' => [
            'path' => 'app/Models',
            'namespace' => 'App\\Models',
            'id_prefix' => 'model',
            'description' => 'Eloquent-модели для работы с БД',
            'example_id' => 'model:App\\Models\\Entry',
        ],
        'domain_service' => [
            'path' => 'app/Domain',
            'namespace' => 'App\\Domain',
            'id_prefix' => 'domain_service',
            'description' => 'Доменные сервисы, действия, репозитории, валидаторы, джобы',
            'example_id' => 'domain_service:Entries/PublishingService',
        ],
        'blade_view' => [
            'path' => 'resources/views',
            'id_prefix' => 'blade_view',
            'description' => 'Blade-шаблоны для рендеринга контента',
            'example_id' => 'blade_view:entry.blade.php',
        ],
        'config_area' => [
            'path' => 'config',
            'id_prefix' => 'config_area',
            'description' => 'Логические секции конфигурации',
            'example_id' => 'config_area:stupidcms',
        ],
        'concept' => [
            'id_prefix' => 'concept',
            'description' => 'Доменные концепции и идеи: postType, taxonomy, entry, media, search, routing, template_resolution',
            'example_id' => 'concept:postType:post',
        ],
        'http_endpoint' => [
            'id_prefix' => 'http_endpoint',
            'description' => 'HTTP эндпоинты из Scribe',
            'example_id' => 'http_endpoint:GET:/api/entries/{id}',
        ],
    ],
    'output' => [
        'markdown_dir' => 'docs/generated',
        'index_file' => 'docs/generated/index.json',
    ],
];

