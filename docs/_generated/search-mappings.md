# Elasticsearch Mappings

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:search` to update.

_Last generated: 2025-11-08 10:19:17_

## Index: `entries`

| Field | Type | Analyzer | Description |
|-------|------|----------|-------------|
| `id` | long | - | Entry ID |
| `title` | text | russian | Entry title (searchable) |
| `content` | text | russian | Entry content (searchable) |
| `slug` | keyword | - | Entry slug (exact match) |
| `post_type` | keyword | - | Post type slug (filter) |
| `terms` | keyword | - | Associated term slugs (filter) |
| `published_at` | date | - | Publication date (sort) |
| `status` | keyword | - | Entry status (filter) |

## Index Settings

```json
{
    "number_of_shards": 1,
    "number_of_replicas": 1,
    "analysis": {
        "analyzer": {
            "russian": {
                "type": "standard",
                "stopwords": "_russian_"
            }
        }
    }
}
```

## Setup

To create indices:

```bash
php artisan search:setup
```

To reindex data:

```bash
php artisan search:reindex
```
