# API: Получение JSON схемы Blueprint

## Описание

Эндпоинт для получения готовой JSON схемы структуры данных Blueprint из paths. Схема представляет собой иерархическую JSON структуру со всеми полями и их свойствами.

## Эндпоинт

```
GET /api/v1/admin/blueprints/{blueprint_id}/schema
```

### Параметры

- `blueprint_id` (integer, required) - ID blueprint

### Заголовки

```
Authorization: Bearer {token}
Content-Type: application/json
```

## Формат ответа

```json
{
  "schema": {
    "field_name": {
      "type": "string|text|int|float|bool|date|datetime|json|ref",
      "required": boolean,
      "indexed": boolean,
      "cardinality": "one|many",
      "validation": {},
      "children": {
        // Вложенные поля (если data_type = json)
      }
    }
  }
}
```

## Поля схемы

| Поле | Тип | Описание |
|------|-----|----------|
| `type` | string | Тип данных поля |
| `required` | boolean | Обязательное поле |
| `indexed` | boolean | Поле индексируется |
| `cardinality` | string | `one` - одно значение, `many` - массив |
| `validation` | object | Правила валидации (JSON) |
| `children` | object | Вложенные поля (только для `data_type = json`) |

## Примеры использования

### Пример 1: Простая структура

**Запрос:**
```bash
GET /api/v1/admin/blueprints/1/schema
```

**Ответ:**
```json
{
  "schema": {
    "title": {
      "type": "string",
      "required": true,
      "indexed": true,
      "cardinality": "one",
      "validation": {}
    },
    "author": {
      "type": "json",
      "required": false,
      "indexed": false,
      "cardinality": "one",
      "validation": {},
      "children": {
        "name": {
          "type": "string",
          "required": true,
          "indexed": false,
          "cardinality": "one",
          "validation": {}
        },
        "email": {
          "type": "string",
          "required": true,
          "indexed": true,
          "cardinality": "one",
          "validation": {}
        }
      }
    }
  }
}
```

### Пример 2: Массив объектов с вложенными массивами

**Ответ:**
```json
{
  "schema": {
    "articles": {
      "type": "json",
      "required": true,
      "indexed": false,
      "cardinality": "many",
      "validation": {},
      "children": {
        "title": {
          "type": "string",
          "required": true,
          "indexed": true,
          "cardinality": "one",
          "validation": {}
        },
        "tags": {
          "type": "string",
          "required": false,
          "indexed": true,
          "cardinality": "many",
          "validation": {}
        },
        "author": {
          "type": "json",
          "required": false,
          "indexed": false,
          "cardinality": "one",
          "validation": {},
          "children": {
            "name": {
              "type": "string",
              "required": true,
              "indexed": false,
              "cardinality": "one",
              "validation": {}
            },
            "contacts": {
              "type": "json",
              "required": false,
              "indexed": false,
              "cardinality": "many",
              "validation": {},
              "children": {
                "type": {
                  "type": "string",
                  "required": true,
                  "indexed": false,
                  "cardinality": "one",
                  "validation": {}
                },
                "metadata": {
                  "type": "string",
                  "required": false,
                  "indexed": false,
                  "cardinality": "many",
                  "validation": {}
                }
              }
            }
          }
        }
      }
    }
  }
}
```

## Интерпретация схемы

### Простые поля

- `type: "string"`, `cardinality: "one"` → обычное текстовое поле
- `type: "int"`, `cardinality: "many"` → массив чисел
- `type: "string"`, `cardinality: "many"` → массив строк

### Объекты

- `type: "json"`, `cardinality: "one"` → объект с вложенными полями в `children`
- `type: "json"`, `cardinality: "many"` → массив объектов, каждый с полями в `children`

### Вложенность

Для доступа к вложенным полям используйте `children`:

```typescript
// schema.articles.children.title - поле title внутри объекта в массиве articles[]
// schema.articles.children.author.children.name - поле name внутри объекта author внутри массива articles[]
```

## Использование на фронтенде

### TypeScript пример

```typescript
interface SchemaField {
  type: string;
  required: boolean;
  indexed: boolean;
  cardinality: 'one' | 'many';
  validation: Record<string, any>;
  children?: Record<string, SchemaField>;
}

interface BlueprintSchema {
  schema: Record<string, SchemaField>;
}

// Получение схемы
async function getBlueprintSchema(blueprintId: number): Promise<BlueprintSchema> {
  const response = await fetch(`/api/v1/admin/blueprints/${blueprintId}/schema`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });
  return response.json();
}

// Генерация формы из схемы
function generateFormFromSchema(schema: BlueprintSchema) {
  const formFields = [];
  
  for (const [fieldName, field] of Object.entries(schema.schema)) {
    if (field.cardinality === 'many') {
      // Массив - нужен компонент для добавления/удаления элементов
      formFields.push({
        name: fieldName,
        type: field.type,
        isArray: true,
        required: field.required,
        children: field.children,
      });
    } else if (field.children) {
      // Объект - рекурсивно обработать children
      formFields.push({
        name: fieldName,
        type: 'object',
        required: field.required,
        children: field.children,
      });
    } else {
      // Простое поле
      formFields.push({
        name: fieldName,
        type: field.type,
        required: field.required,
        indexed: field.indexed,
      });
    }
  }
  
  return formFields;
}
```

### React пример

```typescript
function BlueprintSchemaForm({ blueprintId }: { blueprintId: number }) {
  const [schema, setSchema] = useState<BlueprintSchema | null>(null);
  
  useEffect(() => {
    getBlueprintSchema(blueprintId).then(setSchema);
  }, [blueprintId]);
  
  if (!schema) return <div>Loading...</div>;
  
  return (
    <form>
      {Object.entries(schema.schema).map(([fieldName, field]) => (
        <FieldRenderer
          key={fieldName}
          name={fieldName}
          field={field}
          path={fieldName}
        />
      ))}
    </form>
  );
}

function FieldRenderer({ name, field, path }: { 
  name: string; 
  field: SchemaField;
  path: string;
}) {
  if (field.cardinality === 'many') {
    return (
      <ArrayField
        name={name}
        field={field}
        path={path}
      />
    );
  }
  
  if (field.children) {
    return (
      <ObjectField
        name={name}
        field={field}
        path={path}
      />
    );
  }
  
  return (
    <input
      name={path}
      type={field.type === 'int' ? 'number' : 'text'}
      required={field.required}
    />
  );
}
```

## Обработка ошибок

### 404 - Blueprint не найден

```json
{
  "type": "https://stupidcms.dev/problems/not-found",
  "title": "Not Found",
  "status": 404,
  "code": "NOT_FOUND",
  "detail": "Blueprint with ID 999 does not exist."
}
```

### 401 - Не авторизован

```json
{
  "type": "https://stupidcms.dev/problems/unauthorized",
  "title": "Unauthorized",
  "status": 401,
  "code": "UNAUTHORIZED"
}
```

## Заметки

1. **Пустая схема**: Если у blueprint нет paths, вернётся `{"schema": {}}`
2. **Сортировка**: Поля в схеме отсортированы по `sort_order` из paths
3. **Валидация**: Поле `validation` содержит JSON правила валидации из paths
4. **Материализация**: Схема включает все поля (собственные + материализованные из embeds)

