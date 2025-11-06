# Правило уникальности slug среди Pages

## Обзор

Правило валидации `UniquePageSlug` обеспечивает уникальность URL-идентификатора (slug) для материалов типа `page` и возвращает **422 Unprocessable Entity** при попытке сохранения дубля.

## Использование

### В FormRequest

```php
use App\Domain\Pages\Validation\UniquePageSlug;

public function rules(): array
{
    return [
        'slug' => [
            'required',
            'string',
            'max:120',
            app(UniquePageSlug::class),
        ],
    ];
}
```

### В контроллере

```php
use App\Domain\Pages\Validation\UniquePageSlug;

$request->validate([
    'slug' => ['required', 'string', 'max:120', app(UniquePageSlug::class)],
]);
```

## Особенности

1. **Нормализация**: автоматически приводит slug к нижнему регистру, удаляет лишние дефисы
2. **Игнорирование текущей записи**: при обновлении позволяет оставить свой slug
3. **Soft-deletes**: по умолчанию не разрешает повторное использование slug удалённых записей
4. **Только для Pages**: проверка уникальности только в рамках `post_type = 'page'`

## Формат ошибки

При нарушении уникальности возвращается:

**HTTP 422**
```json
{
  "message": "Данные не прошли валидацию.",
  "errors": {
    "slug": ["Этот URL уже используется другой страницей. Измените слуг или сохраните с автоправкой."]
  }
}
```

## Защита от гонок

Уникальность гарантируется на уровне БД через:
- Уникальный индекс `['post_type_id', 'slug', 'is_active']` в таблице `entries`
- Триггеры для проверки уникальности при INSERT/UPDATE

## Тестирование

Unit-тесты: `tests/Unit/UniquePageSlugRuleTest.php`
- Проверка уникальности
- Игнорирование текущей записи при обновлении
- Нормализация slug (lowercase, дефисы)

