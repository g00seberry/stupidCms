# План рефакторинга системы валидации Blueprint

Детальный план улучшения архитектуры системы валидации на основе ревью.

## Обзор

План разбит на **4 этапа** с приоритизацией задач. Каждый этап завершается рабочим состоянием системы.

**Принципы:**

-   Обратная совместимость на каждом этапе
-   Постепенная миграция без breaking changes
-   Тестирование на каждом этапе
-   Документирование изменений

---

## Этап 1: Доменная модель правил и разделение слоёв

**Цель:** Отделить доменную логику от Laravel-валидации, создать основу для расширений.

**Проблемы, которые решаются:**

-   П.1: Сильная привязка домена к Laravel-валидации
-   П.2: Статический `PathValidationRulesConverter`
-   П.3: Логика валидации в HTTP-слое

**Длительность:** 2-3 недели

### Задача 1.1: Создать доменную модель правил

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Rules/Rule.php`**

    ```php
    interface Rule
    {
        public function getType(): string;
        public function getParams(): array;
    }
    ```

2. **`app/Domain/Blueprint/Validation/Rules/MinRule.php`**

    ```php
    final class MinRule implements Rule
    {
        public function __construct(
            private readonly mixed $value,
            private readonly string $dataType
        ) {}
    }
    ```

3. **`app/Domain/Blueprint/Validation/Rules/MaxRule.php`**
4. **`app/Domain/Blueprint/Validation/Rules/PatternRule.php`**
5. **`app/Domain/Blueprint/Validation/Rules/RequiredRule.php`**
6. **`app/Domain/Blueprint/Validation/Rules/NullableRule.php`**

7. **`app/Domain/Blueprint/Validation/Rules/RuleSet.php`**

    ```php
    final class RuleSet
    {
        /** @var array<string, list<Rule>> */
        private array $rules = [];

        public function addRule(string $fieldPath, Rule $rule): void;
        public function getRulesForField(string $fieldPath): array;
        public function getAllRules(): array;
    }
    ```

8. **`app/Domain/Blueprint/Validation/Rules/FieldDefinition.php`**
    ```php
    final class FieldDefinition
    {
        public function __construct(
            public readonly string $path,
            public readonly string $dataType,
            public readonly bool $isRequired,
            public readonly string $cardinality,
            public readonly array $validationRules
        ) {}
    }
    ```

**Тесты:**

-   `tests/Unit/Domain/Blueprint/Validation/Rules/RuleSetTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Rules/MinRuleTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Rules/MaxRuleTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Rules/PatternRuleTest.php`

**Критерии готовности:**

-   ✅ Все классы правил созданы
-   ✅ RuleSet корректно управляет правилами
-   ✅ Unit-тесты покрывают все классы
-   ✅ PHPDoc на всех методах

---

### Задача 1.2: Создать интерфейс и реализацию конвертера

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/PathValidationRulesConverterInterface.php`**

    ```php
    interface PathValidationRulesConverterInterface
    {
        /**
         * Преобразовать validation_rules из Path в доменные Rule объекты.
         */
        public function convert(
            ?array $validationRules,
            string $dataType,
            bool $isRequired,
            string $cardinality
        ): array;
    }
    ```

2. **`app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`** (рефакторинг)

    - Убрать `static`
    - Реализовать интерфейс
    - Возвращать `Rule[]` вместо `string[]`
    - Использовать фабрики правил

3. **`app/Domain/Blueprint/Validation/Rules/RuleFactory.php`**
    ```php
    interface RuleFactory
    {
        public function createMinRule(mixed $value, string $dataType): MinRule;
        public function createMaxRule(mixed $value, string $dataType): MaxRule;
        public function createPatternRule(mixed $pattern): PatternRule;
    }
    ```

**Файлы для изменения:**

-   `app/Providers/AppServiceProvider.php` — зарегистрировать интерфейс

**Тесты:**

-   Обновить `tests/Unit/Domain/Blueprint/Validation/PathValidationRulesConverterTest.php`
-   Добавить тесты для RuleFactory

**Критерии готовности:**

-   ✅ Конвертер реализует интерфейс
-   ✅ Возвращает доменные Rule объекты
-   ✅ Все тесты проходят
-   ✅ Зарегистрирован в DI-контейнере

---

### Задача 1.3: Создать доменный сервис валидации

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/EntryValidationServiceInterface.php`**

    ```php
    interface EntryValidationServiceInterface
    {
        /**
         * Построить RuleSet для Blueprint.
         */
        public function buildRulesFor(Blueprint $blueprint): RuleSet;

        /**
         * Валидировать content_json по Blueprint.
         */
        public function validate(Blueprint $blueprint, array $content): ValidationResult;
    }
    ```

2. **`app/Domain/Blueprint/Validation/EntryValidationService.php`**

    ```php
    final class EntryValidationService implements EntryValidationServiceInterface
    {
        public function __construct(
            private readonly PathValidationRulesConverterInterface $converter
        ) {}

        public function buildRulesFor(Blueprint $blueprint): RuleSet
        {
            // Логика из BlueprintContentValidator.buildRules()
            // Но возвращает RuleSet вместо массива строк
        }
    }
    ```

3. **`app/Domain/Blueprint/Validation/ValidationResult.php`**

    ```php
    final class ValidationResult
    {
        /** @var array<string, list<ValidationError>> */
        private array $errors = [];

        public function addError(string $field, ValidationError $error): void;
        public function hasErrors(): bool;
        public function getErrors(): array;
    }
    ```

4. **`app/Domain/Blueprint/Validation/ValidationError.php`**
    ```php
    final class ValidationError
    {
        public function __construct(
            public readonly string $field,
            public readonly string $code, // BLUEPRINT_REQUIRED, BLUEPRINT_MIN_LENGTH и т.д.
            public readonly array $params,
            public readonly ?string $message = null
        ) {}
    }
    ```

**Файлы для изменения:**

-   `app/Domain/Blueprint/Validation/BlueprintContentValidator.php` — использовать `EntryValidationService`

**Тесты:**

-   `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/ValidationResultTest.php`

**Критерии готовности:**

-   ✅ Сервис построен и протестирован
-   ✅ ValidationResult корректно собирает ошибки
-   ✅ Все тесты проходят

---

### Задача 1.4: Создать адаптер Laravel → доменные правила

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapterInterface.php`**

    ```php
    interface LaravelValidationAdapterInterface
    {
        /**
         * Преобразовать RuleSet в массив правил Laravel.
         */
        public function adapt(RuleSet $ruleSet): array;
    }
    ```

2. **`app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php`**
    ```php
    final class LaravelValidationAdapter implements LaravelValidationAdapterInterface
    {
        public function adapt(RuleSet $ruleSet): array
        {
            $laravelRules = [];
            foreach ($ruleSet->getAllRules() as $field => $rules) {
                $laravelRules[$field] = $this->convertRulesToLaravel($rules);
            }
            return $laravelRules;
        }

        private function convertRulesToLaravel(array $rules): array
        {
            // Преобразование Rule[] → string[]
        }
    }
    ```

**Файлы для изменения:**

-   `app/Http/Requests/Admin/StoreEntryRequest.php` — использовать адаптер
-   `app/Http/Requests/Admin/UpdateEntryRequest.php` — использовать адаптер

**Тесты:**

-   `tests/Unit/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapterTest.php`

**Критерии готовности:**

-   ✅ Адаптер корректно преобразует RuleSet в Laravel правила
-   ✅ FormRequest использует адаптер
-   ✅ Все существующие тесты проходят
-   ✅ Обратная совместимость сохранена

---

### Задача 1.5: Рефакторинг FormRequest

**Файлы для изменения:**

1. **`app/Http/Requests/Admin/StoreEntryRequest.php`**

    ```php
    private function addBlueprintValidationRules(Validator $validator): void
    {
        $postType = PostType::query()
            ->with('blueprint')
            ->where('slug', $postTypeSlug)
            ->first();

        if (! $postType || ! $postType->blueprint) {
            return;
        }

        // Используем доменный сервис
        $validationService = app(EntryValidationServiceInterface::class);
        $ruleSet = $validationService->buildRulesFor($postType->blueprint);

        // Адаптируем для Laravel
        $adapter = app(LaravelValidationAdapterInterface::class);
        $laravelRules = $adapter->adapt($ruleSet);

        foreach ($laravelRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }
    }
    ```

2. **`app/Http/Requests/Admin/UpdateEntryRequest.php`** — аналогично

**Критерии готовности:**

-   ✅ FormRequest использует доменный сервис
-   ✅ Логика валидации вынесена из HTTP-слоя
-   ✅ Все тесты проходят
-   ✅ Обратная совместимость сохранена

---

### Итоги Этапа 1

**Результат:**

-   ✅ Доменная модель правил создана
-   ✅ Конвертер стал сервисом с интерфейсом
-   ✅ Валидация вынесена в доменный сервис
-   ✅ HTTP-слой использует адаптер
-   ✅ Обратная совместимость сохранена

**Тестирование:**

```bash
php artisan test --filter="Validation"
```

**Документация:**

-   Обновить `docs/backend-validation-system.md`
-   Добавить описание новой архитектуры

---

## Этап 2: Расширение выразительности правил

**Цель:** Добавить поддержку условных правил, межполейных зависимостей, уникальности.

**Проблемы, которые решаются:**

-   П.4: Ограниченная выразительность правил
-   П.10: Ограничения для массивов

**Длительность:** 2-3 недели

### Задача 2.1: Расширить модель validation_rules

**Файлы для изменения:**

1. **Миграция: расширить `validation_rules` в таблице `paths`**

    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_extend_paths_validation_rules.php
    Schema::table('paths', function (Blueprint $table) {
        // validation_rules уже JSON, расширяем структуру
        // Добавляем поддержку:
        // - type: 'min' | 'max' | 'pattern' | 'required_if' | 'unique' | ...
        // - operator: '>=' | '<=' | '==' | '!=' | ...
        // - params: {...}
    });
    ```

2. **`app/Domain/Blueprint/Validation/Rules/ConditionalRule.php`**

    ```php
    final class ConditionalRule implements Rule
    {
        public function __construct(
            private readonly string $type, // 'required_if', 'prohibited_unless'
            private readonly string $field,
            private readonly mixed $value,
            private readonly ?string $operator = null
        ) {}
    }
    ```

3. **`app/Domain/Blueprint/Validation/Rules/UniqueRule.php`**
4. **`app/Domain/Blueprint/Validation/Rules/ExistsRule.php`**
5. **`app/Domain/Blueprint/Validation/Rules/ArrayMinItemsRule.php`**
6. **`app/Domain/Blueprint/Validation/Rules/ArrayMaxItemsRule.php`**
7. **`app/Domain/Blueprint/Validation/Rules/ArrayUniqueRule.php`**

**Структура validation_rules:**

```json
{
    "rules": [
        {
            "type": "min",
            "value": 1
        },
        {
            "type": "max",
            "value": 500
        },
        {
            "type": "required_if",
            "field": "is_published",
            "value": true
        },
        {
            "type": "array_min_items",
            "value": 1
        },
        {
            "type": "array_max_items",
            "value": 10
        }
    ]
}
```

**Тесты:**

-   Тесты для каждого нового типа правила
-   Интеграционные тесты с реальными данными

**Критерии готовности:**

-   ✅ Все новые типы правил реализованы
-   ✅ Миграция выполнена
-   ✅ Тесты проходят
-   ✅ Обратная совместимость (старый формат поддерживается)

---

### Задача 2.2: Создать систему обработчиков правил

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerInterface.php`**

    ```php
    interface RuleHandlerInterface
    {
        public function supports(string $ruleType): bool;
        public function handle(Rule $rule, string $dataType): array; // Laravel правила
    }
    ```

2. **`app/Domain/Blueprint/Validation/Rules/Handlers/MinRuleHandler.php`**
3. **`app/Domain/Blueprint/Validation/Rules/Handlers/MaxRuleHandler.php`**
4. **`app/Domain/Blueprint/Validation/Rules/Handlers/PatternRuleHandler.php`**
5. **`app/Domain/Blueprint/Validation/Rules/Handlers/ConditionalRuleHandler.php`**
6. **`app/Domain/Blueprint/Validation/Rules/Handlers/ArrayMinItemsRuleHandler.php`**
7. **`app/Domain/Blueprint/Validation/Rules/Handlers/ArrayMaxItemsRuleHandler.php`**

8. **`app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry.php`**
    ```php
    final class RuleHandlerRegistry
    {
        /** @var array<string, RuleHandlerInterface> */
        private array $handlers = [];

        public function register(string $ruleType, RuleHandlerInterface $handler): void;
        public function getHandler(string $ruleType): ?RuleHandlerInterface;
    }
    ```

**Файлы для изменения:**

-   `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php` — использовать registry

**Тесты:**

-   Тесты для каждого handler
-   Тесты для registry

**Критерии готовности:**

-   ✅ Все handlers реализованы
-   ✅ Registry корректно управляет handlers
-   ✅ Адаптер использует registry
-   ✅ Тесты проходят

---

### Задача 2.3: Добавить поддержку межполейных зависимостей

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Dependencies/FieldDependency.php`**

    ```php
    final class FieldDependency
    {
        public function __construct(
            public readonly string $sourceField,
            public readonly string $targetField,
            public readonly string $operator, // '>=', '<=', '==', '!='
            public readonly mixed $value = null
        ) {}
    }
    ```

2. **`app/Domain/Blueprint/Validation/Dependencies/DependencyResolver.php`**
    ```php
    interface DependencyResolverInterface
    {
        /**
         * Разрешить зависимости между полями.
         */
        public function resolve(Blueprint $blueprint): array; // FieldDependency[]
    }
    ```

**Миграция:**

-   Добавить поле `depends_on` в таблицу `paths` (JSON)

**Структура depends_on:**

```json
{
    "field": "end_date",
    "operator": ">=",
    "value": "start_date"
}
```

**Тесты:**

-   Тесты для DependencyResolver
-   Интеграционные тесты с условными правилами

**Критерии готовности:**

-   ✅ Зависимости между полями поддерживаются
-   ✅ Тесты проходят
-   ✅ Документация обновлена

---

### Итоги Этапа 2

**Результат:**

-   ✅ Расширена модель validation_rules
-   ✅ Добавлены новые типы правил
-   ✅ Система handlers для обработки правил
-   ✅ Поддержка межполейных зависимостей
-   ✅ Ограничения для массивов (min_items, max_items, unique)

**Тестирование:**

```bash
php artisan test --filter="Validation|Rule"
```

---

## Этап 4: Улучшение структуры Path и доменные коды ошибок

**Цель:** Улучшить структуру Path и добавить доменные коды ошибок.

**Проблемы, которые решаются:**

-   П.7: Модель Path основана на строковом full_path
-   П.8: Валидация жёстко завязана на post_type/entryId
-   П.9: Ошибки валидации не имеют доменной семантики

**Длительность:** 2-3 недели

### Задача 4.1: Улучшить структуру Path (опционально)

**Примечание:** Это большой рефакторинг, который может быть отложен.

**Варианты:**

1. Хранить путь как JSON-массив сегментов
2. Использовать nested set для иерархии
3. Добавить вычисляемое поле `full_path` (computed column)

**Если реализуется:**

-   Миграция для изменения структуры
-   Обновление всех сервисов, работающих с Path
-   Тесты

---

### Задача 4.2: Создать BlueprintContextResolver

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Context/BlueprintContextResolverInterface.php`**

    ```php
    interface BlueprintContextResolverInterface
    {
        public function resolveFromRequest(Request $request): ?Blueprint;
        public function resolveFromEntry(Entry $entry): ?Blueprint;
        public function resolveFromPostType(PostType $postType): ?Blueprint;
    }
    ```

2. **`app/Domain/Blueprint/Validation/Context/BlueprintContextResolver.php`**
    ```php
    final class BlueprintContextResolver implements BlueprintContextResolverInterface
    {
        public function resolveFromRequest(Request $request): ?Blueprint
        {
            if ($request->has('post_type')) {
                $postType = PostType::where('slug', $request->input('post_type'))->first();
                return $postType?->blueprint;
            }

            if ($request->route('id')) {
                $entry = Entry::find($request->route('id'));
                return $entry?->postType?->blueprint;
            }

            return null;
        }
    }
    ```

**Файлы для изменения:**

-   `app/Http/Requests/Admin/StoreEntryRequest.php` — использовать resolver
-   `app/Http/Requests/Admin/UpdateEntryRequest.php` — использовать resolver

**Тесты:**

-   `tests/Unit/Domain/Blueprint/Validation/Context/BlueprintContextResolverTest.php`

**Критерии готовности:**

-   ✅ Resolver работает для всех сценариев
-   ✅ FormRequest использует resolver
-   ✅ Тесты проходят

---

### Задача 4.3: Добавить доменные коды ошибок

**Файлы для создания:**

1. **`app/Domain/Blueprint/Validation/Errors/ValidationErrorCode.php`**

    ```php
    enum ValidationErrorCode: string
    {
        case REQUIRED = 'BLUEPRINT_REQUIRED';
        case MIN_LENGTH = 'BLUEPRINT_MIN_LENGTH';
        case MAX_LENGTH = 'BLUEPRINT_MAX_LENGTH';
        case MIN_VALUE = 'BLUEPRINT_MIN_VALUE';
        case MAX_VALUE = 'BLUEPRINT_MAX_VALUE';
        case PATTERN = 'BLUEPRINT_PATTERN';
        case ARRAY_MIN_ITEMS = 'BLUEPRINT_ARRAY_MIN_ITEMS';
        case ARRAY_MAX_ITEMS = 'BLUEPRINT_ARRAY_MAX_ITEMS';
        case CONDITIONAL_REQUIRED = 'BLUEPRINT_CONDITIONAL_REQUIRED';
        // ...
    }
    ```

2. **`app/Domain/Blueprint/Validation/Errors/StructuredValidationError.php`**
    ```php
    final class StructuredValidationError
    {
        public function __construct(
            public readonly string $field,
            public readonly int $pathId,
            public readonly ValidationErrorCode $code,
            public readonly array $params,
            public readonly string $message
        ) {}
    }
    ```

**Файлы для изменения:**

-   `app/Domain/Blueprint/Validation/ValidationError.php` — использовать ValidationErrorCode
-   `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php` — маппить коды в сообщения

**Файлы для создания:**

3. **`app/Domain/Blueprint/Validation/Errors/ErrorResponseFormatter.php`**

    ```php
    interface ErrorResponseFormatterInterface
    {
        /**
         * Форматировать ошибки для API-ответа.
         */
        public function format(array $errors): array;
    }
    ```

4. **`app/Domain/Blueprint/Validation/Errors/StructuredErrorResponseFormatter.php`**
    ```php
    final class StructuredErrorResponseFormatter implements ErrorResponseFormatterInterface
    {
        public function format(array $errors): array
        {
            return [
                'errors' => array_map(function (StructuredValidationError $error) {
                    return [
                        'field' => $error->field,
                        'path_id' => $error->pathId,
                        'code' => $error->code->value,
                        'params' => $error->params,
                        'message' => $error->message,
                    ];
                }, $errors),
            ];
        }
    }
    ```

**Тесты:**

-   `tests/Unit/Domain/Blueprint/Validation/Errors/StructuredValidationErrorTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Errors/ErrorResponseFormatterTest.php`

**Критерии готовности:**

-   ✅ Все коды ошибок определены
-   ✅ Ошибки содержат структурированные данные
-   ✅ API возвращает коды ошибок
-   ✅ Тесты проходят

---

### Задача 4.4: Обновить API-ответы с доменными кодами

**Файлы для изменения:**

1. **`app/Http/Controllers/Admin/V1/EntryController.php`** (если есть кастомная обработка ошибок)

    - Использовать ErrorResponseFormatter

2. **Middleware для форматирования ошибок валидации** (опционально)
    ```php
    // app/Http/Middleware/FormatValidationErrors.php
    ```

**Формат ответа:**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "content_json.title": [
            {
                "code": "BLUEPRINT_REQUIRED",
                "path_id": 123,
                "params": {},
                "message": "The content json.title field is required."
            },
            {
                "code": "BLUEPRINT_MIN_LENGTH",
                "path_id": 123,
                "params": { "min": 1 },
                "message": "The content json.title field must be at least 1 characters."
            }
        ]
    }
}
```

**Критерии готовности:**

-   ✅ API возвращает структурированные ошибки
-   ✅ Обратная совместимость (старый формат опционально)
-   ✅ Тесты проходят

---

### Итоги Этапа 4

**Результат:**

-   ✅ BlueprintContextResolver создан
-   ✅ Доменные коды ошибок добавлены
-   ✅ API возвращает структурированные ошибки
-   ✅ Валидация не привязана к HTTP-слою

**Тестирование:**

```bash
php artisan test --filter="Validation|Error|Context"
```

---

## Общие рекомендации

### Приоритизация

**Критично (сделать в первую очередь):**

-   Этап 1: Доменная модель правил
-   Этап 3: Версионирование (частично)

**Важно (следующий приоритет):**

-   Этап 2: Расширение выразительности правил
-   Этап 4: Доменные коды ошибок

**Желательно (можно отложить):**

-   Этап 4: Улучшение структуры Path (задача 4.1)

### Тестирование

На каждом этапе:

1. Unit-тесты для новых классов
2. Интеграционные тесты для изменённых сервисов
3. Feature-тесты для API
4. Проверка обратной совместимости

### Документация

После каждого этапа:

1. Обновить `docs/backend-validation-system.md`
2. Обновить `docs/frontend-validation-rules.md` (если нужно)
3. Добавить примеры использования новых возможностей

### Миграция

1. **Постепенная миграция:** Старый код работает параллельно с новым
2. **Feature flags:** Включение новых возможностей через конфиг
3. **Обратная совместимость:** Старый формат validation_rules поддерживается

### Оценка времени

-   **Этап 1:** 2-3 недели
-   **Этап 2:** 2-3 недели
-   **Этап 3:** 1-2 недели
-   **Этап 4:** 2-3 недели

**Итого:** 7-11 недель (1.5-3 месяца)

### Риски

1. **Breaking changes:** Минимизировать через обратную совместимость
2. **Производительность:** Мониторить время выполнения после изменений
3. **Сложность:** Не переусложнять архитектуру

---

## Чек-лист перед началом

-   [ ] Создать ветку для рефакторинга
-   [ ] Обновить тесты для текущего состояния
-   [ ] Создать резервную копию БД
-   [ ] Обсудить план с командой
-   [ ] Подготовить окружение для тестирования

---

## Заключение

План рефакторинга разбит на 4 этапа с чёткими задачами и критериями готовности. Каждый этап завершается рабочим состоянием системы с сохранением обратной совместимости.

**Следующие шаги:**

1. Обсудить план с командой
2. Определить приоритеты
3. Начать с Этапа 1
