# Валидация Blueprint для фронтенда

Документация по работе с правилами валидации Blueprint на фронтенде.

## Содержание

1. [Получение правил валидации](#получение-правил-валидации)
2. [Формат правил валидации](#формат-правил-валидации)
3. [Конфигурирование правил валидации](#конфигурирование-правил-валидации)
4. [Отправка данных на бэкенд](#отправка-данных-на-бэкенд)
5. [Примеры использования](#примеры-использования)

---

## Получение правил валидации

### Способ 1: Получение Path с правилами валидации

**Endpoint:** `GET /api/v1/admin/blueprints/{blueprint_id}/paths`

**Ответ:**

```json
{
    "data": [
        {
            "id": 1,
            "blueprint_id": 1,
            "parent_id": null,
            "name": "title",
            "full_path": "title",
            "data_type": "string",
            "cardinality": "one",
            "is_required": true,
            "is_indexed": true,
            "is_readonly": false,
            "sort_order": 0,
            "validation_rules": {
                "min": 5,
                "max": 500
            },
            "children": [],
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        },
        {
            "id": 2,
            "blueprint_id": 1,
            "parent_id": null,
            "name": "author",
            "full_path": "author",
            "data_type": "json",
            "cardinality": "one",
            "is_required": true,
            "is_indexed": false,
            "is_readonly": false,
            "sort_order": 1,
            "validation_rules": null,
            "children": [
                {
                    "id": 3,
                    "name": "name",
                    "full_path": "author.name",
                    "data_type": "string",
                    "cardinality": "one",
                    "is_required": true,
                    "validation_rules": {
                        "pattern": "/^[a-z\\s]+$/i"
                    }
                },
                {
                    "id": 4,
                    "name": "email",
                    "full_path": "author.email",
                    "data_type": "string",
                    "cardinality": "one",
                    "is_required": true,
                    "validation_rules": {
                        "pattern": "/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i"
                    }
                }
            ]
        }
    ]
}
```

### Способ 2: Получение JSON схемы Blueprint

**Endpoint:** `GET /api/v1/admin/blueprints/{blueprint_id}/schema`

**Ответ:**

```json
{
    "schema": {
        "title": {
            "type": "string",
            "required": true,
            "indexed": true,
            "cardinality": "one",
            "validation": {
                "min": 5,
                "max": 500
            }
        },
        "author": {
            "type": "json",
            "required": true,
            "indexed": false,
            "cardinality": "one",
            "validation": {},
            "children": {
                "name": {
                    "type": "string",
                    "required": true,
                    "indexed": false,
                    "cardinality": "one",
                    "validation": {
                        "pattern": "/^[a-z\\s]+$/i"
                    }
                },
                "email": {
                    "type": "string",
                    "required": true,
                    "indexed": true,
                    "cardinality": "one",
                    "validation": {
                        "pattern": "/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i"
                    }
                }
            }
        },
        "tags": {
            "type": "string",
            "required": false,
            "indexed": true,
            "cardinality": "many",
            "validation": {
                "array_min_items": 2,
                "array_max_items": 10,
                "min": 3,
                "max": 50
            }
        }
    }
}
```

**Преимущества схемы:**

-   Удобная иерархическая структура
-   Все правила валидации в одном месте
-   Легко преобразовать в форму для фронтенда

---

## Формат правил валидации

### Структура validation_rules

`validation_rules` — это объект JSON, который может содержать следующие правила:

```typescript
interface ValidationRules {
    // Минимальное/максимальное значение или длина
    min?: number;
    max?: number;

    // Регулярное выражение
    pattern?: string;

    // Правила для массивов (только для cardinality: 'many')
    array_min_items?: number;
    array_max_items?: number;
    array_unique?: boolean;

    // Условные правила
    required_if?: string | ConditionalRule;
    prohibited_unless?: string | ConditionalRule;
    required_unless?: string | ConditionalRule;
    prohibited_if?: string | ConditionalRule;

    // Уникальность значения
    unique?: string | UniqueRule;

    // Существование значения
    exists?: string | ExistsRule;

    // Сравнение полей
    field_comparison?: FieldComparisonRule;
}

// Условное правило
interface ConditionalRule {
    field: string; // Путь к полю (например, 'is_published')
    value?: any; // Значение для сравнения
    operator?: "==" | "!=" | ">" | "<" | ">=" | "<="; // Оператор (по умолчанию '==')
}

// Правило уникальности
interface UniqueRule {
    table: string; // Таблица для проверки
    column?: string; // Колонка (по умолчанию 'id')
    except?: {
        column: string; // Колонка для исключения
        value: any; // Значение для исключения
    };
    where?: {
        column: string; // Дополнительная колонка для WHERE
        value: any; // Значение для WHERE
    };
}

// Правило существования
interface ExistsRule {
    table: string; // Таблица для проверки
    column?: string; // Колонка (по умолчанию 'id')
    where?: {
        column: string; // Дополнительная колонка для WHERE
        value: any; // Значение для WHERE
    };
}

// Правило сравнения полей
interface FieldComparisonRule {
    operator: ">=" | "<=" | ">" | "<" | "==" | "!=";
    field?: string; // Путь к другому полю (например, 'content_json.start_date')
    value?: any; // Константное значение для сравнения
}
```

### Типы данных (data_type)

-   `string` — строка
-   `text` — многострочный текст
-   `int` — целое число
-   `float` — число с плавающей точкой
-   `bool` — булево значение
-   `date` — дата (YYYY-MM-DD)
-   `datetime` — дата и время (ISO 8601)
-   `json` — вложенный объект
-   `ref` — ссылка на другую сущность (ID)

### Кардинальность (cardinality)

-   `one` — одиночное значение
-   `many` — массив значений

---

## Конфигурирование правил валидации

### Создание/обновление Path с правилами валидации

**Endpoint:** `POST /api/v1/admin/blueprints/{blueprint_id}/paths`

**Запрос:**

```json
{
    "name": "title",
    "data_type": "string",
    "cardinality": "one",
    "is_required": true,
    "is_indexed": true,
    "validation_rules": {
        "min": 5,
        "max": 500
    }
}
```

**Endpoint:** `PUT /api/v1/admin/blueprints/{blueprint_id}/paths/{path_id}`

**Запрос:**

```json
{
    "validation_rules": {
        "min": 10,
        "max": 1000,
        "pattern": "/^[A-Z]/"
    }
}
```

### Примеры конфигурации правил

#### 1. Простое поле с min/max

```json
{
    "name": "title",
    "data_type": "string",
    "is_required": true,
    "validation_rules": {
        "min": 5,
        "max": 500
    }
}
```

#### 2. Поле с регулярным выражением

```json
{
    "name": "email",
    "data_type": "string",
    "is_required": true,
    "validation_rules": {
        "pattern": "/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i"
    }
}
```

#### 3. Массив с валидацией элементов

```json
{
    "name": "tags",
    "data_type": "string",
    "cardinality": "many",
    "is_required": false,
    "validation_rules": {
        "array_min_items": 2,
        "array_max_items": 10,
        "min": 3,
        "max": 50,
        "array_unique": true
    }
}
```

**Примечание:**

-   `array_min_items` и `array_max_items` применяются к самому массиву
-   `min` и `max` применяются к элементам массива
-   `array_unique` проверяет уникальность элементов

#### 4. Условное правило (required_if)

**Простой формат:**

```json
{
    "name": "published_at",
    "data_type": "date",
    "is_required": false,
    "validation_rules": {
        "required_if": "is_published"
    }
}
```

**Расширенный формат:**

```json
{
    "name": "published_at",
    "data_type": "date",
    "is_required": false,
    "validation_rules": {
        "required_if": {
            "field": "is_published",
            "value": true,
            "operator": "=="
        }
    }
}
```

**Старый формат (совместимость):**

```json
{
    "validation_rules": {
        "required_if": {
            "is_published": true
        }
    }
}
```

#### 5. Правило уникальности

**Простой формат:**

```json
{
    "name": "slug",
    "data_type": "string",
    "is_required": true,
    "validation_rules": {
        "unique": "entries"
    }
}
```

**Расширенный формат:**

```json
{
    "name": "slug",
    "data_type": "string",
    "is_required": true,
    "validation_rules": {
        "unique": {
            "table": "entries",
            "column": "slug",
            "except": {
                "column": "id",
                "value": 1
            },
            "where": {
                "column": "status",
                "value": "published"
            }
        }
    }
}
```

#### 6. Правило существования

**Простой формат:**

```json
{
    "name": "category_id",
    "data_type": "ref",
    "is_required": true,
    "validation_rules": {
        "exists": "categories"
    }
}
```

**Расширенный формат:**

```json
{
    "name": "category_id",
    "data_type": "ref",
    "is_required": true,
    "validation_rules": {
        "exists": {
            "table": "categories",
            "column": "id",
            "where": {
                "column": "status",
                "value": "active"
            }
        }
    }
}
```

#### 7. Сравнение полей

**Сравнение с другим полем:**

```json
{
    "name": "end_date",
    "data_type": "date",
    "is_required": true,
    "validation_rules": {
        "field_comparison": {
            "operator": ">=",
            "field": "content_json.start_date"
        }
    }
}
```

**Сравнение с константой:**

```json
{
    "name": "start_date",
    "data_type": "date",
    "is_required": true,
    "validation_rules": {
        "field_comparison": {
            "operator": ">=",
            "value": "2024-01-01"
        }
    }
}
```

---

## Отправка данных на бэкенд

### Формат content_json

При создании или обновлении Entry поле `content_json` должно быть отправлено как объект JSON, структура которого соответствует схеме Blueprint.

**Endpoint:** `POST /api/v1/admin/entries`

**Запрос:**

```json
{
    "post_type": "article",
    "title": "My Article",
    "content_json": {
        "title": "Article Title",
        "author": {
            "name": "John Doe",
            "email": "john@example.com"
        },
        "tags": ["tag1", "tag2", "tag3"]
    }
}
```

**Endpoint:** `PUT /api/v1/admin/entries/{id}`

**Запрос:**

```json
{
    "content_json": {
        "title": "Updated Title",
        "author": {
            "name": "Jane Doe",
            "email": "jane@example.com"
        }
    }
}
```

### Важные замечания

1. **Пустые объекты:** Пустые объекты должны быть отправлены как `{}`, а не как `null` или `[]`
2. **Массивы:** Массивы отправляются как JSON массивы `[]`
3. **Вложенность:** Вложенные объекты соответствуют структуре Path в Blueprint
4. **Типы данных:**
    - `string` → строка
    - `int` → число
    - `float` → число с плавающей точкой
    - `bool` → `true`/`false`
    - `date` → строка в формате `YYYY-MM-DD`
    - `datetime` → строка в формате ISO 8601
    - `json` → объект или массив
    - `ref` → число (ID)

### Формат ответа

**Ответ при успешном создании/обновлении:**

```json
{
    "data": {
        "id": 42,
        "post_type": "article",
        "title": "My Article",
        "slug": "my-article",
        "status": "draft",
        "content_json": {
            "title": "Article Title",
            "author": {
                "name": "John Doe",
                "email": "john@example.com"
            },
            "tags": ["tag1", "tag2", "tag3"]
        },
        "meta_json": {},
        "is_published": false,
        "published_at": null,
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Примечание:** В ответе `content_json` всегда является объектом (даже если пустой), а не массивом или `null`.

### Ошибки валидации

При ошибках валидации бэкенд возвращает ответ с кодом `422`:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "content_json.title": ["The content_json.title field is required."],
        "content_json.title": [
            "The content_json.title must be at least 5 characters."
        ],
        "content_json.author.email": [
            "The content_json.author.email format is invalid."
        ],
        "content_json.tags": [
            "The content_json.tags must have at least 2 items."
        ],
        "content_json.tags.0": [
            "The content_json.tags.0 must be at least 3 characters."
        ]
    }
}
```

**Структура ошибок:**

-   Путь к полю в точечной нотации: `content_json.{field_path}`
-   Для массивов: `content_json.{field_path}.{index}` или `content_json.{field_path}.*`
-   Массив сообщений об ошибках для каждого поля

---

## Примеры использования

### Пример 1: Простая форма с валидацией

```typescript
// Получаем схему Blueprint
const response = await fetch("/api/v1/admin/blueprints/1/schema");
const { schema } = await response.json();

// Строим форму на основе схемы
function buildForm(schema: any) {
    const form: Record<string, any> = {};

    for (const [fieldName, fieldSchema] of Object.entries(schema)) {
        if (fieldSchema.cardinality === "many") {
            form[fieldName] = [];
        } else if (fieldSchema.type === "json") {
            form[fieldName] = {};
        } else {
            form[fieldName] = fieldSchema.type === "bool" ? false : "";
        }
    }

    return form;
}

// Валидация на фронтенде
function validateField(value: any, schema: any): string[] {
    const errors: string[] = [];
    const rules = schema.validation || {};

    // Проверка required
    if (
        schema.required &&
        (value === null || value === undefined || value === "")
    ) {
        errors.push("Field is required");
    }

    // Проверка min
    if (rules.min !== undefined) {
        if (schema.type === "string" && value.length < rules.min) {
            errors.push(`Minimum length is ${rules.min}`);
        } else if (
            ["int", "float"].includes(schema.type) &&
            value < rules.min
        ) {
            errors.push(`Minimum value is ${rules.min}`);
        }
    }

    // Проверка max
    if (rules.max !== undefined) {
        if (schema.type === "string" && value.length > rules.max) {
            errors.push(`Maximum length is ${rules.max}`);
        } else if (
            ["int", "float"].includes(schema.type) &&
            value > rules.max
        ) {
            errors.push(`Maximum value is ${rules.max}`);
        }
    }

    // Проверка pattern
    if (rules.pattern && schema.type === "string") {
        const regex = new RegExp(rules.pattern.slice(1, -1));
        if (!regex.test(value)) {
            errors.push("Format is invalid");
        }
    }

    return errors;
}
```

### Пример 2: Обработка массивов

```typescript
// Валидация массива
function validateArray(value: any[], schema: any): string[] {
    const errors: string[] = [];
    const rules = schema.validation || {};

    // Проверка array_min_items
    if (
        rules.array_min_items !== undefined &&
        value.length < rules.array_min_items
    ) {
        errors.push(`Minimum ${rules.array_min_items} items required`);
    }

    // Проверка array_max_items
    if (
        rules.array_max_items !== undefined &&
        value.length > rules.array_max_items
    ) {
        errors.push(`Maximum ${rules.array_max_items} items allowed`);
    }

    // Проверка array_unique
    if (rules.array_unique && new Set(value).size !== value.length) {
        errors.push("All items must be unique");
    }

    // Валидация элементов массива
    const elementSchema = { ...schema, cardinality: "one" };
    value.forEach((item, index) => {
        const itemErrors = validateField(item, elementSchema);
        if (itemErrors.length > 0) {
            errors.push(`Item ${index + 1}: ${itemErrors.join(", ")}`);
        }
    });

    return errors;
}
```

### Пример 3: Отправка данных на бэкенд

```typescript
async function createEntry(postType: string, formData: any) {
    const response = await fetch("/api/v1/admin/entries", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
            post_type: postType,
            title: formData.title,
            content_json: formData.content_json,
        }),
    });

    if (!response.ok) {
        const error = await response.json();
        // Обработка ошибок валидации
        if (response.status === 422) {
            console.error("Validation errors:", error.errors);
            return { success: false, errors: error.errors };
        }
        throw new Error("Failed to create entry");
    }

    const result = await response.json();
    return { success: true, data: result.data };
}
```

### Пример 4: Обработка условных правил

```typescript
function checkConditionalRules(formData: any, schema: any): boolean {
    for (const [fieldName, fieldSchema] of Object.entries(schema)) {
        const rules = fieldSchema.validation || {};

        // Проверка required_if
        if (rules.required_if) {
            const condition =
                typeof rules.required_if === "string"
                    ? { field: rules.required_if, value: true }
                    : rules.required_if;

            const conditionValue = getNestedValue(formData, condition.field);
            const shouldBeRequired = compareValues(
                conditionValue,
                condition.value,
                condition.operator
            );

            if (shouldBeRequired && !formData[fieldName]) {
                return false; // Поле должно быть заполнено
            }
        }
    }

    return true;
}

function getNestedValue(obj: any, path: string): any {
    return path.split(".").reduce((acc, key) => acc?.[key], obj);
}

function compareValues(a: any, b: any, operator: string = "=="): boolean {
    switch (operator) {
        case "==":
            return a === b;
        case "!=":
            return a !== b;
        case ">":
            return a > b;
        case "<":
            return a < b;
        case ">=":
            return a >= b;
        case "<=":
            return a <= b;
        default:
            return a === b;
    }
}
```

---

## Рекомендации

1. **Кэширование схемы:** Схему Blueprint рекомендуется кэшировать на фронтенде, так как она редко изменяется
2. **Валидация на фронтенде:** Реализуйте валидацию на фронтенде для улучшения UX, но всегда полагайтесь на валидацию бэкенда
3. **Обработка ошибок:** Обрабатывайте ошибки валидации и отображайте их пользователю в понятном виде
4. **Типизация:** Используйте TypeScript для типизации схемы и данных формы
5. **Нормализация данных:** Нормализуйте данные перед отправкой (пустые строки → null, форматирование дат и т.д.)

---

## Дополнительные ресурсы

-   [Документация системы валидации Blueprint](./blueprint-validation-system.md) — подробное описание бэкенд-системы
-   [API документация](../generated/http-endpoints.md) — полная документация API endpoints

