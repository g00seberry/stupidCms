<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Template Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для BladeTemplateResolver, который выбирает шаблоны
    | для рендера записей (Entry) на основе полей template:
    | 1. Entry.template_override (если задано — используется как полное имя вью)
    | 2. PostType.template (если задано)
    | 3. templates.index (дефолтный шаблон)
    |
    | Все шаблоны должны находиться в папке templates или дочерних папках.
    |
    */

    'default' => env('VIEW_TEMPLATES_DEFAULT', 'templates.index'),
];

