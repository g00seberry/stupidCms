# План рефакторинга: Унификация работы с constraints в EntryValidationService и PathMaterializer

## Проблема

В текущей реализации есть несколько мест, где жёстко закодирована логика работы с `ref` constraints:

1. **EntryValidationService** (строки 105-121):
   - Проверка `$path->data_type === 'ref'`
   - Проверка `$path->hasRefConstraints()`
   - Использование `$path->getAllowedPostTypeIds()`
   - Создание `RefPostTypeRule` через `RuleFactory`
   - Жёстко закодированное имя связи `'refConstraints'` в `with('refConstraints')`

2. **PathMaterializer** (строки 102-105, 333-379):
   - Вызов специфичного метода `copyRefConstraints()`
   - Жёстко закодированная логика копирования только `ref` constraints
   - Использование `PathRefConstraint` модели напрямую
   - Жёстко закодированное имя связи `'refConstraints'` в `with('refConstraints')`

При добавлении нового типа constraints (например, `media` с `allowed_mimes`):
- Нужно будет дублировать логику в нескольких местах
- Высокий риск забыть обновить какое-то место
- Нарушение принципа Open/Closed (код не открыт для расширения, закрыт для модификации)

## Решение

Расширить существующую архитектуру билдеров constraints (`PathConstraintsBuilderInterface`) методами для:
1. **Построения правил валидации** - для использования в `EntryValidationService`
2. **Копирования constraints** - для использования в `PathMaterializer`
3. **Получения имени связи** - для универсального eager loading

Использовать регистр билдеров (`PathConstraintsBuilderRegistry`) для динамического получения нужного билдера на основе `data_type`.

## План выполнения

### P.1. Расширение интерфейса PathConstraintsBuilderInterface

**Файл:** `app/Services/Path/Constraints/PathConstraintsBuilderInterface.php`

**Действия:**
1. Добавить метод `buildValidationRule()`:
   ```php
   /**
    * Построить доменное правило валидации для EntryValidationService.
    *
    * Создаёт Rule объект для валидации значений полей Entry на основе constraints Path.
    * Используется в EntryValidationService для автоматической валидации constraints.
    *
    * @param Path $path Path с загруженными constraints
    * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
    * @param string $fieldPath Путь к полю в Entry (например, 'data_json.author')
    * @param string $cardinality Кардинальность поля ('one' или 'many')
    * @return \App\Domain\Blueprint\Validation\Rules\Rule|null Правило валидации или null, если constraints нет
    */
   public function buildValidationRule(
       Path $path,
       \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
       string $fieldPath,
       string $cardinality
   ): ?\App\Domain\Blueprint\Validation\Rules\Rule;
   ```

2. Добавить метод `getRelationName()`:
   ```php
   /**
    * Получить имя Eloquent связи для eager loading constraints.
    *
    * Используется для универсального загрузки constraints через with().
    * Например, для ref это 'refConstraints', для media может быть 'mediaConstraints'.
    *
    * @return string Имя связи (например, 'refConstraints')
    */
   public function getRelationName(): string;
   ```

3. Добавить метод `copyConstraints()`:
   ```php
   /**
    * Скопировать constraints из source Path в target Path.
    *
    * Используется в PathMaterializer для копирования constraints при материализации путей.
    * Выполняет batch insert для оптимизации производительности.
    *
    * @param Path $sourcePath Исходный Path с загруженными constraints
    * @param int $targetPathId ID целевого Path
    * @param int $batchInsertSize Размер batch для вставки
    * @return void
    */
   public function copyConstraints(Path $sourcePath, int $targetPathId, int $batchInsertSize): void;
   ```

**Результат:** Интерфейс расширен методами для валидации и копирования constraints.

---

### P.2. Обновление AbstractPathConstraintsBuilder

**Файл:** `app/Services/Path/Constraints/AbstractPathConstraintsBuilder.php`

**Действия:**
1. Добавить реализацию `buildValidationRule()`:
   - Проверять, поддерживает ли билдер `data_type` Path
   - Если нет - возвращать `null`
   - Если да - вызывать абстрактный метод `buildValidationRuleForSupportedDataType()`

2. Добавить реализацию `copyConstraints()`:
   - Проверять, поддерживает ли билдер `data_type` Path
   - Если нет - ничего не делать
   - Если да - проверять, загружены ли связи через `relationLoaded()`
   - Если связи загружены и не пусты - вызывать абстрактный метод `copyConstraintsForSupportedDataType()`

3. Добавить абстрактные методы:
   ```php
   /**
    * Построить правило валидации для поддерживаемого типа данных.
    *
    * @param Path $path Path с загруженными constraints
    * @param RuleFactory $ruleFactory Фабрика правил
    * @param string $fieldPath Путь к полю
    * @param string $cardinality Кардинальность
    * @return Rule|null Правило валидации или null
    */
   abstract protected function buildValidationRuleForSupportedDataType(
       Path $path,
       \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory,
       string $fieldPath,
       string $cardinality
   ): ?\App\Domain\Blueprint\Validation\Rules\Rule;

   /**
    * Скопировать constraints для поддерживаемого типа данных.
    *
    * @param Path $sourcePath Исходный Path
    * @param int $targetPathId ID целевого Path
    * @param int $batchInsertSize Размер batch
    * @return void
    */
   abstract protected function copyConstraintsForSupportedDataType(
       Path $sourcePath,
       int $targetPathId,
       int $batchInsertSize
   ): void;
   ```

**Результат:** Абстрактный класс предоставляет общую логику проверки типов данных.

---

### P.3. Реализация в RefPathConstraintsBuilder

**Файл:** `app/Services/Path/Constraints/RefPathConstraintsBuilder.php`

**Действия:**
1. Реализовать `getRelationName()`:
   ```php
   public function getRelationName(): string
   {
       return 'refConstraints';
   }
   ```

2. Реализовать `buildValidationRuleForSupportedDataType()`:
   - Проверять наличие constraints через `hasConstraints()`
   - Если constraints нет - возвращать `null`
   - Извлекать `allowed_post_type_ids` через `buildForResource()` или напрямую из связи
   - Создавать `RefPostTypeRule` через `$ruleFactory->createRefPostTypeRule()`
   - Возвращать правило

3. Реализовать `copyConstraintsForSupportedDataType()`:
   - Проверять, что связи загружены и не пусты
   - Собирать массив данных для batch insert:
     ```php
     [
         'path_id' => $targetPathId,
         'allowed_post_type_id' => $constraint->allowed_post_type_id,
         'created_at' => now(),
         'updated_at' => now(),
     ]
     ```
   - Выполнять batch insert через `PathRefConstraint::insert()` с разбиением на chunks

**Результат:** RefPathConstraintsBuilder поддерживает валидацию и копирование constraints.

---

### P.4. Реализация в MediaPathConstraintsBuilder

**Файл:** `app/Services/Path/Constraints/MediaPathConstraintsBuilder.php`

**Действия:**
1. Реализовать `getRelationName()`:
   - Вернуть пустую строку или имя будущей связи (например, `'mediaConstraints'`)
   - Если связи ещё нет - вернуть пустую строку

2. Реализовать `buildValidationRuleForSupportedDataType()`:
   - Вернуть `null` (заглушка для будущей реализации)
   - В будущем: создавать правило валидации MIME типов

3. Реализовать `copyConstraintsForSupportedDataType()`:
   - Ничего не делать (заглушка для будущей реализации)
   - В будущем: копировать media constraints

**Результат:** MediaPathConstraintsBuilder имеет заглушки для будущей реализации.

---

### P.5. Рефакторинг EntryValidationService

**Файл:** `app/Domain/Blueprint/Validation/EntryValidationService.php`

**Действия:**
1. Внедрить `PathConstraintsBuilderRegistry` в конструктор:
   ```php
   public function __construct(
       private readonly PathValidationRulesConverterInterface $converter,
       private readonly FieldPathBuilder $fieldPathBuilder,
       private readonly DataTypeMapper $dataTypeMapper,
       private readonly RuleFactory $ruleFactory,
       private readonly \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry
   ) {}
   ```

2. В методе `buildRulesFor()`:
   - Заменить жёстко закодированный `with('refConstraints')` на универсальную загрузку:
     ```php
     // Получить все поддерживаемые типы данных из регистра
     $supportedDataTypes = $this->constraintsBuilderRegistry->getSupportedDataTypes();
     $relationsToLoad = [];
     
     foreach ($supportedDataTypes as $dataType) {
         $builder = $this->constraintsBuilderRegistry->getBuilder($dataType);
         if ($builder !== null) {
             $relationName = $builder->getRelationName();
             if ($relationName !== '') {
                 $relationsToLoad[] = $relationName;
             }
         }
     }
     
     $paths = $blueprint->paths()
         ->with($relationsToLoad)
         ->select([...])
         ->get();
     ```

   - Заменить блок валидации constraints (строки 105-121):
     ```php
     // Добавляем валидацию constraints через билдеры
     $constraintsBuilder = $this->constraintsBuilderRegistry->getBuilder($path->data_type);
     if ($constraintsBuilder !== null) {
         $validationRule = $constraintsBuilder->buildValidationRule(
             $path,
             $this->ruleFactory,
             $fieldPath,
             $path->cardinality
         );
         
         if ($validationRule !== null) {
             // Для cardinality = 'many' правило применяется к элементам массива
             // Для cardinality = 'one' правило применяется к самому полю
             if ($path->cardinality === 'many') {
                 $refFieldPath = $fieldPath . ValidationConstants::ARRAY_ELEMENT_WILDCARD;
                 $ruleSet->addRule($refFieldPath, $validationRule);
             } else {
                 $ruleSet->addRule($fieldPath, $validationRule);
             }
         }
     }
     ```

**Результат:** EntryValidationService использует регистр билдеров вместо хардкода.

---

### P.6. Рефакторинг PathMaterializer

**Файл:** `app/Services/Blueprint/PathMaterializer.php`

**Действия:**
1. Внедрить `PathConstraintsBuilderRegistry` в конструктор:
   ```php
   public function __construct(
       private readonly int $batchInsertSize = 500,
       private readonly \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry
   ) {}
   ```

2. В методе `getSourcePaths()`:
   - Заменить жёстко закодированный `with('refConstraints')` на универсальную загрузку (аналогично P.5.2)

3. Удалить метод `copyRefConstraints()` (строки 333-379)

4. В методе `copyPaths()` после `bulkUpdateParentIds()`:
   - Заменить вызов `$this->copyRefConstraints($sourcePaths, $idMap)` на:
     ```php
     // Копировать constraints через билдеры
     $this->copyAllConstraints($sourcePaths, $idMap);
     ```

5. Добавить новый метод `copyAllConstraints()`:
   ```php
   /**
    * Скопировать все constraints из source paths в host paths.
    *
    * Использует регистр билдеров для копирования constraints всех поддерживаемых типов.
    *
    * @param Collection<Path> $sourcePaths Исходные paths с загруженными constraints
    * @param array<int, int> $idMap Карта соответствия source_path_id => copy_path_id
    * @return void
    */
   private function copyAllConstraints(Collection $sourcePaths, array $idMap): void
   {
       foreach ($sourcePaths as $sourcePath) {
           if (!isset($idMap[$sourcePath->id])) {
               continue;
           }

           $builder = $this->constraintsBuilderRegistry->getBuilder($sourcePath->data_type);
           if ($builder !== null) {
               $builder->copyConstraints(
                   $sourcePath,
                   $idMap[$sourcePath->id],
                   $this->batchInsertSize
               );
           }
       }
   }
   ```

**Результат:** PathMaterializer использует регистр билдеров вместо хардкода.

---

### P.7. Обновление AppServiceProvider

**Файл:** `app/Providers/AppServiceProvider.php`

**Проверка:**
- Убедиться, что `PathConstraintsBuilderRegistry` зарегистрирован как singleton
- Убедиться, что билдеры (`RefPathConstraintsBuilder`, `MediaPathConstraintsBuilder`) зарегистрированы в регистре

**Результат:** Регистрация проверена, изменения не требуются (если уже сделано ранее).

---

### P.8. Обновление тестов

**Файлы:**
- `tests/Feature/Api/Admin/Blueprints/BlueprintControllerTest.php` (если есть тесты на валидацию Entry)
- `tests/Unit/Services/Blueprint/MaterializationServiceTest.php` (тесты на копирование constraints)

**Действия:**
1. Проверить, что существующие тесты проходят
2. Убедиться, что тесты не зависят от хардкода `'refConstraints'`
3. При необходимости добавить тесты для универсальной логики

**Результат:** Тесты проходят, проверяют универсальную логику.

---

## Ожидаемые результаты

После выполнения всех пунктов:

1. ✅ **Расширяемость**: Добавление нового типа constraints требует только создания нового билдера и регистрации его в `AppServiceProvider`
2. ✅ **Отсутствие дублирования**: Логика валидации и копирования constraints инкапсулирована в билдерах
3. ✅ **Универсальность**: `EntryValidationService` и `PathMaterializer` не знают о конкретных типах constraints
4. ✅ **Тестируемость**: Каждый билдер можно тестировать изолированно
5. ✅ **Соблюдение принципов SOLID**: Open/Closed, Single Responsibility, Dependency Inversion

## Дополнительные замечания

1. **Методы Path**: Методы `hasRefConstraints()` и `getAllowedPostTypeIds()` в модели `Path` можно оставить для обратной совместимости, но они будут использоваться только внутри `RefPathConstraintsBuilder`.

2. **RuleFactory**: Метод `createRefPostTypeRule()` специфичен для ref. В будущем для media можно добавить `createMediaMimeRule()` и т.д. Билдеры будут использовать соответствующие методы фабрики.

3. **Производительность**: Eager loading через `with()` будет загружать все связи для всех поддерживаемых типов, что может быть избыточно если в blueprint нет полей некоторых типов. Но это приемлемо, так как обычно blueprint содержит ограниченное количество разных типов данных.

4. **Миграции**: Если в будущем появятся новые таблицы constraints (например, `path_media_constraints`), их нужно будет создавать через миграции, а билдеры будут работать с соответствующими моделями.

