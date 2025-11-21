<?php

declare(strict_types=1);

/**
 * Конфигурация для работы с Blueprint и материализацией.
 *
 * Управляет ограничениями глубины вложенности, лимитами проверок конфликтов
 * и другими параметрами работы с графом зависимостей blueprint'ов.
 */
return [
    /**
     * Максимальная глубина вложенности встраиваний (embeds).
     *
     * Защищает от слишком глубокой рекурсии при материализации.
     * Синхронизировано с MaterializationService::MAX_EMBED_DEPTH.
     *
     * @var int
     */
    'max_embed_depth' => env('BLUEPRINT_MAX_EMBED_DEPTH', 5),

    /**
     * Максимальная глубина для проверки конфликтов путей.
     *
     * Ограничивает рекурсивный обход графа при валидации конфликтов.
     * Должен быть >= max_embed_depth.
     *
     * @var int
     */
    'max_conflict_check_depth' => env('BLUEPRINT_MAX_CONFLICT_CHECK_DEPTH', 10),

    /**
     * Кеширование загруженных путей и embeds при проверке конфликтов.
     *
     * При включении все paths и embeds загружаются один раз с eager loading,
     * что значительно ускоряет работу с большими графами.
     *
     * @var bool
     */
    'cache_graph_on_conflict_check' => env('BLUEPRINT_CACHE_GRAPH', true),
];

