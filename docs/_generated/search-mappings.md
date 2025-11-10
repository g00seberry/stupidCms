# Elasticsearch Mappings

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:search` to update.

_Last generated: 2025-11-10 05:11:39_

## Index: `entries`

| Field | Type | Analyzer | Description |
|-------|------|----------|-------------|
| `id` | keyword | - | Entry ID |
| `post_type` | keyword | - | Post type slug (filter) |
| `slug` | keyword | - | Entry slug (exact match) |
| `title` | text | ru_en | Entry title (searchable) |
| `excerpt` | text | ru_en |  |
| `body_plain` | text | ru_en |  |
| `terms` | nested | - | Associated term slugs (filter) |
| `published_at` | date | - | Publication date (sort) |
| `boost` | float | - |  |

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
