# План реализации Blueprint System

> **Версия:** 1.0  
> **Дата:** 2025-11-20  
> **Задач:** 40

---

## Содержание

1. [Обзор](#обзор)
2. [Ключевые изменения: визуальный редактор графов](#ключевые-изменения-визуальный-редактор-графов)
3. [Блок 1: Типы данных и схемы валидации](#блок-1-типы-данных-и-схемы-валидации)
4. [Блок 2: API клиент](#блок-2-api-клиент)
5. [Блок 3: Сторы MobX](#блок-3-сторы-mobx)
6. [Блок 4: UI компоненты](#блок-4-ui-компоненты)
7. [Блок 5: Страницы](#блок-5-страницы)
8. [Блок 6: Утилиты и роутинг](#блок-6-утилиты-и-роутинг)
9. [Зависимости между задачами](#зависимости-между-задачами)

---

## Обзор

План реализации фронтенда для системы динамических шаблонов данных (Blueprint System) stupidCMS.

**Основные возможности:**

-   Управление Blueprint (CRUD)
-   Древовидная структура полей (Path)
-   Встраивание Blueprint друг в друга
-   Валидация циклических зависимостей
-   Проверка конфликтов путей
-   Визуализация графа зависимостей

**Технологии:**

-   TypeScript + Zod
-   React + MobX
-   Ant Design + Tailwind CSS
-   React Router
-   React Flow (визуальный редактор графов)

---

## Ключевые изменения: визуальный редактор графов

### Концепция

Вместо древовидного списка полей (Tree) используется **визуальный редактор графов** на базе React Flow.

### Основные принципы

**Узлы графа:**

1. **Простые типы данных** (string, int, bool и т.д.) - конечные узлы
2. **JSON группы** (data_type = json) - расширяемые узлы, могут иметь дочерние
3. **Встроенные Blueprint** - readonly узлы, скопированные из других Blueprint

**Правила:**

-   ✅ Добавлять дочерние узлы можно **только к узлам типа JSON**
-   ✅ Встраивать Blueprint можно **только в узлы типа JSON**
-   ❌ Встроенные узлы (readonly) **нельзя редактировать/удалять**
-   ✅ Но в поля встроенных Blueprint **можно записывать итоговые данные**

**Преимущества визуального редактора:**

-   Наглядное отображение структуры схемы
-   Быстрая навигация по сложным структурам
-   Визуальное выделение readonly полей (серый цвет)
-   Drag & Drop для организации layout
-   Контекстное меню для быстрых действий

### Пример использования

**Сценарий:** Создание Blueprint "Company" с встроенным "Address"

```
[Корень]
  ├─ name (string, required, indexed)
  ├─ office (json) ← расширяемый узел
  │   └─ [Address Blueprint] ← встроенный (readonly)
  │       ├─ street (string, readonly)
  │       ├─ city (string, readonly)
  │       └─ zip (string, readonly)
  └─ employees (json, many)
      └─ [Employee Blueprint] ← встроенный
```

**В графе это будет выглядеть:**

```
┌───────────┐
│   name    │ (синий, простое поле)
│  string   │
└───────────┘

┌───────────┐
│  office   │ (зеленый, json - расширяемый)
│   json    │
└─────┬─────┘
      │
      ▼
┌─────────────────┐
│ Address Blueprint│ (серый, readonly)
│   [встроенный]   │
└────────┬─────────┘
         │
    ┌────┴────┬────────┐
    ▼         ▼        ▼
┌────────┐ ┌──────┐ ┌─────┐
│ street │ │ city │ │ zip │ (все серые, readonly)
│ string │ │string│ │string│
└────────┘ └──────┘ └─────┘
```

### Установка зависимостей

```bash
npm install reactflow
npm install @types/dagre dagre
```

**package.json:**

```json
{
    "dependencies": {
        "reactflow": "^11.10.0",
        "dagre": "^0.8.5"
    },
    "devDependencies": {
        "@types/dagre": "^0.7.52"
    }
}
```

### Структура компонентов

```
src/components/paths/
├── PathGraphEditor.tsx       # Основной редактор (React Flow)
├── nodes/
│   ├── SimpleFieldNode.tsx   # Узел простого поля
│   ├── JsonGroupNode.tsx     # Узел JSON группы
│   └── EmbeddedBlueprintNode.tsx # Узел встроенного Blueprint
├── NodeForm.tsx              # Форма создания/редактирования узла
├── NodeFormModal.tsx         # Модальное окно с формой
├── GraphControls.tsx         # Панель управления графом
└── utils/
    ├── graphLayout.ts        # Алгоритм компоновки (dagre)
    ├── pathToGraph.ts        # Преобразование Path → Graph
    └── graphToPath.ts        # Преобразование Graph → Path
```

---

## Блок 1: Типы данных и схемы валидации

### bp-001: Создать Zod схемы для Blueprint

**Файл:** `src/types/blueprint.ts`

**Схемы:**

```typescript
// Основная схема Blueprint
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

// Элемент списка Blueprint
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

### bp-002: Создать Zod схемы для Path

**Файл:** `src/types/path.ts`

**Схемы:**

```typescript
zDataType = z.enum([
    "string",
    "text",
    "int",
    "float",
    "bool",
    "date",
    "datetime",
    "json",
    "ref",
]);

zCardinality = z.enum(["one", "many"]);

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
    validation_rules: z.array(z.any()).nullable(), // JSON массив (любые типы)
    source_blueprint_id: z.number().nullable(),
    blueprint_embed_id: z.number().nullable(),
    source_blueprint: z
        .object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
        .optional(),
    children: z.array(z.lazy(() => zPath)).optional(),
    created_at: z.string(),
    updated_at: z.string(),
});

zPathTreeNode = zPath.extend({
    children: z.array(z.lazy(() => zPathTreeNode)).optional(),
});
```

### bp-003: Создать Zod схемы для BlueprintEmbed

**Файл:** `src/types/blueprintEmbed.ts`

**Схемы:**

```typescript
zBlueprintEmbed = z.object({
    id: z.number(),
    blueprint_id: z.number(),
    embedded_blueprint_id: z.number(),
    host_path_id: z.number().nullable(),
    blueprint: z
        .object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
        .optional(),
    embedded_blueprint: z
        .object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
        .optional(),
    host_path: z
        .object({
            id: z.number(),
            name: z.string(),
            full_path: z.string(),
        })
        .nullable()
        .optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
```

### bp-004: Создать Zod схемы для DTO Blueprint

**Файл:** `src/types/blueprint.ts`

**Схемы:**

```typescript
zCreateBlueprintDto = z.object({
    name: z
        .string()
        .min(1, "Название обязательно")
        .max(255, "Максимум 255 символов"),
    code: z
        .string()
        .min(1, "Код обязателен")
        .max(255, "Максимум 255 символов")
        .regex(/^[a-z0-9_]+$/, "Только a-z, 0-9 и _"),
    description: z.string().max(1000, "Максимум 1000 символов").optional(),
});

zUpdateBlueprintDto = z.object({
    name: z.string().min(1).max(255).optional(),
    code: z
        .string()
        .max(255)
        .regex(/^[a-z0-9_]+$/)
        .optional(),
    description: z.string().max(1000).optional(),
});
```

### bp-005: Создать Zod схемы для DTO Path

**Файл:** `src/types/path.ts`

**Схемы:**

```typescript
zCreatePathDto = z.object({
    name: z
        .string()
        .min(1, "Имя поля обязательно")
        .max(255, "Максимум 255 символов")
        .regex(/^[a-z0-9_]+$/, "Только a-z, 0-9 и _"),
    parent_id: z.number().nullable().optional(),
    data_type: zDataType,
    cardinality: zCardinality.default("one"),
    is_required: z.boolean().default(false),
    is_indexed: z.boolean().default(false),
    sort_order: z.number().int().min(0, "Минимум 0").default(0),
    validation_rules: z.array(z.any()).optional(), // JSON массив (любые типы)
});

zUpdatePathDto = zCreatePathDto.partial();
```

### bp-006: Создать Zod схемы для вспомогательных типов

**Файл:** `src/types/blueprint.ts`

**Схемы:**

```typescript
zBlueprintDependencies = z.object({
    depends_on: z.array(
        z.object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
    ),
    depended_by: z.array(
        z.object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
    ),
});

zCanDeleteBlueprint = z.object({
    can_delete: z.boolean(),
    reasons: z.array(z.string()),
});

zEmbeddableBlueprints = z.object({
    data: z.array(
        z.object({
            id: z.number(),
            code: z.string(),
            name: z.string(),
        })
    ),
});

// Вспомогательный тип для обработки ошибок API
zApiError = z.object({
    message: z.string(),
    errors: z.record(z.array(z.string())).optional(), // Laravel validation errors
});

// Типы пагинации
zPaginationLinks = z.object({
    first: z.string().nullable(),
    last: z.string().nullable(),
    prev: z.string().nullable(),
    next: z.string().nullable(),
});

zPaginationMeta = z.object({
    current_page: z.number(),
    from: z.number().nullable(),
    last_page: z.number(),
    path: z.string(),
    per_page: z.number(),
    to: z.number().nullable(),
    total: z.number(),
});

// Generic тип для пагинированных ответов
export const zPaginatedResponse = <T extends z.ZodTypeAny>(dataSchema: T) =>
    z.object({
        data: z.array(dataSchema),
        links: zPaginationLinks,
        meta: zPaginationMeta,
    });
```

---

## Блок 2: API клиент

### bp-007: Создать API клиент для Blueprint CRUD

**Файл:** `src/api/blueprintApi.ts`

**Методы:**

```typescript
/**
 * Получить список Blueprint с пагинацией и фильтрацией.
 */
export const listBlueprints = async (params: {
  search?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}): Promise<z.infer<ReturnType<typeof zPaginatedResponse<typeof zBlueprintListItem>>>> => {...}

/**
 * Получить Blueprint по ID.
 */
export const getBlueprint = async (id: number): Promise<ZBlueprint> => {...}

/**
 * Создать новый Blueprint.
 */
export const createBlueprint = async (dto: ZCreateBlueprintDto): Promise<ZBlueprint> => {...}

/**
 * Обновить Blueprint.
 */
export const updateBlueprint = async (id: number, dto: ZUpdateBlueprintDto): Promise<ZBlueprint> => {...}

/**
 * Удалить Blueprint.
 */
export const deleteBlueprint = async (id: number): Promise<void> => {...}
```

### bp-008: Создать API клиент для Path CRUD

**Файл:** `src/api/pathApi.ts`

**Методы:**

```typescript
/**
 * Получить дерево полей Blueprint.
 */
export const listPaths = async (blueprintId: number): Promise<ZPathTreeNode[]> => {...}

/**
 * Получить Path по ID.
 */
export const getPath = async (id: number): Promise<ZPath> => {...}

/**
 * Создать новое поле.
 */
export const createPath = async (blueprintId: number, dto: ZCreatePathDto): Promise<ZPath> => {...}

/**
 * Обновить поле.
 */
export const updatePath = async (id: number, dto: ZUpdatePathDto): Promise<ZPath> => {...}

/**
 * Удалить поле.
 */
export const deletePath = async (id: number): Promise<void> => {...}
```

### bp-009: Создать API клиент для BlueprintEmbed CRUD

**Файл:** `src/api/blueprintEmbedApi.ts`

**Методы:**

```typescript
/**
 * Получить список встраиваний Blueprint.
 */
export const listEmbeds = async (blueprintId: number): Promise<ZBlueprintEmbed[]> => {...}

/**
 * Получить встраивание по ID.
 */
export const getEmbed = async (id: number): Promise<ZBlueprintEmbed> => {...}

/**
 * Создать встраивание.
 */
export const createEmbed = async (blueprintId: number, dto: {
  embedded_blueprint_id: number;
  host_path_id?: number;
}): Promise<ZBlueprintEmbed> => {...}

/**
 * Удалить встраивание.
 */
export const deleteEmbed = async (id: number): Promise<void> => {...}
```

### bp-010: Создать API клиент для вспомогательных методов

**Файл:** `src/api/blueprintApi.ts`

**Методы:**

```typescript
/**
 * Проверить возможность удаления Blueprint.
 */
export const canDeleteBlueprint = async (id: number): Promise<ZCanDeleteBlueprint> => {...}

/**
 * Получить граф зависимостей Blueprint.
 */
export const getBlueprintDependencies = async (id: number): Promise<ZBlueprintDependencies> => {...}

/**
 * Получить список Blueprint для безопасного встраивания.
 */
export const getEmbeddableBlueprints = async (id: number): Promise<ZEmbeddableBlueprints> => {...}
```

---

## Блок 3: Сторы MobX

### bp-011: Создать BlueprintStore базовую структуру

**Файл:** `src/stores/BlueprintStore.ts`

**Структура:**

```typescript
/**
 * Store для управления Blueprint.
 */
export class BlueprintStore {
    // Состояние
    blueprints: ZBlueprintListItem[] = [];
    currentBlueprint: ZBlueprint | null = null;
    pagination: PaginationMeta | null = null;

    // Флаги загрузки
    pending = false;
    creating = false;
    updating = false;
    deleting = false;

    // Фильтры
    search = "";
    sortBy = "created_at";
    sortDir: "asc" | "desc" = "desc";
    perPage = 15;
    currentPage = 1;

    constructor() {
        makeAutoObservable(this);
    }
}
```

### bp-012: BlueprintStore методы загрузки списка

**Методы:**

```typescript
/**
 * Загрузить список Blueprint с текущими фильтрами.
 */
async loadBlueprints(): Promise<void> {...}

/**
 * Установить поисковый запрос.
 */
setSearch(value: string): void {...}

/**
 * Установить сортировку.
 */
setSort(sortBy: string, sortDir: 'asc' | 'desc'): void {...}

/**
 * Перейти на страницу.
 */
goToPage(page: number): Promise<void> {...}
```

### bp-013: BlueprintStore методы CRUD

**Методы:**

```typescript
/**
 * Загрузить Blueprint по ID.
 */
async loadBlueprint(id: number): Promise<void> {...}

/**
 * Создать новый Blueprint.
 */
async createBlueprint(dto: ZCreateBlueprintDto): Promise<ZBlueprint> {...}

/**
 * Обновить Blueprint.
 */
async updateBlueprint(id: number, dto: ZUpdateBlueprintDto): Promise<void> {...}

/**
 * Удалить Blueprint.
 */
async deleteBlueprint(id: number): Promise<void> {...}
```

### bp-014: BlueprintStore методы для зависимостей

**Методы:**

```typescript
/**
 * Проверить возможность удаления.
 */
async checkCanDelete(id: number): Promise<ZCanDeleteBlueprint> {...}

/**
 * Получить граф зависимостей.
 */
async loadDependencies(id: number): Promise<ZBlueprintDependencies> {...}

/**
 * Получить список для встраивания.
 */
async loadEmbeddable(id: number): Promise<ZEmbeddableBlueprints> {...}
```

### bp-015: Создать PathStore

**Файл:** `src/stores/PathStore.ts`

**Структура:**

```typescript
/**
 * Store для управления полями Blueprint (Path).
 */
export class PathStore {
  paths: ZPathTreeNode[] = []
  currentPath: ZPath | null = null
  pending = false
  blueprintId: number | null = null

  constructor() {
    makeAutoObservable(this)
  }

  /**
   * Загрузить дерево полей Blueprint.
   */
  async loadPaths(blueprintId: number): Promise<void> {...}
}
```

### bp-016: PathStore методы CRUD

**Методы:**

```typescript
/**
 * Создать новое поле.
 */
async createPath(dto: ZCreatePathDto): Promise<ZPath> {...}

/**
 * Обновить поле с перерасчетом full_path дочерних элементов.
 */
async updatePath(id: number, dto: ZUpdatePathDto): Promise<void> {...}

/**
 * Удалить поле (с каскадным удалением дочерних).
 */
async deletePath(id: number): Promise<void> {...}

/**
 * Вычислить full_path для предпросмотра.
 */
computeFullPath(name: string, parentId: number | null): string {...}
```

### bp-017: Создать BlueprintEmbedStore

**Файл:** `src/stores/BlueprintEmbedStore.ts`

**Структура:**

```typescript
/**
 * Store для управления встраиваниями Blueprint.
 */
export class BlueprintEmbedStore {
  embeds: ZBlueprintEmbed[] = []
  embeddableBlueprints: Array<{id: number, code: string, name: string}> = []
  pending = false
  blueprintId: number | null = null

  constructor() {
    makeAutoObservable(this)
  }

  /**
   * Загрузить список встраиваний.
   */
  async loadEmbeds(blueprintId: number): Promise<void> {...}
}
```

### bp-018: BlueprintEmbedStore валидация и CRUD

**Методы:**

```typescript
/**
 * Загрузить список доступных для встраивания Blueprint.
 */
async loadEmbeddable(blueprintId: number): Promise<void> {...}

/**
 * Создать встраивание с валидацией конфликтов.
 */
async createEmbed(dto: {
  embedded_blueprint_id: number;
  host_path_id?: number;
}): Promise<ZBlueprintEmbed> {...}

/**
 * Удалить встраивание.
 */
async deleteEmbed(id: number): Promise<void> {...}
```

---

## Блок 4: UI компоненты

### bp-019: Компонент BlueprintList

**Файл:** `src/components/blueprints/BlueprintList.tsx`

**Описание:** Таблица со списком Blueprint (AntD Table).

**Колонки:**

-   name (с ссылкой на редактирование)
-   code
-   description
-   paths_count
-   embeds_count
-   post_types_count
-   created_at
-   Действия (Редактировать, Удалить)

### bp-020: BlueprintList фильтры и пагинация

**Компоненты:**

-   Input для поиска (debounce)
-   Select для сортировки (по name, code, created_at)
-   Radio для направления сортировки
-   Pagination (AntD)

### bp-021: Компонент BlueprintForm

**Файл:** `src/components/blueprints/BlueprintForm.tsx`

**Описание:** Форма создания/редактирования Blueprint (AntD Form).

**Поля:**

-   name (Input, required)
-   code (Input, required, regex: a-z0-9\_)
-   description (TextArea, optional)

### bp-022: BlueprintForm валидация

**Валидация:**

-   Интеграция с Zod схемами
-   Отображение ошибок валидации из API
-   Проверка уникальности code

### bp-023: Компонент PathGraphEditor (визуальный редактор)

**Файл:** `src/components/paths/PathGraphEditor.tsx`

**Описание:** Визуальный редактор графов для схемы Blueprint (React Flow).

**Функции:**

-   Отображение узлов (Path) в виде графа
-   Узлы могут быть: простой тип данных ИЛИ встроенный Blueprint
-   Связи между узлами (parent → children)
-   Drag&drop для перемещения и организации узлов
-   Добавление дочерних узлов только к узлам типа `json`
-   Блокировка редактирования readonly узлов (встроенные Blueprint)

**Типы узлов:**

1. **SimpleFieldNode** - простое поле (string, int, bool и т.д.)
2. **JsonGroupNode** - группа полей (json), может иметь дочерние узлы
3. **EmbeddedBlueprintNode** - встроенный Blueprint (readonly, но можно писать данные)

### bp-024: PathGraphEditor визуальное оформление узлов

**Визуализация узлов:**

-   **Иконки по типам данных:**
    -   📝 string/text
    -   🔢 int/float
    -   ☑️ bool
    -   📅 date/datetime
    -   🔗 ref
    -   📦 json (расширяемый)
-   **Индикаторы состояния:**
    -   🔒 is_readonly (встроенный blueprint, серый фон)
    -   ⭐ is_required (красная звездочка)
    -   🔍 is_indexed (иконка индекса)
-   **Цветовая кодировка:**
    -   Синий - простые типы (string, int, bool и т.д.)
    -   Зеленый - json (группы, расширяемые)
    -   Серый - readonly (встроенные blueprint)
    -   Фиолетовый - ref (ссылки)
-   **Контекстное меню на узле:**
    -   Добавить дочерний узел (только для json)
    -   Встроить Blueprint (только для json)
    -   Редактировать (если не readonly)
    -   Удалить (если не readonly)
-   **Tooltip:**
    -   full_path
    -   Для readonly: источник (source_blueprint)

### bp-025: Компонент NodeForm (форма узла графа)

**Файл:** `src/components/paths/NodeForm.tsx`

**Описание:** Форма создания/редактирования узла схемы (Path или Embed).

**Режимы:**

1. **Простое поле** (data_type не json):

    - name (Input, required, regex: a-z0-9\_)
    - data_type (Select: string, text, int, float, bool, date, datetime, ref)
    - cardinality (Radio: one/many)
    - is_required (Checkbox)
    - is_indexed (Checkbox + подсказка)
    - validation_rules (TagInput, optional)

2. **JSON группа** (data_type = json):

    - name (Input, required, regex: a-z0-9\_)
    - cardinality (Radio: one/many)
    - Может иметь дочерние узлы

3. **Встраивание Blueprint** (выбор из embeddable):
    - embedded_blueprint_id (Select из embeddable списка)
    - Показать превью структуры встраиваемого Blueprint
    - Предупреждение: узлы будут readonly

**Блокировки:**

-   Нельзя редактировать узлы с `is_readonly = true`
-   При редактировании readonly узла показать: "Этот узел скопирован из {source_blueprint.name}. Измените исходный Blueprint."

### bp-026: NodeForm предпросмотр и валидация

**Компоненты:**

-   **Динамический расчет full_path:**
    -   При вводе name и выборе родительского узла (в графе)
    -   Отображение: `Полный путь: author.contacts.email`
-   **Валидация имени:**
    -   Проверка на конфликт с существующими полями на том же уровне
    -   Подсветка ошибок в реальном времени
-   **Предпросмотр типа:**
    -   Показать иконку и описание выбранного data_type
    -   Для ref: дополнительные настройки (на какую сущность ссылка)

### bp-027: Компонент NodeFormModal и GraphControls

**Файл:** `src/components/paths/NodeFormModal.tsx`

**Описание:** Модальное окно с NodeForm (AntD Modal).

**Функции:**

-   **Режимы создания:**
    -   Создание корневого узла (без родителя)
    -   Создание дочернего узла (с parentId - только для json узлов)
    -   Встраивание Blueprint в узел json
-   **Режим редактирования:**
    -   Редактирование свойств узла (если не readonly)
    -   Показ информации о readonly узлах
-   **Обработка:**
    -   Валидация формы с Zod
    -   Submit с обновлением графа
    -   Автоматическое позиционирование нового узла на графе

**Файл:** `src/components/paths/GraphControls.tsx`

**Описание:** Панель управления графом.

**Элементы:**

-   Кнопка "Добавить корневой узел"
-   Кнопка "Центрировать граф"
-   Кнопка "Авто-компоновка"
-   Zoom controls
-   Кнопка "Встроить Blueprint" (показывает список доступных)
-   Переключатель отображения: Компактный / Подробный

### bp-028: Компонент EmbedList

**Файл:** `src/components/embeds/EmbedList.tsx`

**Описание:** Список встраиваний (AntD List или Table).

**Отображение:**

-   embedded_blueprint (name, code)
-   host_path (full_path или "Корень")
-   created_at
-   Кнопка Удалить
-   Кнопка "Показать в графе" (скроллит к узлу и подсвечивает)

**Интеграция с графом:**

-   При клике на "Показать в графе" → скроллит к соответствующему узлу в PathGraphEditor
-   Подсвечивает все узлы, принадлежащие этому встраиванию (через source_blueprint_id)
-   Анимация: узлы мерцают или меняют цвет на 2 секунды

### bp-029: Компонент EmbedForm

**Файл:** `src/components/embeds/EmbedForm.tsx`

**Описание:** Форма добавления встраивания (wizard или простая форма).

**Шаги:**

1. Выбор Blueprint из embeddable списка
2. Выбор host_path (TreeSelect + опция "В корень")

### bp-030: EmbedForm выбор и предпросмотр

**Компоненты:**

-   Select/Cards для выбора Blueprint
-   Поиск по name/code
-   Превью структуры встраиваемого Blueprint (количество полей)
-   Предупреждение о возможных конфликтах

### bp-031: Компонент DependencyGraph

**Файл:** `src/components/blueprints/DependencyGraph.tsx`

**Описание:** Визуализация графа зависимостей.

**Варианты реализации:**

-   Простой: два списка (depends_on, depended_by)
-   Сложный: диаграмма с использованием библиотеки (react-flow, vis.js)

### bp-032: Компонент BlueprintDeleteConfirm

**Файл:** `src/components/blueprints/BlueprintDeleteConfirm.tsx`

**Описание:** Модальное окно подтверждения удаления (AntD Modal.confirm).

**Функции:**

-   Запрос can-delete перед показом
-   Отображение reasons если нельзя удалить
-   Блокировка кнопки Удалить если can_delete = false

---

## Блок 5: Страницы

### bp-033: Страница BlueprintsListPage

**Файл:** `src/pages/blueprints/BlueprintsListPage.tsx`

**Структура:**

```tsx
<Page title="Blueprint">
    <PageHeader
        title="Список Blueprint"
        extra={
            <Link to="/blueprints/new">
                <Button>Создать</Button>
            </Link>
        }
    />
    <BlueprintList />
</Page>
```

### bp-034: Страница BlueprintEditPage

**Файл:** `src/pages/blueprints/BlueprintEditPage.tsx`

**Структура:**

```tsx
<Page title="Редактирование Blueprint">
    <PageHeader title={blueprint.name} />
    <BlueprintForm /> {/* Основная информация */}
    <Tabs>
        <TabPane tab="Поля" key="paths">
            ...
        </TabPane>
        <TabPane tab="Встраивания" key="embeds">
            ...
        </TabPane>
        <TabPane tab="Зависимости" key="dependencies">
            ...
        </TabPane>
    </Tabs>
</Page>
```

### bp-035: BlueprintEditPage вкладка Схема (граф)

**Компоненты:**

-   **PathGraphEditor** - визуальный редактор графа схемы
-   **GraphControls** - панель управления графом
-   **NodeFormModal** - модальное окно для создания/редактирования узлов
-   **Sidebar** с информацией о выбранном узле:
    -   Свойства узла (name, type, full_path)
    -   Статистика (количество дочерних)
    -   Для readonly: источник и ссылка на source_blueprint

**Функционал:**

-   Клик на узел → показать sidebar с деталями
-   Двойной клик на узел → открыть NodeFormModal для редактирования
-   Контекстное меню (ПКМ) на узле:
    -   Добавить дочерний узел (только для json)
    -   Встроить Blueprint здесь (только для json)
    -   Редактировать
    -   Удалить (с подтверждением)
-   Drag узлов для организации layout
-   Автоматическая компоновка графа (алгоритм dagre/elk)
-   Подсветка связанных узлов при наведении

### bp-036: BlueprintEditPage вкладка Встраивания

**Компоненты:**

-   EmbedList (список текущих встраиваний)
-   Кнопка "Добавить встраивание"
-   EmbedForm (модальное окно)

### bp-037: BlueprintEditPage вкладка Зависимости

**Компоненты:**

-   DependencyGraph (визуализация)
-   Два раздела: "Зависит от" и "Зависимы от текущего"
-   Ссылки на зависимые Blueprint

---

## Блок 6: Утилиты и роутинг

### bp-038: Утилиты для обработки ошибок

**Файл:** `src/utils/blueprintErrors.ts`

**Функции:**

```typescript
/**
 * Обработчик ошибки циклической зависимости.
 */
export const handleCyclicDependencyError = (error: AxiosError): string => {...}

/**
 * Обработчик ошибки конфликта путей.
 */
export const handlePathConflictError = (error: AxiosError): string => {...}

/**
 * Обработчик ошибки редактирования readonly поля.
 */
export const handleReadonlyFieldError = (error: AxiosError): string => {...}
```

### bp-039: Утилиты для валидации

**Файл:** `src/utils/blueprintValidation.ts`

**Функции:**

```typescript
/**
 * Валидация формата code (a-z0-9_).
 * Max длина: 255 символов.
 */
export const validateBlueprintCode = (code: string): boolean => {
    return /^[a-z0-9_]+$/.test(code) && code.length <= 255;
};

/**
 * Валидация формата name поля (a-z0-9_).
 * Max длина: 255 символов.
 */
export const validateFieldName = (name: string): boolean => {
    return /^[a-z0-9_]+$/.test(name) && name.length <= 255;
};

/**
 * Форматирование code (приведение к нижнему регистру).
 */
export const formatBlueprintCode = (code: string): string => {
    return code.toLowerCase().replace(/[^a-z0-9_]/g, "");
};

/**
 * Проверить, может ли host_path содержать встраивание.
 * Встраивание возможно только в поля типа JSON.
 * ✅ ВАЖНО: Эта проверка ДУБЛИРУЕТ валидацию бэкенда для лучшего UX.
 */
export const canEmbedInPath = (path: ZPath | null): boolean => {
    if (!path) return true; // Корневое встраивание разрешено
    return path.data_type === "json";
};

/**
 * Проверить уникальность имени поля на уровне (клиентская валидация).
 * ✅ ВАЖНО: Бэкенд гарантирует уникальность через full_path, но клиент может
 * предупредить пользователя заранее.
 */
export const isNameUniqueAtLevel = (
    name: string,
    parentId: number | null,
    existingPaths: ZPath[]
): boolean => {
    return !existingPaths.some(
        (p) => p.name === name && p.parent_id === parentId
    );
};
```

### bp-040: Настройка роутинга

**Файл:** `src/App.tsx` или `src/routes.tsx`

**Роуты:**

```tsx
<Route path="/blueprints" element={<BlueprintsListPage />} />
<Route path="/blueprints/new" element={<BlueprintCreatePage />} />
<Route path="/blueprints/:id" element={<BlueprintEditPage />} />
```

---

## Зависимости между задачами

### Последовательность реализации:

**Этап 1: Фундамент (параллельно)**

-   bp-001 до bp-006 (типы)

**Этап 2: API слой**

-   bp-007 до bp-010 (зависит от этапа 1)

**Этап 3: Сторы**

-   bp-011 до bp-018 (зависит от этапов 1-2)

**Этап 4: Базовые компоненты (параллельно после этапа 3)**

-   bp-021, bp-022 (BlueprintForm)
-   bp-025, bp-026, bp-027 (PathForm)
-   bp-029, bp-030 (EmbedForm)

**Этап 5: Сложные компоненты**

-   bp-019, bp-020 (BlueprintList)
-   bp-023, bp-024 (PathTreeView)
-   bp-028 (EmbedList)
-   bp-031 (DependencyGraph)
-   bp-032 (DeleteConfirm)

**Этап 6: Страницы**

-   bp-033 (ListPage - зависит от bp-019, bp-020)
-   bp-034 до bp-037 (EditPage - зависит от всех компонентов)

**Этап 7: Финализация**

-   bp-038, bp-039 (утилиты - можно делать параллельно)
-   bp-040 (роутинг - последний)

---

## Чеклист готовности

### Общее

-   [ ] Все Zod схемы созданы и протестированы
    -   [x] ✅ Добавлены max ограничения (name: 255, code: 255, description: 1000)
    -   [x] ✅ Исправлен тип validation_rules (z.any() вместо z.string())
    -   [x] ✅ Добавлено .min(0) для sort_order
-   [ ] API клиент покрывает все endpoints
    -   [x] ✅ Все endpoints соответствуют бэкенду
-   [ ] Сторы реализуют всю бизнес-логику
-   [ ] Компоненты переиспользуемы и документированы
-   [ ] Страницы корректно интегрируют компоненты
-   [ ] Обработка ошибок покрывает все доменные кейсы
    -   [x] ✅ Циклические зависимости (CyclicDependencyException)
    -   [x] ✅ Конфликты путей (PathConflictException)
    -   [x] ✅ Readonly поля (LogicException)
    -   [ ] Validation errors (422 с errors объектом)
-   [ ] Роутинг настроен и работает
-   [ ] UI соответствует UX рекомендациям из API Blueprint

### Визуальный редактор графов

-   [ ] React Flow установлен и настроен
-   [ ] Реализованы 3 типа custom узлов (SimpleFieldNode, JsonGroupNode, EmbeddedBlueprintNode)
-   [ ] Преобразование Path API → React Flow граф работает корректно
-   [ ] Автоматическая компоновка (dagre layout) применяется при загрузке
-   [ ] Контекстное меню на узлах работает (ПКМ)
-   [ ] Двойной клик открывает форму редактирования
-   [ ] Добавление дочерних узлов работает только для json типов
    -   [x] ✅ БЭКЕНД ВАЛИДИРУЕТ: host_path.data_type === 'json'
    -   [ ] Клиентская проверка через canEmbedInPath()
-   [ ] Readonly узлы нельзя редактировать/удалять (блокировка UI)
    -   [x] ✅ БЭКЕНД ВАЛИДИРУЕТ: path.isCopied() выбрасывает исключение
-   [ ] Визуальная индикация типов узлов (цвета, иконки) работает
-   [ ] Tooltip с полной информацией отображается при наведении
-   [ ] Панель управления (GraphControls) работает:
    -   [ ] Добавить корневой узел
    -   [ ] Встроить Blueprint
    -   [ ] Центрировать граф
    -   [ ] Авто-компоновка
    -   [ ] Zoom controls
-   [ ] Sidebar с информацией о выбранном узле отображается
-   [ ] Изменения в графе синхронизируются с MobX store
-   [ ] Граф корректно обновляется при изменении данных (реактивность)
-   [ ] Валидация уникальности имени на уровне (клиентская, через isNameUniqueAtLevel())

---

## Результаты проверки бэкенда

> **Дата проверки:** 2025-11-20  
> **Статус:** ✅ Фронтенд-план **СООТВЕТСТВУЕТ** бэкенд-реализации с минорными исправлениями

### ✅ Подтверждённые валидации на бэкенде

1. **Встраивание только в JSON узлы** (BlueprintStructureService, строка 372-376):

    ```php
    if ($hostPath->data_type !== 'json') {
        throw new \InvalidArgumentException(
            "host_path должен быть группой (data_type = 'json')."
        );
    }
    ```

2. **Циклические зависимости** (CyclicDependencyValidator):

    - Проверяется перед созданием embed
    - Выбрасывает `CyclicDependencyException`

3. **Конфликты путей** (PathConflictValidator):

    - Проверяется при материализации embed
    - Выбрасывает `PathConflictException`

4. **Readonly поля** (BlueprintStructureService, строка 170-177):

    ```php
    if ($path->isCopied()) {
        throw new \LogicException(
            "Невозможно редактировать скопированное поле."
        );
    }
    ```

5. **Уникальность full_path** (Миграция paths, строка 38-50):
    ```sql
    CREATE UNIQUE INDEX uq_paths_full_path_per_blueprint
    ON paths (blueprint_id, full_path(766))
    ```

### 📝 Ключевые находки

-   **validation_rules**: JSON поле (массив/объект любой структуры) ✅ Исправлено в схемах
-   **sort_order**: Должен быть `>= 0` ✅ Добавлено `.min(0)`
-   **max ограничения**: Blueprint/Path имеют лимиты длины ✅ Добавлены все `.max()`
-   **children в PathTreeNode**: ⚠️ **ИСПРАВЛЕНО** - `whenLoaded` может вернуть `undefined`, поэтому изменено на `.optional()` вместо обязательного массива. На фронтенде использовать `path.children ?? []`

### ⚠️ Рекомендации для фронтенда

1. **Validation_rules формат**: Бэкенд принимает любой JSON. Фронтенд может использовать:

    ```typescript
    // Гибкий подход
    validation_rules: z.array(z.any()).optional();

    // Или структурированный (если определён формат)
    validation_rules: z.array(
        z.object({
            rule: z.string(),
            value: z.any().optional(),
        })
    ).optional();
    ```

2. **Клиентская валидация name**: Проверять уникальность имени на уровне для лучшего UX, но помнить, что бэкенд гарантирует уникальность через full_path.

3. **Обработка ошибок**: Все доменные исключения возвращаются с HTTP 422 и понятными сообщениями.

---

## Примечания

### Сложные моменты:

1. **React Flow интеграция:**
    - Преобразование дерева Path в граф узлов и связей (edges)
    - Автоматическая компоновка графа (dagre layout algorithm)
    - Синхронизация изменений графа с MobX store
    - Custom Node Components для разных типов узлов
2. **Визуальное редактирование:**
    - Только узлы типа `json` могут иметь дочерние узлы
    - Встроенные Blueprint узлы (readonly) нельзя редактировать/удалять
    - Но можно писать данные в поля встроенных Blueprint
    - Ограничение на добавление дочерних узлов к non-json типам
    - ✅ **БЭКЕНД ВАЛИДИРУЕТ:** `host_path.data_type === 'json'` (BlueprintStructureService::validateHostPath)
3. **Валидация циклов:**
    - ✅ **РЕАЛИЗОВАНО НА БЭКЕ:** CyclicDependencyValidator (блокирует создание embed)
    - Фронтенд должен предупреждать заранее (опционально)
4. **Предпросмотр встраивания:** показать структуру Blueprint перед встраиванием
5. **Реактивность:** изменения в исходном Blueprint должны отражаться в зависимых
6. **Уникальность путей:**
    - ✅ **БЭКЕНД ГАРАНТИРУЕТ:** уникальность `(blueprint_id, full_path)` через индекс
    - Фронтенд должен валидировать name на уровне для лучшего UX

### React Flow особенности:

**Типы узлов:**

```typescript
// Простое поле
type SimpleFieldNode = {
    id: string; // path.id
    type: "simpleField";
    data: {
        path: ZPath;
        label: string;
        dataType: ZDataType;
        isRequired: boolean;
        isIndexed: boolean;
        isReadonly: boolean;
    };
    position: { x: number; y: number };
};

// JSON группа (расширяемая)
type JsonGroupNode = {
    id: string;
    type: "jsonGroup";
    data: {
        path: ZPath;
        label: string;
        childCount: number;
        isReadonly: boolean;
    };
    position: { x: number; y: number };
};

// Встроенный Blueprint
type EmbeddedBlueprintNode = {
    id: string;
    type: "embeddedBlueprint";
    data: {
        path: ZPath;
        sourceBlueprintName: string;
        sourceBlueprintCode: string;
        childCount: number;
    };
    position: { x: number; y: number };
};
```

**Связи (Edges):**

```typescript
type PathEdge = {
    id: string;
    source: string; // parent path id
    target: string; // child path id
    type: "smoothstep"; // или 'default'
    animated: boolean; // для readonly узлов
};
```

**Layout алгоритм:**

-   Использовать `dagre` для автоматической компоновки
-   Направление: Top-to-Bottom (TB) или Left-to-Right (LR)
-   Расстояние между узлами: 100px по горизонтали, 80px по вертикали
-   Группировка по уровням вложенности

**Интерактивность:**

-   `nodesDraggable={true}` - можно перемещать узлы
-   `nodesConnectable={false}` - нельзя создавать связи мышью (только через форму)
-   `elementsSelectable={true}` - можно выделять узлы
-   Контекстное меню через `onNodeContextMenu`
-   Двойной клик через `onNodeDoubleClick`

**Преобразование данных API ↔ Граф:**

```typescript
// src/components/paths/utils/pathToGraph.ts

/**
 * Преобразует дерево Path из API в узлы и связи для React Flow.
 */
export const pathTreeToGraph = (
    paths: ZPathTreeNode[]
): { nodes: Node[]; edges: Edge[] } => {
    const nodes: Node[] = [];
    const edges: Edge[] = [];

    const traverse = (
        path: ZPathTreeNode,
        level: number,
        parentX: number,
        parentY: number
    ) => {
        // Определить тип узла
        let nodeType = "simpleField";
        if (path.data_type === "json") {
            nodeType = "jsonGroup";
        }
        if (path.is_readonly && path.source_blueprint_id) {
            nodeType = "embeddedBlueprint";
        }

        // Создать узел
        nodes.push({
            id: path.id.toString(),
            type: nodeType,
            data: {
                path,
                label: path.name,
                dataType: path.data_type,
                isRequired: path.is_required,
                isIndexed: path.is_indexed,
                isReadonly: path.is_readonly,
                sourceBlueprintName: path.source_blueprint?.name,
            },
            position: { x: parentX, y: parentY + level * 100 },
        });

        // Создать связь с родителем
        if (path.parent_id) {
            edges.push({
                id: `e${path.parent_id}-${path.id}`,
                source: path.parent_id.toString(),
                target: path.id.toString(),
                type: "smoothstep",
                animated: path.is_readonly,
            });
        }

        // Обработать дочерние узлы
        if (path.children) {
            path.children.forEach((child, index) => {
                traverse(child, level + 1, parentX + index * 200, parentY);
            });
        }
    };

    paths.forEach((path, index) => traverse(path, 0, index * 300, 0));

    return { nodes, edges };
};

/**
 * Применить автоматическую компоновку графа через dagre.
 */
export const applyDagreLayout = (
    nodes: Node[],
    edges: Edge[],
    direction: "TB" | "LR" = "TB"
) => {
    const dagreGraph = new dagre.graphlib.Graph();
    dagreGraph.setDefaultEdgeLabel(() => ({}));
    dagreGraph.setGraph({ rankdir: direction, nodesep: 100, ranksep: 80 });

    nodes.forEach((node) => {
        dagreGraph.setNode(node.id, { width: 200, height: 80 });
    });

    edges.forEach((edge) => {
        dagreGraph.setEdge(edge.source, edge.target);
    });

    dagre.layout(dagreGraph);

    return nodes.map((node) => {
        const position = dagreGraph.node(node.id);
        return {
            ...node,
            position: { x: position.x, y: position.y },
        };
    });
};
```

### Оптимизации:

-   Debounce для поиска (300ms)
-   Virtualization для больших графов (react-flow встроенная)
-   Мемоизация тяжелых вычислений (layout calculation, граф зависимостей)
-   Оптимистичные обновления для UI отзывчивости
-   Кэширование позиций узлов (сохранять в localStorage или на бэке)

---

## Примеры работы с визуальным редактором

### Пример 1: Создание простой схемы "Article"

**Задача:** Создать Blueprint с полями title, content, author.

**Действия в графе:**

1. Открыть редактор схемы Blueprint "Article"
2. Нажать "Добавить корневой узел"
3. В форме NodeForm:
    - name: `title`
    - data_type: `string`
    - is_required: ✓
    - is_indexed: ✓
4. Повторить для `content` (text) и `author` (string)

**Результат в графе:**

```
┌─────────┐  ┌──────────┐  ┌────────┐
│  title  │  │ content  │  │ author │
│ string  │  │   text   │  │ string │
└─────────┘  └──────────┘  └────────┘
```

### Пример 2: Создание вложенной структуры

**Задача:** Добавить поле `author` как json группу с вложенными `name` и `email`.

**Действия:**

1. Создать узел `author` с data_type = `json`
2. ПКМ на узле `author` → "Добавить дочерний узел"
3. Создать узел `name` (string)
4. ПКМ на узле `author` → "Добавить дочерний узел"
5. Создать узел `email` (string)

**Результат:**

```
┌─────────┐
│ author  │ (зеленый, расширяемый)
│  json   │
└────┬────┘
     │
  ┌──┴──┐
  ▼     ▼
┌────┐ ┌───────┐
│name│ │ email │
│str │ │  str  │
└────┘ └───────┘
```

### Пример 3: Встраивание Blueprint "Address"

**Задача:** Встроить готовый Blueprint "Address" в поле `office`.

**Действия:**

1. Создать узел `office` с data_type = `json`
2. ПКМ на узле `office` → "Встроить Blueprint здесь"
3. В модальном окне:
    - Выбрать Blueprint: "Address"
    - Просмотреть превью (street, city, zip)
    - Нажать "Встроить"
4. Узлы из Address появятся как дочерние к `office` (серые, readonly)

**Результат:**

```
┌─────────┐
│ office  │ (зеленый)
│  json   │
└────┬────┘
     │
     ▼
┌──────────────────┐
│ Address Blueprint│ (серый фон)
└──────┬───────────┘
       │
   ┌───┴───┬─────┐
   ▼       ▼     ▼
┌──────┐ ┌────┐ ┌─────┐
│street│ │city│ │ zip │ (все серые, readonly)
│ str  │ │str │ │ str │
└──────┘ └────┘ └─────┘
```

### Пример 4: Попытка изменить readonly узел

**Ситуация:** Пользователь пытается изменить поле `street` (из встроенного Address).

**Действие:** Двойной клик на узле `street`

**Результат:**

-   Открывается модальное окно с сообщением:

    ```
    ⚠️ Редактирование недоступно

    Это поле скопировано из Blueprint "Address".

    Чтобы изменить структуру, перейдите к исходному Blueprint:
    [Открыть Blueprint "Address"]

    Примечание: Вы можете записывать данные в это поле,
    но не можете изменить его тип или настройки.
    ```

-   Кнопки: [Открыть исходный Blueprint] [Закрыть]

### Пример 5: Удаление узла с дочерними элементами

**Ситуация:** Пользователь удаляет узел `author` (json), у которого есть дочерние `name` и `email`.

**Действие:** ПКМ на узле `author` → "Удалить"

**Результат:**

-   Показывается подтверждение:

    ```
    ⚠️ Удалить узел "author"?

    Будут также удалены все дочерние узлы:
    • author.name
    • author.email

    Все данные в этих полях будут потеряны.

    Это действие нельзя отменить.
    ```

-   Кнопки: [Удалить] [Отменить]

### Пример 6: Встраивание в неподходящий узел

**Ситуация:** Пользователь пытается встроить Blueprint в узел типа `string`.

**Действие:** ПКМ на узле `title` (string) → пункт "Встроить Blueprint здесь" **неактивен**

**Tooltip при наведении на неактивный пункт:**

```
Встраивание Blueprint возможно только в узлы типа JSON.
Преобразуйте этот узел в JSON группу или выберите другой узел.
```

---
