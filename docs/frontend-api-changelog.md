# API Changelog: Изменения для фронтенда

> **stupidCMS API Changelog**  
> Версия: 1.0  
> Последнее обновление: 2025-11-20

---

## 2025-11-20: Добавлен blueprint в ответ GET /api/v1/admin/entries/{id}

### Изменение

При запросе конкретной записи (Entry) теперь возвращается `blueprint`, назначенный в родительском `PostType`.

### Endpoint

```
GET /api/v1/admin/entries/{id}
```

### Изменения в ответе

**До:**

```json
{
  "data": {
    "id": 15,
    "post_type": "article",
    "title": "My Article",
    "slug": "my-article",
    "status": "published",
    "content_json": {},
    "meta_json": {},
    "author": { ... },
    "terms": [ ... ],
    "created_at": "2025-11-20T10:00:00+00:00",
    "updated_at": "2025-11-20T10:00:00+00:00"
  }
}
```

**После:**

```json
{
  "data": {
    "id": 15,
    "post_type": "article",
    "title": "My Article",
    "slug": "my-article",
    "status": "published",
    "content_json": {},
    "meta_json": {},
    "author": { ... },
    "terms": [ ... ],
    "blueprint": {
      "id": 1,
      "name": "Article Blueprint",
      "code": "article",
      "description": "Blueprint for articles",
      "created_at": "2025-01-01T00:00:00+00:00",
      "updated_at": "2025-01-01T00:00:00+00:00"
    },
    "created_at": "2025-11-20T10:00:00+00:00",
    "updated_at": "2025-11-20T10:00:00+00:00"
  }
}
```

### Структура поля `blueprint`

| Поле          | Тип              | Описание                   |
| ------------- | ---------------- | -------------------------- |
| `id`          | `number`         | ID blueprint               |
| `name`        | `string`         | Название blueprint         |
| `code`        | `string`         | Уникальный код blueprint   |
| `description` | `string \| null` | Описание blueprint         |
| `created_at`  | `string \| null` | Дата создания (ISO 8601)   |
| `updated_at`  | `string \| null` | Дата обновления (ISO 8601) |

### Важные замечания

1. **Поле опциональное**: `blueprint` присутствует в ответе только если:

    - PostType имеет назначенный blueprint (`post_type.blueprint_id` не null)
    - Blueprint успешно загружен

2. **Если blueprint отсутствует**: поле `blueprint` не будет включено в ответ (не `null`, а полностью отсутствует)

3. **Обратная совместимость**: Изменение полностью обратно совместимо. Если ваш код не использует поле `blueprint`, ничего не сломается.

### Примеры использования

#### TypeScript / JavaScript

```typescript
interface Blueprint {
    id: number;
    name: string;
    code: string;
    description: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Entry {
    id: number;
    post_type: string;
    title: string;
    slug: string;
    status: string;
    content_json: Record<string, any>;
    meta_json: Record<string, any> | null;
    author?: { id: number; name: string };
    terms?: Array<{ id: number; name: string; taxonomy: number }>;
    blueprint?: Blueprint; // Опциональное поле
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

// Использование
const response = await fetch("/api/v1/admin/entries/15", {
    headers: { Authorization: `Bearer ${token}` },
});
const { data }: { data: Entry } = await response.json();

if (data.blueprint) {
    console.log("Entry uses blueprint:", data.blueprint.name);
    // Используйте blueprint для валидации или отображения формы
} else {
    console.log("Entry has no blueprint assigned");
}
```

#### React пример

```tsx
interface EntryResponse {
    data: {
        id: number;
        post_type: string;
        title: string;
        blueprint?: {
            id: number;
            name: string;
            code: string;
        };
        // ... другие поля
    };
}

function EntryForm({ entryId }: { entryId: number }) {
    const [entry, setEntry] = useState<EntryResponse["data"] | null>(null);

    useEffect(() => {
        fetch(`/api/v1/admin/entries/${entryId}`)
            .then((res) => res.json())
            .then((response: EntryResponse) => {
                setEntry(response.data);
            });
    }, [entryId]);

    if (!entry) return <div>Loading...</div>;

    return (
        <div>
            <h1>{entry.title}</h1>
            {entry.blueprint && (
                <div className="blueprint-info">
                    <p>Blueprint: {entry.blueprint.name}</p>
                    <p>Code: {entry.blueprint.code}</p>
                </div>
            )}
            {/* Остальная форма */}
        </div>
    );
}
```

### Миграция кода

Если вы хотите использовать новое поле:

1. **Обновите типы/интерфейсы** (если используете TypeScript):

    ```typescript
    interface Entry {
        // ... существующие поля
        blueprint?: Blueprint; // Добавьте это поле
    }
    ```

2. **Проверяйте наличие поля** перед использованием:

    ```typescript
    if (entry.blueprint) {
        // Используйте blueprint
    }
    ```

3. **Нет необходимости в миграции**, если вы не используете это поле — всё продолжит работать как раньше.

### Связанные endpoints

-   `GET /api/v1/admin/blueprints/{blueprint}` — получить полную информацию о blueprint
-   `GET /api/v1/admin/post-types/{slug}` — получить информацию о PostType (включая blueprint_id)

---

## Как использовать этот changelog

Этот файл содержит все изменения API, которые могут повлиять на фронтенд. Проверяйте его при обновлении API или при интеграции новых функций.

### Формат записей

Каждая запись содержит:

-   **Дату изменения**
-   **Описание изменения**
-   **Endpoint**, который затронут
-   **Примеры до/после**
-   **Инструкции по миграции** (если требуется)
-   **Примеры кода** для использования

---

**Вопросы?** Обратитесь к основной документации API или к команде разработки.
