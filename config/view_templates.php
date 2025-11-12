<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Template Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для BladeTemplateResolver, который выбирает шаблоны
    | для рендера записей (Entry) по приоритету:
    | 1. Entry.template_override (если задан)
    | 2. PostType.template (если задан)
    | 3. Default (pages.show) - если оба не заданы
    |
    */

    'default' => env('VIEW_TEMPLATES_DEFAULT', 'pages.show'),
];

