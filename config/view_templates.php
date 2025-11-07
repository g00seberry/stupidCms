<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Template Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для BladeTemplateResolver, который выбирает шаблоны
    | для рендера записей (Entry) по приоритету:
    | 1. Override по slug (pages.overrides.{slug})
    | 2. По типу поста (pages.types.{postType->slug})
    | 3. Default (pages.show)
    |
    */

    'default' => env('VIEW_TEMPLATES_DEFAULT', 'pages.show'),

    'override_prefix' => env('VIEW_TEMPLATES_OVERRIDE_PREFIX', 'pages.overrides.'),

    'type_prefix' => env('VIEW_TEMPLATES_TYPE_PREFIX', 'pages.types.'),
];

