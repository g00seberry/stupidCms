---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
  - "config/search.php"
---

# Search (полнотекстовый поиск)

stupidCms поддерживает полнотекстовый поиск по контенту через **Elasticsearch**.

> ⚠️ **Статус**: Раздел в процессе разработки. Описание концептуальное.

## Концепция

### Зачем Elasticsearch?

**Проблемы SQL LIKE**:
```sql
SELECT * FROM entries WHERE title LIKE '%laravel%' OR content LIKE '%laravel%';
```

- Медленно на больших таблицах
- Нет ранжирования по релевантности
- Нет поддержки морфологии ("Laravel", "ларавел", "ларавела" — разные строки)
- Нет фасетов (фильтров)

**Решение — Elasticsearch**:
- Полнотекстовый индекс с анализаторами
- Ранжирование по relevance score
- Морфология (stemming, lemmatization)
- Фасеты (aggregations) — "Найдено: 10 в категории Laravel, 5 в PHP"
- Быстрый поиск (миллисекунды на миллионах документов)

## Архитектура

```mermaid
graph LR
    Entry[Entry Model] --> Event[EntryCreated/Updated]
    Event --> Listener[IndexEntryListener]
    Listener --> ES[Elasticsearch]
    
    User[User Search] --> API[SearchController]
    API --> ES
    ES --> API
    API --> User
```

### Компоненты

1. **Elasticsearch** — поисковый движок
2. **Index** — индекс `entries` с маппингом полей
3. **Listener** — автоматическая индексация при создании/обновлении entry
4. **SearchService** — обёртка над Elasticsearch client
5. **API** — `GET /api/search`

## Индексация

### Создание индекса

```bash
php artisan search:setup
```

**Что происходит**:

```php
// app/Console/Commands/SearchSetup.php

$client->indices()->create([
    'index' => 'entries',
    'body' => [
        'settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
            'analysis' => [
                'analyzer' => [
                    'russian' => [
                        'type' => 'standard',
                        'stopwords' => '_russian_',
                    ],
                ],
            ],
        ],
        'mappings' => [
            'properties' => [
                'id' => ['type' => 'long'],
                'title' => ['type' => 'text', 'analyzer' => 'russian'],
                'content' => ['type' => 'text', 'analyzer' => 'russian'],
                'slug' => ['type' => 'keyword'],
                'post_type' => ['type' => 'keyword'],
                'terms' => ['type' => 'keyword'],
                'published_at' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
            ],
        ],
    ],
]);
```

---

### Автоматическая индексация

**Listener**: `app/Listeners/IndexEntry.php`

```php
public function handle(EntryCreated|EntryUpdated $event): void
{
    $entry = $event->entry;
    
    if ($entry->status !== 'published') {
        // Удалить из индекса, если не published (draft)
        $this->searchService->delete($entry->id);
        return;
    }
    
    $this->searchService->index([
        'id' => $entry->id,
        'title' => $entry->title,
        'content' => strip_tags($entry->data_json['content'] ?? ''),
        'slug' => $entry->slug,
        'post_type' => $entry->postType->slug,
        'terms' => $entry->terms->pluck('slug')->toArray(),
        'published_at' => $entry->published_at->toIso8601String(),
        'status' => $entry->status,
    ]);
}
```

---

### Ре-индексация (bulk)

```bash
php artisan search:reindex
```

**Команда**:

```php
Entry::published()
    ->with(['postType', 'terms'])
    ->chunk(100, function ($entries) {
        foreach ($entries as $entry) {
            event(new EntryUpdated($entry));
        }
    });
```

## Поиск

### API Endpoint

**Endpoint**: `GET /api/search`

**Query Parameters**:
- `q` — поисковый запрос (обязательно)
- `post_type` — фильтр по типу контента
- `term_id` — фильтр по термину
- `page` — пагинация
- `per_page` — результатов на страницу (default: 20)

**Пример**:
```
GET /api/search?q=laravel&post_type=article&page=1
```

---

### Response

```json
{
  "data": [
    {
      "id": 1,
      "title": "Laravel 12 Released",
      "slug": "laravel-12-released",
      "excerpt": "...что нового в <mark>Laravel</mark> 12...",
      "post_type": "article",
      "published_at": "2025-11-08T12:00:00Z",
      "score": 4.523
    }
  ],
  "meta": {
    "total": 42,
    "per_page": 20,
    "current_page": 1
  },
  "aggregations": {
    "post_types": {
      "article": 30,
      "page": 12
    },
    "terms": {
      "laravel": 25,
      "php": 15
    }
  }
}
```

---

### SearchService

**Файл**: `app/Services/SearchService.php` (пример)

```php
public function search(string $query, array $filters = []): array
{
    $params = [
        'index' => 'entries',
        'body' => [
            'query' => [
                'bool' => [
                    'must' => [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['title^3', 'content'],
                        ],
                    ],
                    'filter' => [
                        ['term' => ['status' => 'published']],
                    ],
                ],
            ],
            'highlight' => [
                'fields' => [
                    'title' => (object)[],
                    'content' => ['fragment_size' => 150],
                ],
            ],
            'aggs' => [
                'post_types' => [
                    'terms' => ['field' => 'post_type'],
                ],
                'terms' => [
                    'terms' => ['field' => 'terms', 'size' => 10],
                ],
            ],
        ],
        'from' => ($filters['page'] - 1) * $filters['per_page'],
        'size' => $filters['per_page'],
    ];
    
    // Добавить фильтры
    if (!empty($filters['post_type'])) {
        $params['body']['query']['bool']['filter'][] = [
            'term' => ['post_type' => $filters['post_type']],
        ];
    }
    
    $response = $this->client->search($params);
    
    return $this->transformResponse($response);
}
```

## Маппинг полей

### Базовые поля

| Поле | Тип ES | Анализатор | Описание |
|------|--------|------------|----------|
| `id` | long | — | ID entry |
| `title` | text | russian | Заголовок (searchable) |
| `content` | text | russian | Контент (searchable) |
| `slug` | keyword | — | URL (exact match) |
| `post_type` | keyword | — | Тип контента (filter) |
| `terms` | keyword | — | Термины (filter, aggregation) |
| `published_at` | date | — | Дата публикации (sort) |
| `status` | keyword | — | Статус (filter) |

### Кастомные поля (data_json)

Если нужно индексировать кастомные поля:

```php
// Маппинг
'data_json.subtitle' => ['type' => 'text', 'analyzer' => 'russian'],
'data_json.featured' => ['type' => 'boolean'],

// Индексация
'data_json' => [
    'subtitle' => $entry->data_json['subtitle'] ?? null,
    'featured' => $entry->data_json['featured'] ?? false,
],
```

## Фасетная навигация (Aggregations)

### По типам контента

```json
{
  "aggs": {
    "post_types": {
      "terms": { "field": "post_type" }
    }
  }
}
```

**Response**:
```json
{
  "aggregations": {
    "post_types": {
      "buckets": [
        {"key": "article", "doc_count": 150},
        {"key": "page", "doc_count": 30}
      ]
    }
  }
}
```

**UI**: Чекбоксы "Статьи (150)", "Страницы (30)"

---

### По терминам

```json
{
  "aggs": {
    "terms": {
      "terms": { "field": "terms", "size": 10 }
    }
  }
}
```

**Response**:
```json
{
  "aggregations": {
    "terms": {
      "buckets": [
        {"key": "laravel", "doc_count": 50},
        {"key": "php", "doc_count": 30}
      ]
    }
  }
}
```

## Highlight (подсветка)

Elasticsearch может подсвечивать найденные слова:

```json
{
  "highlight": {
    "fields": {
      "title": {},
      "content": { "fragment_size": 150, "number_of_fragments": 3 }
    }
  }
}
```

**Response**:
```json
{
  "hits": {
    "hits": [
      {
        "_source": {"title": "Laravel 12 Released"},
        "highlight": {
          "title": ["<em>Laravel</em> 12 Released"],
          "content": ["...новинки <em>Laravel</em> 12..."]
        }
      }
    ]
  }
}
```

**UI**: Отображаем `<mark>Laravel</mark>` вместо `<em>`.

## Синонимы и морфология

### Настройка анализатора

```json
{
  "analysis": {
    "filter": {
      "russian_stop": {
        "type": "stop",
        "stopwords": "_russian_"
      },
      "russian_stemmer": {
        "type": "stemmer",
        "language": "russian"
      },
      "synonym_filter": {
        "type": "synonym",
        "synonyms": [
          "ларавел, laravel",
          "фреймворк, framework"
        ]
      }
    },
    "analyzer": {
      "russian_custom": {
        "type": "custom",
        "tokenizer": "standard",
        "filter": [
          "lowercase",
          "russian_stop",
          "russian_stemmer",
          "synonym_filter"
        ]
      }
    }
  }
}
```

**Результат**: Запрос "ларавел" найдёт "Laravel" и наоборот.

## Конфигурация

**Файл**: `config/search.php` (создать)

```php
return [
    'enabled' => env('ELASTICSEARCH_ENABLED', false),
    'hosts' => explode(',', env('ELASTICSEARCH_HOSTS', 'localhost:9200')),
    'index' => env('ELASTICSEARCH_INDEX', 'entries'),
    
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 1,
    ],
    
    'mappings' => [
        // см. выше
    ],
];
```

**.env**:
```env
ELASTICSEARCH_ENABLED=true
ELASTICSEARCH_HOSTS=localhost:9200
ELASTICSEARCH_INDEX=entries
```

## Best Practices

### ✅ DO

- Используйте очередь для индексации (не блокируйте HTTP-запросы)
- Храните `_score` для отладки релевантности
- Логируйте медленные запросы (>1s)
- Настройте алиасы индексов для zero-downtime reindex
- Используйте фасеты для фильтрации

### ❌ DON'T

- Не индексируйте draft entries (только `status = 'published'`)
- Не забывайте удалять из индекса при изменении статуса на draft или удалении
- Не делайте `SELECT *` перед индексацией (только нужные поля)
- Не используйте wildcard запросы часто (медленно)

## Производительность

### Bulk индексация

Вместо:
```php
foreach ($entries as $entry) {
    $searchService->index($entry); // N запросов
}
```

Используйте:
```php
$searchService->bulk($entries); // 1 запрос
```

### Кэширование частых запросов

```php
$results = Cache::remember("search:{$query}:{$filters}", 600, fn() =>
    $searchService->search($query, $filters)
);
```

## Мониторинг

### Статус кластера

```bash
curl http://localhost:9200/_cluster/health?pretty
```

### Статистика индекса

```bash
curl http://localhost:9200/entries/_stats?pretty
```

### Медленные запросы

Включить логирование:
```json
PUT /entries/_settings
{
  "index.search.slowlog.threshold.query.warn": "1s",
  "index.search.slowlog.threshold.query.info": "500ms"
}
```

## Связанные страницы

- [Search Mappings](../30-reference/search-mappings.md) — автосгенерированный маппинг
- [How-to: Настройка поиска](../20-how-to/search-config.md)
- [Entries](entries.md) — индексируемые данные

---

> 💡 **Tip**: Для локальной разработки используйте Docker:
> ```bash
> docker run -p 9200:9200 -e "discovery.type=single-node" elasticsearch:8.11.0
> ```

