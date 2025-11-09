# ADR-0003: Use Elasticsearch for Search

**Status**: Accepted  
**Date**: 2025-11-08  
**Deciders**: @backend-team  

## Context

Требуется реализовать полнотекстовый поиск по entries с поддержкой:
- Поиск по заголовку, содержимому (data_json)
- Фильтрация по post_type, status, terms
- Фасетный поиск (facets)
- Высокая скорость (< 100ms на 100k записей)
- Поддержка морфологии (русский, английский)

## Decision

Использовать **Elasticsearch** в качестве поискового движка.

**Архитектура**:
- Elasticsearch cluster (внешний сервис)
- Laravel интеграция через HTTP client
- Асинхронная индексация через события
- Fallback на `NullSearchClient` если ES недоступен

**Индексы**:
- `entries_v1` — основной индекс записей
- Маппинг: title (text), slug (keyword), data_json (nested), terms (keyword[])

**Индексация**:
- Триггер: `EntryObserver` → `ReindexSearchJob` (queue)
- Bulk update через `SearchService::reindex()`

## Alternatives Considered

### 1. PostgreSQL Full-Text Search
```sql
SELECT * FROM entries WHERE to_tsvector(title) @@ to_tsquery('search')
```
**Плюсы**: Встроенное решение, нет внешних зависимостей  
**Минусы**: 
- Медленный на больших данных (> 100k записей)
- Ограниченные возможности фасетного поиска
- Сложная настройка морфологии

### 2. Algolia / Meilisearch (SaaS)
**Плюсы**: Managed решение, отличный DX  
**Минусы**:
- Дорого на высоких нагрузках ($$$)
- Vendor lock-in
- Данные хранятся у третьей стороны

### 3. Apache Solr
**Плюсы**: Зрелое решение, мощные возможности  
**Минусы**:
- Сложнее в настройке чем ES
- Меньше распространён в Laravel-экосистеме
- Тяжелее в деплое

## Consequences

### Положительные
- ✅ Быстрый поиск (< 50ms на 1M записей)
- ✅ Мощные фильтры и агрегации (facets)
- ✅ Отличная поддержка морфологии (ru/en)
- ✅ Масштабируемость (clustering)
- ✅ Open-source (self-hosted)

### Отрицательные
- ❌ Внешняя зависимость (ES cluster)
- ❌ Дополнительная инфраструктура (Docker, memory)
- ❌ Риск рассинхронизации с PostgreSQL
- ❌ Требует мониторинга и тюнинга

### Нейтральные
- ⚠️ Асинхронная индексация (eventual consistency)
- ⚠️ Fallback на DB search если ES недоступен
- ⚠️ Версионирование маппингов (entries_v1, entries_v2...)

## Implementation

**Файлы**:
- `app/Domain/Search/SearchService.php`
- `app/Domain/Search/Clients/ElasticsearchSearchClient.php`
- `app/Domain/Search/IndexManager.php`
- `config/search.php`

**Зависимости**:
```bash
# Docker compose
elasticsearch:8.x

# Laravel
guzzlehttp/guzzle
```

**Конфигурация**:
```php
// config/search.php
'driver' => env('SEARCH_DRIVER', 'elasticsearch'), // or 'null'
'elasticsearch' => [
    'hosts' => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'stupidcms_'),
]
```

**Мониторинг**:
- Health check: `GET /_cluster/health`
- Index stats: `GET /entries_v1/_stats`
- Sync lag: сравнение `Entry::count()` vs `ES document count`

## Related

- Документация: [Search](../../10-concepts/search.md)
- Маппинги: [Search Mappings](../../_generated/search-mappings.md)
- Команда реиндексации: `php artisan search:reindex`

## Future Considerations

- Синонимы и стоп-слова
- Suggester для автокомплита
- Highlight для фрагментов текста
- Multi-language поиск
- Vector search для семантического поиска (Elasticsearch 8.8+)

