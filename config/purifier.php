<?php

return [
    'settings' => [
        'cms_default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'Cache.SerializerPath' => storage_path('app/purifier'),

            // Теги/атрибуты
            // Примечание: figure и figcaption добавляются через кастомную конфигурацию HTMLDefinition
            'HTML.AllowedElements' => [
                'a','abbr','b','blockquote','br','code','em','i','hr','img','li','ol','p','pre','s','small','strong','sub','sup','u','ul','h1','h2','h3','h4','h5','h6','div','span','figure','figcaption'
            ],
            'HTML.AllowedAttributes' => [
                'a.href','a.title','a.target','a.rel',
                'img.src','img.alt','img.title','img.width','img.height',
            ],
            'URI.AllowedSchemes' => [ 'http' => true, 'https' => true, 'mailto' => true ],

            // Удаляем скрипты/ивенты
            'HTML.SafeScripting' => [],
            'HTML.SafeEmbed' => false,
            'HTML.SafeObject' => false,
            'Attr.EnableID' => false,
            // onload, onclick и т.д. блокируются через белый список атрибутов (HTML.AllowedAttributes)
            // HTMLPurifier не поддерживает Attr.ForbiddenPatterns в стандартной конфигурации

            // Автоформатирование
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.Linkify' => false, // ссылкование оставим на редактор
            'AutoFormat.AutoParagraph' => false,

            // Изображения
            'URI.DisableExternalResources' => false,
            'CSS.AllowedProperties' => [], // стили запрещаем в базе профиля
        ],
        // Кастомные определения для HTML5 элементов
        'custom_definition' => [
            'id' => 'cms_default_html5',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // HTML5 семантические элементы
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
            ],
        ],
    ],
];

