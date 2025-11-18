# Изменения в Media API для фронтенда

**Дата:** 2025-11-18  
**Версия API:** v1 (без изменений)  
**Статус:** ✅ Обратная совместимость сохранена

---

## Краткое резюме

Архитектура хранения медиа-файлов была рефакторена с монолитной таблицы на Table Per Type (TPT) структуру. **Структура API ответов не изменилась** — все поля остались на своих местах. Изменения касаются только внутренней архитектуры базы данных.

---

## Что НЕ изменилось

✅ **Структура API ответов осталась прежней:**

```json
{
  "data": {
    "id": "01kaawc1bqkjcx36bbre75gewa",
    "kind": "image",
    "name": "hero.jpg",
    "ext": "jpg",
    "mime": "image/jpeg",
    "size_bytes": 235678,
    "width": 1920,           // ← осталось на месте
    "height": 1080,          // ← осталось на месте
    "duration_ms": null,     // ← осталось на месте
    "title": "Hero image",
    "alt": "Hero cover",
    "collection": "uploads",
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00",
    "deleted_at": null,
    "preview_urls": {
      "thumbnail": "https://api.stupidcms.dev/api/v1/admin/media/.../preview?variant=thumbnail"
    },
    "download_url": "https://api.stupidcms.dev/api/v1/admin/media/.../download"
  }
}
```

✅ **Все эндпоинты работают как прежде:**
- `GET /api/v1/admin/media` — список медиа
- `GET /api/v1/admin/media/{id}` — получение медиа
- `POST /api/v1/admin/media` — загрузка медиа
- `PUT /api/v1/admin/media/{id}` — обновление метаданных
- `DELETE /api/v1/admin/media/bulk` — удаление медиа
- `POST /api/v1/admin/media/bulk/restore` — восстановление медиа

✅ **Типы данных полей не изменились:**
- `width`, `height` — `number | null` (для изображений)
- `duration_ms` — `number | null` (для видео/аудио)
- `exif_json` — `object | null` (для изображений, если доступен)

---

## Что изменилось (внутренняя архитектура)

### До рефакторинга:
```
media
├── id
├── width (nullable)        ← для всех типов
├── height (nullable)       ← для всех типов
├── duration_ms (nullable)  ← для всех типов
├── exif_json (nullable)    ← для всех типов
└── ...
```

### После рефакторинга:
```
media (общие поля)
├── id
└── ...

media_images (только для изображений)
├── id
├── media_id
├── width (NOT NULL)
├── height (NOT NULL)
└── exif_json (nullable)

media_av_metadata (только для видео/аудио)
├── id
├── media_id
├── duration_ms (nullable)
├── bitrate_kbps (nullable)
├── frame_rate (nullable)
└── ...
```

**Важно:** Эти изменения не влияют на API — данные автоматически собираются из связанных таблиц и возвращаются в том же формате.

---

## Поведение полей

### Для изображений (`kind: "image"`):
- `width`, `height` — всегда присутствуют (NOT NULL в БД)
- `duration_ms` — всегда `null`
- `exif_json` — может быть `null` или объект с EXIF данными

### Для видео (`kind: "video"`):
- `width`, `height` — всегда `null`
- `duration_ms` — может быть `null` или число (миллисекунды)
- `exif_json` — всегда `null`

### Для аудио (`kind: "audio"`):
- `width`, `height` — всегда `null`
- `duration_ms` — может быть `null` или число (миллисекунды)
- `exif_json` — всегда `null`

### Для документов (`kind: "document"`):
- `width`, `height` — всегда `null`
- `duration_ms` — всегда `null`
- `exif_json` — всегда `null`

---

## Рекомендации для фронтенда

### ✅ Можно продолжать использовать как раньше:

```typescript
interface Media {
  id: string;
  kind: 'image' | 'video' | 'audio' | 'document';
  name: string;
  ext: string;
  mime: string;
  size_bytes: number;
  width: number | null;        // ← без изменений
  height: number | null;       // ← без изменений
  duration_ms: number | null; // ← без изменений
  title: string | null;
  alt: string | null;
  collection: string | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  preview_urls: Record<string, string> | null;
  download_url: string;
}
```

### ✅ Типизация осталась прежней:

```typescript
// Для изображений
if (media.kind === 'image') {
  const width = media.width;   // number
  const height = media.height; // number
  const exif = media.exif_json; // object | null
}

// Для видео/аудио
if (media.kind === 'video' || media.kind === 'audio') {
  const duration = media.duration_ms; // number | null
}
```

---

## Миграция данных

**Не требуется!** 

Система находится в активной разработке, поэтому:
- Новые записи создаются сразу в новой структуре
- Старые записи отсутствуют (база данных была пересоздана)
- Миграция существующих данных не нужна

---

## Технические детали (для справки)

### Eager Loading

Для оптимизации производительности API автоматически загружает связанные данные через eager loading. Это предотвращает N+1 проблемы и не требует изменений на фронтенде.

### Каскадное удаление

При удалении медиа-файла автоматически удаляются связанные записи:
- `media_images` — для изображений
- `media_av_metadata` — для видео/аудио

Это происходит автоматически и не требует дополнительных запросов.

---

## Чек-лист для проверки

- [ ] Проверить, что `width` и `height` корректно отображаются для изображений
- [ ] Проверить, что `duration_ms` корректно отображается для видео/аудио
- [ ] Убедиться, что `null` значения обрабатываются корректно
- [ ] Проверить работу фильтрации и сортировки по размерам/длительности
- [ ] Убедиться, что загрузка новых медиа работает как прежде

---

## Поддержка

Если обнаружены проблемы или несоответствия в API ответах, пожалуйста, сообщите в issue tracker.

---

**Вывод:** Никаких изменений в коде фронтенда не требуется. API полностью обратно совместим.


