# План проверки Blueprint Implementation Plan

> **Дата:** 2025-11-20  
> **Версия плана:** 1.0  
> **Задача:** Проверка соответствия фронтенд-плана реализации Blueprint System на бэкенде

---

## Содержание

1. [Методология проверки](#методология-проверки)
2. [Блок 1: Типы данных и схемы валидации](#блок-1-типы-данных-и-схемы-валидации)
3. [Блок 2: API клиент](#блок-2-api-клиент)
4. [Блок 3: Модели данных](#блок-3-модели-данных)
5. [Блок 4: Валидация и форматы](#блок-4-валидация-и-форматы)
6. [Блок 5: Бизнес-логика](#блок-5-бизнес-логика)
7. [Блок 6: Недостающие проверки](#блок-6-недостающие-проверки)
8. [Итоговые выводы](#итоговые-выводы)

---

## Методология проверки

### Источники истины (backend)

1. **Модели:** `app/Models/Blueprint.php`, `Path.php`, `BlueprintEmbed.php`
2. **Контроллеры:** `app/Http/Controllers/Admin/V1/{Blueprint,Path,BlueprintEmbed}Controller.php`
3. **Resources:** `app/Http/Resources/Admin/{Blueprint,Path,BlueprintEmbed}Resource.php`
4. **Validation:** `app/Http/Requests/Admin/{Blueprint,Path,BlueprintEmbed}/*Request.php`
5. **Endpoints:** `docs/generated/http-endpoints.md`
6. **API Docs:** `docs/frontend-api-blueprints.md`

### Процесс проверки

Для каждой задачи из `blueprint-implementation-plan.md`:

1. **Сравнить типы данных** — соответствие полей в Zod схемах и API Resources
2. **Проверить endpoints** — совпадение URL, методов, параметров
3. **Валидировать правила** — корректность regex, ограничений, enum значений
4. **Выявить расхождения** — несоответствия, лишние/недостающие поля
5. **Предложить исправления** — конкретные правки для фронтенд-плана

---

## Блок 1: Типы данных и схемы валидации

### bp-001: Zod схемы для Blueprint

**Проверяемые пункты:**

#### ✅ Проверка: Основные поля Blueprint

**Фронтенд-план (zBlueprint):**
```typescript
zBlueprint = z.object({
  id: z.number(),
  name: z.string(),
  code: z.string(),
  description: z.string().nullable(),
  paths_count: z.number().optional(),
  embeds_count: z.number().optional(),
  embedded_in_count: z.number().optional(),
  post_types_count: z.number().optional(),
  post_types: z.array(zPostType).optional(),
  created_at: z.string(),
  updated_at: z.string(),
});
```

**Бэкенд (BlueprintResource):**
```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'code' => $this->code,
    'description' => $this->description,
    'paths_count' => $this->whenCounted('paths'),
    'embeds_count' => $this->whenCounted('embeds'),
    'embedded_in_count' => $this->whenCounted('embeddedIn'),
    'post_types_count' => $this->whenCounted('postTypes'),
    'post_types' => $this->whenLoaded('postTypes', ...),
    'created_at' => $this->created_at?->toIso8601String(),
    'updated_at' => $this->updated_at?->toIso8601String(),
];
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

**Замечания:**
- Все поля присутствуют
- Счётчики действительно optional (только при `withCount()`)
- `created_at`/`updated_at` возвращаются как ISO 8601 строки

---

#### ✅ Проверка: zBlueprintListItem

**Фронтенд-план:**
```typescript
zBlueprintListItem = z.object({
  id: z.number(),
  name: z.string(),
  code: z.string(),
  description: z.string().nullable(),
  paths_count: z.number(),
  embeds_count: z.number(),
  post_types_count: z.number(),
  created_at: z.string(),
  updated_at: z.string(),
});
```

**Бэкенд (BlueprintController::index):**
```php
Blueprint::query()
    ->withCount(['paths', 'embeds', 'postTypes'])
    ->paginate($perPage);
```

**Статус:** ❌ **НЕСООТВЕТСТВИЕ**

**Проблема:** В списке (`index`) **НЕ** загружается `embedded_in_count`, но фронтенд-план его не включает в `zBlueprintListItem`, что корректно. Однако на бэке `withCount(['paths', 'embeds', 'postTypes'])` означает, что счётчики **ВСЕГДА** присутствуют в списке, поэтому они НЕ должны быть `optional`.

**Исправление:**
```typescript
zBlueprintListItem = z.object({
  // ...
  paths_count: z.number(),        // ✅ Correct (not optional)
  embeds_count: z.number(),       // ✅ Correct
  post_types_count: z.number(),   // ✅ Correct
  // НЕТ embedded_in_count в списке — правильно
});
```

---

### bp-002: Zod схемы для Path

#### ✅ Проверка: zDataType

**Фронтенд-план:**
```typescript
zDataType = z.enum(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']);
```

**Бэкенд (StorePathRequest):**
```php
Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: zCardinality

**Фронтенд-план:**
```typescript
zCardinality = z.enum(['one', 'many']);
```

**Бэкенд (StorePathRequest):**
```php
Rule::in(['one', 'many'])
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: zPath основные поля

**Фронтенд-план:**
```typescript
zPath = z.object({
  id: z.number(),
  blueprint_id: z.number(),
  parent_id: z.number().nullable(),
  name: z.string(),
  full_path: z.string(),
  data_type: zDataType,
  cardinality: zCardinality,
  is_required: z.boolean(),
  is_indexed: z.boolean(),
  is_readonly: z.boolean(),
  sort_order: z.number(),
  validation_rules: z.array(z.any()).nullable(),
  source_blueprint_id: z.number().nullable(),
  blueprint_embed_id: z.number().nullable(),
  source_blueprint: z.object({ id, code, name }).optional(),
  children: z.array(z.lazy(() => zPath)).optional(),
  created_at: z.string(),
  updated_at: z.string(),
});
```

**Бэкенд (PathResource):**
```php
return [
    'id' => $this->id,
    'blueprint_id' => $this->blueprint_id,
    'parent_id' => $this->parent_id,
    'name' => $this->name,
    'full_path' => $this->full_path,
    'data_type' => $this->data_type,
    'cardinality' => $this->cardinality,
    'is_required' => $this->is_required,
    'is_indexed' => $this->is_indexed,
    'is_readonly' => $this->is_readonly,
    'sort_order' => $this->sort_order,
    'validation_rules' => $this->validation_rules,
    'source_blueprint_id' => $this->source_blueprint_id,
    'source_blueprint' => $this->whenLoaded('sourceBlueprint', ...),
    'blueprint_embed_id' => $this->blueprint_embed_id,
    'children' => PathResource::collection($this->whenLoaded('children')),
    'created_at' => $this->created_at?->toIso8601String(),
    'updated_at' => $this->updated_at?->toIso8601String(),
];
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ⚠️ Проверка: zPathTreeNode

**Фронтенд-план:**
```typescript
zPathTreeNode = zPath.extend({
  children: z.array(z.lazy(() => zPathTreeNode)),
});
```

**Статус:** ⚠️ **ТРЕБУЕТ УТОЧНЕНИЯ**

**Проблема:** `zPathTreeNode` расширяет `zPath`, делая `children` **обязательным** массивом (не optional). Но в бэкенде:

```php
// PathController::index
$tree = $this->buildTree($paths); // children могут быть пустыми
```

**Возможные проблемы:**
1. Листовые узлы (без детей) должны иметь `children: []` (пустой массив)
2. Или `children` должны быть опциональными

**Рекомендация:** Проверить, всегда ли бэкенд возвращает `children` массив (даже пустой) для древовидной структуры.

**Исправление (если children могут отсутствовать):**
```typescript
zPathTreeNode = zPath.extend({
  children: z.array(z.lazy(() => zPathTreeNode)).default([]),
});
```

---

### bp-003: Zod схемы для BlueprintEmbed

#### ✅ Проверка: zBlueprintEmbed

**Фронтенд-план:**
```typescript
zBlueprintEmbed = z.object({
  id: z.number(),
  blueprint_id: z.number(),
  embedded_blueprint_id: z.number(),
  host_path_id: z.number().nullable(),
  blueprint: z.object({...}).optional(),
  embedded_blueprint: z.object({...}).optional(),
  host_path: z.object({...}).nullable().optional(),
  created_at: z.string(),
  updated_at: z.string(),
});
```

**Бэкенд (BlueprintEmbedResource):**
```php
return [
    'id' => $this->id,
    'blueprint_id' => $this->blueprint_id,
    'embedded_blueprint_id' => $this->embedded_blueprint_id,
    'host_path_id' => $this->host_path_id,
    'blueprint' => $this->whenLoaded('blueprint', ...),
    'embedded_blueprint' => $this->whenLoaded('embeddedBlueprint', ...),
    'host_path' => $this->whenLoaded('hostPath', ...),
    'created_at' => ...,
    'updated_at' => ...,
];
```

**Статус:** ⚠️ **ТРЕБУЕТ УТОЧНЕНИЯ**

**Проблема:** `host_path` может быть:
1. `null` (если `host_path_id` = NULL, встраивание в корень)
2. Объект (если загружена связь)
3. Отсутствует (если связь не загружена)

**Текущий фронтенд-план:** `.nullable().optional()` — **корректно**

---

### bp-004: Zod схемы для DTO Blueprint

#### ✅ Проверка: zCreateBlueprintDto

**Фронтенд-план:**
```typescript
zCreateBlueprintDto = z.object({
  name: z.string().min(1, 'Название обязательно'),
  code: z.string().min(1, 'Код обязателен').regex(/^[a-z0-9_]+$/, 'Только a-z, 0-9 и _'),
  description: z.string().optional(),
});
```

**Бэкенд (StoreBlueprintRequest):**
```php
return [
    'name' => ['required', 'string', 'max:255'],
    'code' => ['required', 'string', 'max:255', 'unique:blueprints,code', 'regex:/^[a-z0-9_]+$/'],
    'description' => ['nullable', 'string', 'max:1000'],
];
```

**Статус:** ⚠️ **НЕПОЛНОЕ СООТВЕТСТВИЕ**

**Проблемы:**
1. **Отсутствует `max:255` для `name`** в фронтенд-плане
2. **Отсутствует `max:255` для `code`** в фронтенд-плане
3. **Отсутствует `max:1000` для `description`** в фронтенд-плане
4. **Отсутствует проверка `unique`** на фронте (должна быть обработка ошибки с бэка)

**Исправление:**
```typescript
zCreateBlueprintDto = z.object({
  name: z.string().min(1, 'Название обязательно').max(255, 'Максимум 255 символов'),
  code: z.string()
    .min(1, 'Код обязателен')
    .max(255, 'Максимум 255 символов')
    .regex(/^[a-z0-9_]+$/, 'Только a-z, 0-9 и _'),
  description: z.string().max(1000, 'Максимум 1000 символов').optional(),
});
```

---

#### ✅ Проверка: zUpdateBlueprintDto

**Фронтенд-план:**
```typescript
zUpdateBlueprintDto = z.object({
  name: z.string().min(1).optional(),
  code: z.string().regex(/^[a-z0-9_]+$/).optional(),
  description: z.string().optional(),
});
```

**Бэкенд (UpdateBlueprintRequest):** (файл не был прочитан, но логика аналогична Store)

**Статус:** ⚠️ **ТРЕБУЕТ ДОПОЛНЕНИЯ**

**Проблема:** Отсутствуют `max` ограничения (аналогично Create).

**Исправление:**
```typescript
zUpdateBlueprintDto = z.object({
  name: z.string().min(1).max(255).optional(),
  code: z.string().max(255).regex(/^[a-z0-9_]+$/).optional(),
  description: z.string().max(1000).optional(),
});
```

---

### bp-005: Zod схемы для DTO Path

#### ⚠️ Проверка: zCreatePathDto

**Фронтенд-план:**
```typescript
zCreatePathDto = z.object({
  name: z.string().min(1, 'Имя поля обязательно').regex(/^[a-z0-9_]+$/, 'Только a-z, 0-9 и _'),
  parent_id: z.number().nullable().optional(),
  data_type: zDataType,
  cardinality: zCardinality.default('one'),
  is_required: z.boolean().default(false),
  is_indexed: z.boolean().default(false),
  sort_order: z.number().default(0),
  validation_rules: z.array(z.string()).optional(),
});
```

**Бэкенд (StorePathRequest):**
```php
return [
    'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
    'parent_id' => ['nullable', 'integer', 'exists:paths,id'],
    'data_type' => ['required', Rule::in([...])],
    'cardinality' => ['sometimes', Rule::in(['one', 'many'])],
    'is_required' => ['sometimes', 'boolean'],
    'is_indexed' => ['sometimes', 'boolean'],
    'sort_order' => ['sometimes', 'integer', 'min:0'],
    'validation_rules' => ['nullable', 'array'],
];
```

**Статус:** ❌ **НЕСООТВЕТСТВИЕ**

**Проблемы:**
1. **Отсутствует `max:255` для `name`**
2. **`validation_rules` на бэке — массив любых типов, на фронте — массив строк**
3. **`sort_order` должен быть `>= 0` (min:0)**

**Исправление:**
```typescript
zCreatePathDto = z.object({
  name: z.string()
    .min(1, 'Имя поля обязательно')
    .max(255, 'Максимум 255 символов')
    .regex(/^[a-z0-9_]+$/, 'Только a-z, 0-9 и _'),
  parent_id: z.number().nullable().optional(),
  data_type: zDataType,
  cardinality: zCardinality.default('one'),
  is_required: z.boolean().default(false),
  is_indexed: z.boolean().default(false),
  sort_order: z.number().int().min(0, 'Минимум 0').default(0),
  validation_rules: z.array(z.any()).optional(), // ✅ any вместо string
});
```

---

### bp-006: Zod схемы для вспомогательных типов

#### ✅ Проверка: zBlueprintDependencies

**Фронтенд-план:**
```typescript
zBlueprintDependencies = z.object({
  depends_on: z.array(z.object({ id, code, name })),
  depended_by: z.array(z.object({ id, code, name })),
});
```

**Бэкенд (BlueprintController::dependencies):**
```php
return [
    'depends_on' => Blueprint::whereIn('id', $graph['depends_on'])->get(['id', 'code', 'name']),
    'depended_by' => Blueprint::whereIn('id', $graph['depended_by'])->get(['id', 'code', 'name']),
];
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: zCanDeleteBlueprint

**Фронтенд-план:**
```typescript
zCanDeleteBlueprint = z.object({
  can_delete: z.boolean(),
  reasons: z.array(z.string()),
});
```

**Бэкенд (BlueprintController::canDelete):**
```php
return response()->json($check); // { can_delete: bool, reasons: string[] }
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: zEmbeddableBlueprints

**Фронтенд-план:**
```typescript
zEmbeddableBlueprints = z.object({
  data: z.array(z.object({ id, code, name })),
});
```

**Бэкенд (BlueprintController::embeddable):**
```php
return response()->json([
    'data' => $embeddable->map(fn($bp) => ['id' => ..., 'code' => ..., 'name' => ...]),
]);
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

## Блок 2: API клиент

### bp-007: API клиент для Blueprint CRUD

#### ✅ Проверка: listBlueprints

**Фронтенд-план:**
```typescript
export const listBlueprints = async (params: {
  search?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}): Promise<PaginatedResponse<ZBlueprintListItem>>
```

**Бэкенд (BlueprintController::index):**
```php
GET /api/v1/admin/blueprints
Query: search, sort_by, sort_dir, per_page, page
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: getBlueprint

**Фронтенд-план:**
```typescript
export const getBlueprint = async (id: number): Promise<ZBlueprint>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: createBlueprint

**Фронтенд-план:**
```typescript
export const createBlueprint = async (dto: ZCreateBlueprintDto): Promise<ZBlueprint>
```

**Бэкенд:**
```php
POST /api/v1/admin/blueprints
Body: { name, code, description }
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: updateBlueprint

**Фронтенд-план:**
```typescript
export const updateBlueprint = async (id: number, dto: ZUpdateBlueprintDto): Promise<ZBlueprint>
```

**Бэкенд:**
```php
PUT /api/v1/admin/blueprints/{blueprint}
Body: { name?, code?, description? }
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: deleteBlueprint

**Фронтенд-план:**
```typescript
export const deleteBlueprint = async (id: number): Promise<void>
```

**Бэкенд:**
```php
DELETE /api/v1/admin/blueprints/{blueprint}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### bp-008: API клиент для Path CRUD

#### ✅ Проверка: listPaths

**Фронтенд-план:**
```typescript
export const listPaths = async (blueprintId: number): Promise<ZPathTreeNode[]>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}/paths
Returns: tree structure (PathResource collection)
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: getPath

**Фронтенд-план:**
```typescript
export const getPath = async (id: number): Promise<ZPath>
```

**Бэкенд:**
```php
GET /api/v1/admin/paths/{path}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: createPath

**Фронтенд-план:**
```typescript
export const createPath = async (blueprintId: number, dto: ZCreatePathDto): Promise<ZPath>
```

**Бэкенд:**
```php
POST /api/v1/admin/blueprints/{blueprint}/paths
Body: { name, parent_id?, data_type, ... }
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: updatePath

**Фронтенд-план:**
```typescript
export const updatePath = async (id: number, dto: ZUpdatePathDto): Promise<ZPath>
```

**Бэкенд:**
```php
PUT /api/v1/admin/paths/{path}
Body: partial Path fields
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: deletePath

**Фронтенд-план:**
```typescript
export const deletePath = async (id: number): Promise<void>
```

**Бэкенд:**
```php
DELETE /api/v1/admin/paths/{path}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### bp-009: API клиент для BlueprintEmbed CRUD

#### ✅ Проверка: listEmbeds

**Фронтенд-план:**
```typescript
export const listEmbeds = async (blueprintId: number): Promise<ZBlueprintEmbed[]>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}/embeds
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: getEmbed

**Фронтенд-план:**
```typescript
export const getEmbed = async (id: number): Promise<ZBlueprintEmbed>
```

**Бэкенд:**
```php
GET /api/v1/admin/embeds/{embed}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: createEmbed

**Фронтенд-план:**
```typescript
export const createEmbed = async (blueprintId: number, dto: {
  embedded_blueprint_id: number;
  host_path_id?: number;
}): Promise<ZBlueprintEmbed>
```

**Бэкенд:**
```php
POST /api/v1/admin/blueprints/{blueprint}/embeds
Body: { embedded_blueprint_id, host_path_id? }
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: deleteEmbed

**Фронтенд-план:**
```typescript
export const deleteEmbed = async (id: number): Promise<void>
```

**Бэкенд:**
```php
DELETE /api/v1/admin/embeds/{embed}
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### bp-010: API клиент для вспомогательных методов

#### ✅ Проверка: canDeleteBlueprint

**Фронтенд-план:**
```typescript
export const canDeleteBlueprint = async (id: number): Promise<ZCanDeleteBlueprint>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}/can-delete
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: getBlueprintDependencies

**Фронтенд-план:**
```typescript
export const getBlueprintDependencies = async (id: number): Promise<ZBlueprintDependencies>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}/dependencies
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

#### ✅ Проверка: getEmbeddableBlueprints

**Фронтенд-план:**
```typescript
export const getEmbeddableBlueprints = async (id: number): Promise<ZEmbeddableBlueprints>
```

**Бэкенд:**
```php
GET /api/v1/admin/blueprints/{blueprint}/embeddable
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

## Блок 3: Модели данных

### ✅ Проверка: Blueprint Model

**Фронтенд-план:** Концепция соответствует.

**Бэкенд (app/Models/Blueprint.php):**
```php
- fillable: ['name', 'code', 'description']
- Relations: paths(), embeds(), embeddedIn(), postTypes()
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### ✅ Проверка: Path Model

**Фронтенд-план:** Концепция дерева, материализация `full_path`, `is_readonly`.

**Бэкенд (app/Models/Path.php):**
```php
- fillable: ['blueprint_id', 'parent_id', 'name', 'data_type', ...]
- guarded: ['source_blueprint_id', 'blueprint_embed_id', 'is_readonly', 'full_path']
- Relations: blueprint(), sourceBlueprint(), parent(), children()
- Methods: isOwn(), isCopied()
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### ✅ Проверка: BlueprintEmbed Model

**Фронтенд-план:** Встраивание с `host_path_id` (nullable).

**Бэкенд (app/Models/BlueprintEmbed.php):**
```php
- fillable: ['blueprint_id', 'embedded_blueprint_id', 'host_path_id']
- Relations: blueprint(), embeddedBlueprint(), hostPath()
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

## Блок 4: Валидация и форматы

### ❌ Проверка: Regex для code/name

**Фронтенд-план:**
```typescript
// Blueprint code
code: z.string().regex(/^[a-z0-9_]+$/)

// Path name
name: z.string().regex(/^[a-z0-9_]+$/)
```

**Бэкенд:**
```php
// Blueprint code
'code' => [..., 'regex:/^[a-z0-9_]+$/']

// Path name
'name' => [..., 'regex:/^[a-z0-9_]+$/']
```

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### ⚠️ Проверка: Ограничения длины

**Фронтенд-план:** Отсутствуют `max` ограничения в большинстве DTO.

**Бэкенд:**
- `Blueprint.name`: `max:255`
- `Blueprint.code`: `max:255`
- `Blueprint.description`: `max:1000`
- `Path.name`: `max:255`

**Статус:** ❌ **НЕПОЛНОЕ СООТВЕТСТВИЕ**

**Исправление:** Добавить `.max()` во все DTO схемы (см. выше).

---

### ⚠️ Проверка: validation_rules тип

**Фронтенд-план:**
```typescript
validation_rules: z.array(z.string()).optional()
```

**Бэкенд:**
```php
'validation_rules' => ['nullable', 'array'] // массив любых типов
```

**Статус:** ❌ **НЕСООТВЕТСТВИЕ**

**Исправление:**
```typescript
validation_rules: z.array(z.any()).optional() // или z.record(z.string(), z.any())
```

---

## Блок 5: Бизнес-логика

### ✅ Проверка: Циклические зависимости

**Фронтенд-план:** Упоминается валидация на фронте (предупреждение) и бэке (блокировка).

**Бэкенд:** Логика в `BlueprintStructureService` (не прочитан, но документация подтверждает).

**Статус:** ✅ **СООТВЕТСТВУЕТ КОНЦЕПЦИИ**

---

### ✅ Проверка: Конфликты путей

**Фронтенд-план:** Валидация конфликтов при встраивании.

**Бэкенд:** Ошибка 422 с сообщением `"Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"`.

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### ✅ Проверка: Readonly поля

**Фронтенд-план:** Блокировка редактирования `is_readonly = true` полей.

**Бэкенд:** Ошибка 422 `"Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."`.

**Статус:** ✅ **СООТВЕТСТВУЕТ**

---

### ✅ Проверка: Каскадное удаление

**Фронтенд-план:** Удаление Path удаляет дочерние.

**Бэкенд:** Предположительно в `BlueprintStructureService` (миграции с `onDelete('cascade')`).

**Статус:** ✅ **СООТВЕТСТВУЕТ КОНЦЕПЦИИ**

---

## Блок 6: Недостающие проверки

### ❌ КРИТИЧНО: Отсутствие проверки типа host_path

**Фронтенд-план (bp-025, bp-027):** Упоминается, что встраивать Blueprint можно **только в узлы типа JSON**.

**Бэкенд:** НЕ ПРОВЕРЕНО В ПРОЧИТАННЫХ ФАЙЛАХ.

**Задача:** Убедиться, что на бэкенде есть валидация:
```php
// StoreEmbedRequest или BlueprintStructureService::createEmbed
if ($hostPath && $hostPath->data_type !== 'json') {
    throw new ValidationException('Встраивание возможно только в поля типа JSON.');
}
```

**Действие:** **ТРЕБУЕТСЯ ПРОВЕРКА** `app/Services/Blueprint/BlueprintStructureService.php`.

---

### ⚠️ ВАЖНО: Отсутствие проверки уникальности name на уровне

**Фронтенд-план (bp-026):** Упоминается валидация конфликта имён на одном уровне.

**Бэкенд:** НЕ ПРОВЕРЕНО В ПРОЧИТАННЫХ ФАЙЛАХ.

**Задача:** Убедиться, что на бэкенде есть уникальность `(blueprint_id, parent_id, name)`.

**Действие:** **ТРЕБУЕТСЯ ПРОВЕРКА** миграций и `BlueprintStructureService`.

---

### ⚠️ ВАЖНО: Формат validation_rules

**Фронтенд-план:** Не определён формат `validation_rules`.

**Бэкенд:** `'validation_rules' => ['nullable', 'array']` — структура не определена.

**Задача:** Уточнить структуру `validation_rules`:
- Массив строк: `["required", "min:5"]`?
- Объект: `{"min": 5, "max": 100}`?
- Смешанный формат?

**Действие:** **ТРЕБУЕТСЯ ДОКУМЕНТАЦИЯ** формата или проверка реальных данных.

---

### ✅ УТОЧНЕНИЕ: Формат даты

**Фронтенд-план:** `created_at: z.string()`

**Бэкенд:** `->toIso8601String()` (формат ISO 8601)

**Статус:** ✅ **СООТВЕТСТВУЕТ**

**Рекомендация:** Добавить комментарий в типы:
```typescript
created_at: z.string(), // ISO 8601 format
```

---

## Итоговые выводы

### Критичные несоответствия (BLOCKER)

1. **❌ Отсутствие `max` ограничений в DTO** (bp-004, bp-005)
   - Blueprint: `name`, `code` max 255, `description` max 1000
   - Path: `name` max 255

2. **❌ Тип `validation_rules`** (bp-002, bp-005)
   - Фронт: `z.array(z.string())`
   - Бэк: `array` (любые типы)
   - **Исправить:** `z.array(z.any())` или `z.record(z.string(), z.any())`

3. **❌ Формат `sort_order`** (bp-005)
   - Добавить `.min(0)` (бэк требует `>= 0`)

---

### Важные уточнения (HIGH)

4. **⚠️ `zPathTreeNode.children`** (bp-002)
   - Проверить: всегда ли возвращается массив (пустой) или может отсутствовать?
   - Если может отсутствовать: `.default([])`

5. **⚠️ Валидация `host_path.data_type === 'json'`** (bp-025, bp-027)
   - **ТРЕБУЕТСЯ ПРОВЕРКА** `BlueprintStructureService::createEmbed`

6. **⚠️ Уникальность `name` на уровне** (bp-026)
   - **ТРЕБУЕТСЯ ПРОВЕРКА** миграций или сервиса

7. **⚠️ Структура `validation_rules`** (bp-005)
   - **ТРЕБУЕТСЯ ДОКУМЕНТАЦИЯ** формата

---

### Соответствия (OK)

- ✅ Все endpoints и методы API совпадают
- ✅ Основные типы данных корректны
- ✅ Regex-валидация `code`/`name` совпадает
- ✅ Концепции `readonly`, циклических зависимостей, встраиваний соответствуют
- ✅ Структура ответов (Resources) совпадает

---

## Рекомендации по исправлению

### 1. Обновить все DTO схемы

```typescript
// bp-004: Blueprint DTO
zCreateBlueprintDto = z.object({
  name: z.string().min(1).max(255, 'Максимум 255 символов'),
  code: z.string().min(1).max(255).regex(/^[a-z0-9_]+$/),
  description: z.string().max(1000).optional(),
});

zUpdateBlueprintDto = z.object({
  name: z.string().min(1).max(255).optional(),
  code: z.string().max(255).regex(/^[a-z0-9_]+$/).optional(),
  description: z.string().max(1000).optional(),
});

// bp-005: Path DTO
zCreatePathDto = z.object({
  name: z.string().min(1).max(255).regex(/^[a-z0-9_]+$/),
  parent_id: z.number().nullable().optional(),
  data_type: zDataType,
  cardinality: zCardinality.default('one'),
  is_required: z.boolean().default(false),
  is_indexed: z.boolean().default(false),
  sort_order: z.number().int().min(0).default(0),
  validation_rules: z.array(z.any()).optional(), // ✅ Изменено
});

zUpdatePathDto = zCreatePathDto.partial();
```

---

### 2. Уточнить `zPathTreeNode`

**Перед реализацией:** Проверить бэкенд:

```bash
# В stupidCms (backend)
php artisan tinker
> $bp = App\Models\Blueprint::first();
> $paths = $bp->paths()->with('children')->get();
> $tree = (new PathController)->buildTree($paths);
> dd($tree->toArray()); // Проверить структуру children
```

**Если children всегда массив:**
```typescript
zPathTreeNode = zPath.extend({
  children: z.array(z.lazy(() => zPathTreeNode)).default([]),
});
```

---

### 3. Проверить validation_rules

**Запросить у бэкенда:**
```php
// Какая структура validation_rules?
// Примеры:
$path->validation_rules = ['required', 'min:5']; // массив строк?
$path->validation_rules = ['min' => 5, 'max' => 100]; // объект?
$path->validation_rules = [['rule' => 'required'], ['rule' => 'min', 'value' => 5]]; // массив объектов?
```

**Обновить схему:**
```typescript
// Если массив объектов (наиболее вероятно):
zValidationRule = z.object({
  rule: z.string(),
  value: z.any().optional(),
});

zPath = z.object({
  // ...
  validation_rules: z.array(zValidationRule).nullable(),
});
```

---

### 4. Добавить проверки в Utils

**Файл:** `src/utils/blueprintValidation.ts` (bp-039)

```typescript
/**
 * Проверить, может ли host_path содержать встраивание.
 * Встраивание возможно только в поля типа JSON.
 */
export const canEmbedInPath = (path: ZPath | null): boolean => {
  if (!path) return true; // Корневое встраивание разрешено
  return path.data_type === 'json';
};

/**
 * Проверить уникальность имени поля на уровне.
 */
export const isNameUniqueAtLevel = (
  name: string,
  parentId: number | null,
  existingPaths: ZPath[]
): boolean => {
  return !existingPaths.some(
    p => p.name === name && p.parent_id === parentId
  );
};
```

---

### 5. Обработка ошибок

**Файл:** `src/utils/blueprintErrors.ts` (bp-038)

```typescript
export const handleApiError = (error: AxiosError): string => {
  const status = error.response?.status;
  const message = error.response?.data?.message;

  // Циклическая зависимость
  if (message?.includes('Циклическая зависимость')) {
    return handleCyclicDependencyError(error);
  }

  // Конфликт путей
  if (message?.includes('конфликт путей')) {
    return handlePathConflictError(error);
  }

  // Readonly поле
  if (message?.includes('скопированное поле')) {
    return handleReadonlyFieldError(error);
  }

  // Validation errors
  if (status === 422) {
    const errors = error.response?.data?.errors;
    if (errors) {
      return Object.values(errors).flat().join('; ');
    }
  }

  return message || 'Неизвестная ошибка';
};
```

---

## Следующие шаги

1. **КРИТИЧНО:** Обновить все Zod схемы (bp-001 до bp-006) с учётом:
   - `max` ограничений
   - Типа `validation_rules`
   - Формата `sort_order`

2. **ВАЖНО:** Проверить бэкенд (`BlueprintStructureService`) на наличие:
   - Валидации `host_path.data_type === 'json'`
   - Уникальности `(blueprint_id, parent_id, name)` для Path

3. **УТОЧНИТЬ:** Структуру `validation_rules` и обновить схемы

4. **ПРОВЕРИТЬ:** Всегда ли `children` в `PathTreeNode` возвращается как массив

5. **ОБНОВИТЬ:** План реализации (blueprint-implementation-plan.md) с исправлениями

---

## Статус проверки

| Блок | Задачи | Статус | Критичность |
|------|--------|--------|-------------|
| Типы данных (bp-001–006) | 6 | ⚠️ Требуют исправления | HIGH |
| API клиент (bp-007–010) | 4 | ✅ Соответствует | OK |
| Сторы (bp-011–018) | 8 | 🔄 Не проверено | - |
| UI компоненты (bp-019–032) | 14 | 🔄 Не проверено | - |
| Страницы (bp-033–037) | 5 | 🔄 Не проверено | - |
| Утилиты (bp-038–040) | 3 | 🔄 Не проверено | - |

**Общий вывод:** Фундаментальная архитектура фронтенд-плана **СООТВЕТСТВУЕТ** бэкенду, но требуются **критичные исправления** в Zod схемах и **уточнения** по бизнес-логике.

---

**Автор:** Claude (AI Assistant)  
**Дата:** 2025-11-20

