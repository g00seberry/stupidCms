# План рефакторинга валидации RouteNode по типу kind

## Цель
Реорганизовать валидацию `StoreRouteNodeRequest` и `UpdateRouteNodeRequest` по аналогии с `StorePathRequest`, где для разных типов данных (`ref`, `media`) используются отдельные билдеры правил валидации. Для `RouteNode` нужно создать аналогичную систему для разных `kind` (`group`, `route`).

## Текущая ситуация
- Все правила валидации находятся в одном методе `rules()` в `StoreRouteNodeRequest`
- Нет разделения правил для `kind=group` и `kind=route`
- Правила `uri` и `methods` применяются ко всем узлам, хотя они нужны только для `kind=route`
- Правила `prefix`, `namespace` применяются ко всем узлам, хотя они специфичны для `kind=group`
- Нет расширяемости для добавления новых типов `kind` в будущем

## Целевая архитектура
Использовать паттерн Builder + Registry, аналогичный `Path/Constraints`:
- **Concerns** - трейты с общими правилами валидации
- **Kinds** - билдеры правил валидации для каждого `kind`
- **Registry** - регистр билдеров для динамического выбора правил

---

## План выполнения (10 задач)

### Задача 1: Создать структуру директорий и интерфейсы
**Цель:** Подготовить структуру для системы билдеров валидации по `kind`.

**Действия:**
- Создать директорию `app/Http/Requests/Admin/RouteNode/Concerns/`
- Создать директорию `app/Http/Requests/Admin/RouteNode/Kinds/`
- Создать интерфейс `RouteNodeKindValidationBuilderInterface` в `Kinds/`
  - Методы: `getSupportedKind()`, `buildRulesForStore()`, `buildRulesForUpdate()`, `buildMessages()`
- Создать абстрактный класс `AbstractRouteNodeKindValidationBuilder` в `Kinds/`
  - Реализовать базовую логику проверки `kind`
  - Методы: `buildProhibitedRules()`, `getBaseRules()`

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Kinds/RouteNodeKindValidationBuilderInterface.php`
- `app/Http/Requests/Admin/RouteNode/Kinds/AbstractRouteNodeKindValidationBuilder.php`

---

### Задача 2: Создать Registry для билдеров kind
**Цель:** Создать систему регистрации и получения билдеров по `kind`.

**Действия:**
- Создать класс `RouteNodeKindValidationBuilderRegistry` в `Kinds/`
  - Методы: `register()`, `getBuilder()`, `hasBuilder()`, `getSupportedKinds()`, `getAllBuilders()`
  - Хранить билдеры в массиве: `kind => RouteNodeKindValidationBuilderInterface`
- Зарегистрировать Registry в `AppServiceProvider` как singleton
- Создать метод `registerRouteNodeKindBuilders()` для регистрации всех билдеров

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Kinds/RouteNodeKindValidationBuilderRegistry.php`
- Обновить `app/Providers/AppServiceProvider.php`

---

### Задача 3: Создать билдер для kind=group
**Цель:** Выделить правила валидации для узлов типа `group` в отдельный билдер.

**Действия:**
- Создать класс `GroupKindValidationBuilder` extends `AbstractRouteNodeKindValidationBuilder`
- Реализовать `getSupportedKind()` → возвращает `RouteNodeKind::GROUP->value`
- Реализовать `buildRulesForSupportedDataType()` для Store:
  - `prefix`: nullable, string, max:255, ReservedPrefixRule
  - `domain`: nullable, string, max:255
  - `namespace`: nullable, string, max:255
  - `middleware`: nullable, array
  - `where`: nullable, array
  - `children`: nullable, array (для будущей поддержки)
  - Запретить: `uri`, `methods`, `name`, `action`, `action_type`, `entry_id`
- Реализовать `buildUpdateRulesForSupportedDataType()` для Update (аналогично, но с `sometimes`)
- Реализовать `buildMessages()` с кастомными сообщениями

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Kinds/GroupKindValidationBuilder.php`

---

### Задача 4: Создать билдер для kind=route
**Цель:** Выделить правила валидации для узлов типа `route` в отдельный билдер.

**Действия:**
- Создать класс `RouteKindValidationBuilder` extends `AbstractRouteNodeKindValidationBuilder`
- Реализовать `getSupportedKind()` → возвращает `RouteNodeKind::ROUTE->value`
- Реализовать `buildRulesForSupportedDataType()` для Store:
  - `uri`: required, string, max:255, ReservedPrefixRule, RouteConflictRule
  - `methods`: required, array, min:1
  - `methods.*`: Rule::in([GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD])
  - `name`: nullable, string, max:255
  - `domain`: nullable, string, max:255
  - `middleware`: nullable, array
  - `where`: nullable, array
  - `defaults`: nullable, array
  - `action_type`: required, Rule::in([controller, entry])
  - `action`: nullable (с условиями), string, max:255, ControllerActionFormatRule
    - prohibitedIf(action_type === 'entry')
  - `entry_id`: nullable (с условиями), integer, exists:entries,id
    - requiredIf(action_type === 'entry')
  - Запретить: `prefix`, `namespace`, `children`
- Реализовать `buildUpdateRulesForSupportedDataType()` для Update (аналогично, но с `sometimes` и `nullable`)
- Реализовать `buildMessages()` с кастомными сообщениями

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Kinds/RouteKindValidationBuilder.php`

---

### Задача 5: Создать трейт с общими правилами валидации
**Цель:** Выделить общие правила валидации, которые применяются ко всем `kind`.

**Действия:**
- Создать трейт `RouteNodeValidationRules` в `Concerns/`
- Метод `getCommonRouteNodeRules()`:
  - `kind`: required, Rule::in([group, route])
  - `parent_id`: nullable, integer, exists:route_nodes,id
  - `sort_order`: nullable, integer, min:0
  - `enabled`: nullable, boolean
  - `readonly`: prohibited
- Метод `getRouteNodeValidationMessages()`:
  - Кастомные сообщения для общих полей

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Concerns/RouteNodeValidationRules.php`

---

### Задача 6: Создать трейт для работы с Registry kind
**Цель:** Создать трейт для получения правил валидации по `kind` через Registry.

**Действия:**
- Создать трейт `RouteNodeKindValidationRules` в `Concerns/`
- Метод `getKindRegistry()` → возвращает `RouteNodeKindValidationBuilderRegistry` из app()
- Метод `getKindRulesForStore()`:
  - Получает `kind` из запроса
  - Находит билдер через Registry
  - Если билдер найден → возвращает его правила
  - Если билдер не найден → запрещает все специфичные поля
- Метод `getKindRulesForUpdate()`:
  - Получает текущий `kind` из модели `RouteNode` (если обновление)
  - Аналогично `getKindRulesForStore()`
- Метод `getKindValidationMessages()`:
  - Собирает сообщения от всех зарегистрированных билдеров

**Файлы:**
- `app/Http/Requests/Admin/RouteNode/Concerns/RouteNodeKindValidationRules.php`

---

### Задача 7: Рефакторинг StoreRouteNodeRequest
**Цель:** Переработать `StoreRouteNodeRequest` для использования новой системы билдеров.

**Действия:**
- Добавить use трейтов: `RouteNodeValidationRules`, `RouteNodeKindValidationRules`
- Переработать метод `rules()`:
  ```php
  return array_merge(
      $this->getCommonRouteNodeRules(),
      $this->getKindRulesForStore()
  );
  ```
- Переработать метод `messages()`:
  ```php
  return array_merge(
      $this->getRouteNodeValidationMessages(),
      $this->getKindValidationMessages()
  );
  ```
- Удалить все специфичные правила для `kind` из метода `rules()`
- Обновить PHPDoc комментарии

**Файлы:**
- `app/Http/Requests/Admin/StoreRouteNodeRequest.php`

---

### Задача 8: Рефакторинг UpdateRouteNodeRequest
**Цель:** Переработать `UpdateRouteNodeRequest` для использования новой системы билдеров.

**Действия:**
- Добавить use трейтов: `RouteNodeValidationRules`, `RouteNodeKindValidationRules`
- Переработать метод `rules()`:
  - Получить `RouteNode` из route
  - Использовать `getKindRulesForUpdate($routeNode)`
  - Объединить с общими правилами
- Переработать метод `messages()` аналогично `StoreRouteNodeRequest`
- Удалить все специфичные правила для `kind`
- Обновить PHPDoc комментарии

**Файлы:**
- `app/Http/Requests/Admin/UpdateRouteNodeRequest.php`

---

### Задача 9: Регистрация билдеров в AppServiceProvider
**Цель:** Зарегистрировать все билдеры kind в сервис-провайдере.

**Действия:**
- В `AppServiceProvider::register()` создать singleton для `RouteNodeKindValidationBuilderRegistry`
- Создать метод `registerRouteNodeKindBuilders()`:
  - Получить Registry из app()
  - Зарегистрировать `GroupKindValidationBuilder`
  - Зарегистрировать `RouteKindValidationBuilder`
- Вызвать `registerRouteNodeKindBuilders()` в `register()`

**Файлы:**
- `app/Providers/AppServiceProvider.php`

---

### Задача 10: Обновление тестов и документации
**Цель:** Убедиться, что все тесты проходят и документация актуальна.

**Действия:**
- Обновить существующие тесты валидации:
  - `tests/Feature/Admin/RouteNodeValidationTest.php`
  - `tests/Feature/DynamicRoutes/ValidationTest.php`
- Добавить новые тесты для билдеров:
  - `tests/Unit/Http/Requests/Admin/RouteNode/Kinds/GroupKindValidationBuilderTest.php`
  - `tests/Unit/Http/Requests/Admin/RouteNode/Kinds/RouteKindValidationBuilderTest.php`
  - `tests/Unit/Http/Requests/Admin/RouteNode/Kinds/RouteNodeKindValidationBuilderRegistryTest.php`
- Проверить, что все тесты проходят
- Обновить PHPDoc комментарии во всех изменённых файлах
- Убедиться, что нет ошибок линтера

**Файлы:**
- Все тестовые файлы в `tests/`
- Обновить комментарии в изменённых классах

---

## Преимущества нового подхода

1. **Разделение ответственности:** Каждый билдер отвечает только за свой `kind`
2. **Расширяемость:** Легко добавить новый `kind` - создать новый билдер и зарегистрировать
3. **Читаемость:** Правила валидации для каждого `kind` находятся в отдельном файле
4. **Тестируемость:** Каждый билдер можно тестировать независимо
5. **Консистентность:** Единый подход с `Path/Constraints`
6. **Условная валидация:** Правила применяются только для соответствующего `kind`

## Структура файлов после рефакторинга

```
app/Http/Requests/Admin/
├── StoreRouteNodeRequest.php (использует трейты)
├── UpdateRouteNodeRequest.php (использует трейты)
└── RouteNode/
    ├── Concerns/
    │   ├── RouteNodeValidationRules.php (общие правила)
    │   └── RouteNodeKindValidationRules.php (работа с Registry)
    └── Kinds/
        ├── RouteNodeKindValidationBuilderInterface.php
        ├── AbstractRouteNodeKindValidationBuilder.php
        ├── RouteNodeKindValidationBuilderRegistry.php
        ├── GroupKindValidationBuilder.php
        └── RouteKindValidationBuilder.php
```

## Примечания

- При добавлении нового `kind` в будущем нужно будет:
  1. Создать новый билдер extends `AbstractRouteNodeKindValidationBuilder`
  2. Зарегистрировать его в `AppServiceProvider`
  3. Обновить enum `RouteNodeKind` (если нужно)
- Все существующие правила валидации должны быть сохранены, только реорганизованы
- Обратная совместимость API должна быть сохранена

