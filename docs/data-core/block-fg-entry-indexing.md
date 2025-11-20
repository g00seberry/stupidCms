# Блоки F+G: Entry и индексация данных

**Трудоёмкость:** 46 часов (F: 26 ч + G: 20 ч)  
**Критичность:** 🔴 Критично для работы с данными  
**Результат:** Trait HasDocumentData, EntryIndexer, Job реиндексации, Observer

---

## F.4+F.6. Trait HasDocumentData для Entry

`app/Traits/HasDocumentData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Path;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait для запросов к индексированным данным Entry.
 *
 * Предоставляет scopes для фильтрации Entry по индексированным полям.
 */
trait HasDocumentData
{
    /**
     * Фильтровать Entry по значению индексированного поля.
     *
     * @param Builder $query
     * @param string $fullPath Полный путь поля ('author.name', 'tags')
     * @param string $operator Оператор ('=', '>', '<', 'like', etc.)
     * @param mixed $value Значение для сравнения
     * @return Builder
     *
     * @example Entry::wherePath('author.name', '=', 'John')->get()
     * @example Entry::wherePath('price', '>', 100)->get()
     */
    public function scopeWherePath(Builder $query, string $fullPath, string $operator, mixed $value): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath, $operator, $value) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            });

            // Определить колонку value_* по типу значения
            $valueField = $this->detectValueField($value);
            $q->where($valueField, $operator, $value);
        });
    }

    /**
     * Фильтровать по значениям из списка (IN).
     *
     * @param Builder $query
     * @param string $fullPath
     * @param array $values
     * @return Builder
     *
     * @example Entry::wherePathIn('category', ['tech', 'science'])->get()
     */
    public function scopeWherePathIn(Builder $query, string $fullPath, array $values): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath, $values) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));

            if (empty($values)) {
                return;
            }

            $valueField = $this->detectValueField($values[0]);
            $q->whereIn($valueField, $values);
        });
    }

    /**
     * Фильтровать Entry, у которых есть ссылка на указанный Entry.
     *
     * @param Builder $query
     * @param string $fullPath Полный путь ref-поля ('article', 'relatedArticles')
     * @param int $targetEntryId ID целевого Entry
     * @return Builder
     *
     * @example Entry::whereRef('relatedArticles', 42)->get()
     */
    public function scopeWhereRef(Builder $query, string $fullPath, int $targetEntryId): Builder
    {
        return $query->whereHas('docRefs', function ($q) use ($fullPath, $targetEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('target_entry_id', $targetEntryId);
        });
    }

    /**
     * Фильтровать Entry, на которые ссылается указанный Entry (обратный запрос).
     *
     * @param Builder $query
     * @param string $fullPath
     * @param int $ownerEntryId
     * @return Builder
     *
     * @example Entry::referencedBy('relatedArticles', 1)->get()
     */
    public function scopeReferencedBy(Builder $query, string $fullPath, int $ownerEntryId): Builder
    {
        return $query->whereHas('docRefsIncoming', function ($q) use ($fullPath, $ownerEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('entry_id', $ownerEntryId);
        });
    }

    /**
     * Фильтровать Entry с любым значением в указанном поле (NOT NULL).
     *
     * @param Builder $query
     * @param string $fullPath
     * @return Builder
     *
     * @example Entry::wherePathExists('author.bio')->get()
     */
    public function scopeWherePathExists(Builder $query, string $fullPath): Builder
    {
        return $query->whereHas('docValues', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }

    /**
     * Фильтровать Entry, у которых поле НЕ заполнено (NULL).
     *
     * @param Builder $query
     * @param string $fullPath
     * @return Builder
     *
     * @example Entry::wherePathMissing('author.bio')->get()
     */
    public function scopeWherePathMissing(Builder $query, string $fullPath): Builder
    {
        return $query->whereDoesntHave('docValues', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }

    /**
     * Сортировать по индексированному полю.
     *
     * @param Builder $query
     * @param string $fullPath
     * @param string $direction 'asc' | 'desc'
     * @return Builder
     *
     * @example Entry::orderByPath('price', 'desc')->get()
     */
    public function scopeOrderByPath(Builder $query, string $fullPath, string $direction = 'asc'): Builder
    {
        return $query
            ->leftJoin('doc_values as dv_sort', function ($join) use ($fullPath) {
                $join->on('entries.id', '=', 'dv_sort.entry_id')
                    ->whereIn('dv_sort.path_id', function ($subQuery) use ($fullPath) {
                        $subQuery->select('id')
                            ->from('paths')
                            ->where('full_path', $fullPath);
                    });
            })
            ->orderBy('dv_sort.value_string', $direction)
            ->select('entries.*');
    }

    /**
     * Определить колонку value_* по типу значения.
     *
     * @param mixed $value
     * @return string
     */
    private function detectValueField(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'value_int',
            is_float($value) => 'value_float',
            is_bool($value) => 'value_bool',
            $value instanceof \DateTimeInterface => 'value_datetime',
            default => 'value_string',
        };
    }
}
```

### Обновление модели Entry

`app/Models/Entry.php` (добавить):

```php
use App\Traits\HasDocumentData;

class Entry extends Model
{
    use HasFactory, SoftDeletes, HasDocumentData; // ← добавить trait

    // ... existing code ...

    /**
     * Связь с индексированными значениями.
     *
     * @return HasMany<DocValue>
     */
    public function docValues(): HasMany
    {
        return $this->hasMany(DocValue::class);
    }

    /**
     * Связь с индексированными ссылками (исходящими).
     *
     * @return HasMany<DocRef>
     */
    public function docRefs(): HasMany
    {
        return $this->hasMany(DocRef::class);
    }

    /**
     * Связь с входящими ссылками (кто ссылается на этот Entry).
     *
     * @return HasMany<DocRef>
     */
    public function docRefsIncoming(): HasMany
    {
        return $this->hasMany(DocRef::class, 'target_entry_id');
    }

    /**
     * Получить blueprint через PostType.
     *
     * @return Blueprint|null
     */
    public function blueprint(): ?Blueprint
    {
        return $this->postType?->blueprint;
    }
}
```

---

## G.1. Сервис EntryIndexer

`app/Services/Entry/EntryIndexer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Entry;

use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис индексации данных Entry в doc_values и doc_refs.
 *
 * Извлекает значения из data_json по путям blueprint и сохраняет
 * в реляционные таблицы для быстрых запросов.
 */
class EntryIndexer
{
    /**
     * Индексировать Entry.
     *
     * Если Entry без blueprint (legacy) — индексация не выполняется.
     *
     * @param Entry $entry
     * @return void
     */
    public function index(Entry $entry): void
    {
        // Получить blueprint через PostType
        $blueprint = $entry->postType?->blueprint;

        // Если PostType без blueprint — пропустить индексацию
        if (!$blueprint) {
            Log::debug("Entry {$entry->id}: PostType без blueprint, индексация пропущена");
            return;
        }

        DB::transaction(function () use ($entry, $blueprint) {
            // 1. Удалить старые индексы
            DocValue::where('entry_id', $entry->id)->delete();
            DocRef::where('entry_id', $entry->id)->delete();

            // 2. Получить все пути blueprint (включая материализованные)
            $paths = $blueprint->paths()
                ->where('is_indexed', true)
                ->get();

            // 3. Извлечь и сохранить значения
            foreach ($paths as $path) {
                $this->indexPath($entry, $path);
            }

            // 4. Обновить версию структуры (если используется версионирование)
            if ($blueprint->structure_version) {
                $entry->indexed_structure_version = $blueprint->structure_version;
                $entry->saveQuietly(); // без триггера событий
            }
        });

        Log::debug("Entry {$entry->id}: индексация завершена");
    }

    /**
     * Индексировать одно поле.
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @return void
     */
    private function indexPath(Entry $entry, $path): void
    {
        // Извлечь значение из data_json по full_path
        $value = data_get($entry->data_json, $path->full_path);

        if ($value === null) {
            return; // Поле не заполнено
        }

        // Обработать в зависимости от типа
        if ($path->data_type === 'ref') {
            $this->indexRefPath($entry, $path, $value);
        } else {
            $this->indexValuePath($entry, $path, $value);
        }
    }

    /**
     * Индексировать скалярное поле (или массив скаляров).
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value
     * @return void
     */
    private function indexValuePath(Entry $entry, $path, mixed $value): void
    {
        $valueField = $this->getValueFieldForType($path->data_type);

        if ($path->cardinality === 'one') {
            // Одиночное значение
            DocValue::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => 0,
                $valueField => $this->castValue($value, $path->data_type),
            ]);
        } else {
            // Массив значений
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $item) {
                DocValue::create([
                    'entry_id' => $entry->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1, // 1-based индексация
                    $valueField => $this->castValue($item, $path->data_type),
                ]);
            }
        }
    }

    /**
     * Индексировать ref-поле (ссылка на другой Entry).
     *
     * @param Entry $entry
     * @param \App\Models\Path $path
     * @param mixed $value int|array<int>
     * @return void
     */
    private function indexRefPath(Entry $entry, $path, mixed $value): void
    {
        if ($path->cardinality === 'one') {
            // Одиночная ссылка
            if (!is_int($value) && !is_numeric($value)) {
                return;
            }

            DocRef::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => 0,
                'target_entry_id' => (int) $value,
            ]);
        } else {
            // Массив ссылок
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $targetId) {
                if (!is_int($targetId) && !is_numeric($targetId)) {
                    continue;
                }

                DocRef::create([
                    'entry_id' => $entry->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1,
                    'target_entry_id' => (int) $targetId,
                ]);
            }
        }
    }

    /**
     * Получить имя колонки value_* для типа данных.
     *
     * @param string $dataType
     * @return string
     */
    private function getValueFieldForType(string $dataType): string
    {
        return match ($dataType) {
            'string' => 'value_string',
            'int' => 'value_int',
            'float' => 'value_float',
            'bool' => 'value_bool',
            'date' => 'value_date',
            'datetime' => 'value_datetime',
            'text' => 'value_text',
            'json' => 'value_json',
            default => throw new \InvalidArgumentException("Неизвестный data_type: {$dataType}"),
        };
    }

    /**
     * Привести значение к нужному типу.
     *
     * @param mixed $value
     * @param string $dataType
     * @return mixed
     */
    private function castValue(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value,
            'datetime' => $value instanceof \DateTimeInterface
                ? $value
                : now()->parse($value),
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => (string) $value,
        };
    }
}
```

---

## G.2. Job для массовой реиндексации

`app/Jobs/Blueprint/ReindexBlueprintEntries.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Blueprint;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Services\Entry\EntryIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: асинхронная реиндексация всех Entry blueprint'а.
 *
 * Используется при изменении структуры blueprint.
 */
class ReindexBlueprintEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Количество попыток выполнения job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Таймаут выполнения (секунды).
     *
     * @var int
     */
    public $timeout = 600; // 10 минут

    /**
     * @param int $blueprintId ID blueprint для реиндексации
     */
    public function __construct(
        public int $blueprintId
    ) {}

    /**
     * Выполнить job.
     *
     * @param EntryIndexer $indexer
     * @return void
     */
    public function handle(EntryIndexer $indexer): void
    {
        $blueprint = Blueprint::find($this->blueprintId);

        if (!$blueprint) {
            Log::error("Blueprint {$this->blueprintId} не найден при реиндексации");
            return;
        }

        Log::info("Начало реиндексации Entry для blueprint '{$blueprint->code}' (ID: {$blueprint->id})");

        // Найти все PostType, использующие этот blueprint
        $postTypeIds = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->pluck('id');

        if ($postTypeIds->isEmpty()) {
            Log::info("Нет PostType для blueprint '{$blueprint->code}', реиндексация пропущена");
            return;
        }

        // Реиндексировать Entry батчами
        $totalProcessed = 0;

        Entry::query()
            ->whereIn('post_type_id', $postTypeIds)
            ->chunk(100, function ($entries) use ($indexer, &$totalProcessed) {
                foreach ($entries as $entry) {
                    try {
                        $indexer->index($entry);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::error("Ошибка индексации Entry {$entry->id}: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);
                    }
                }
            });

        Log::info("Реиндексация blueprint '{$blueprint->code}' завершена: обработано {$totalProcessed} Entry");
    }

    /**
     * Обработать ошибку выполнения job.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Ошибка реиндексации blueprint {$this->blueprintId}: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);
    }
}
```

---

## G.3. Observer для автоматической индексации

`app/Observers/EntryObserver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Entry;
use App\Services\Entry\EntryIndexer;
use Illuminate\Support\Facades\Log;

/**
 * Observer для Entry: автоматическая индексация при изменениях.
 */
class EntryObserver
{
    /**
     * @param EntryIndexer $indexer
     */
    public function __construct(
        private readonly EntryIndexer $indexer
    ) {}

    /**
     * Handle the Entry "saved" event.
     *
     * @param Entry $entry
     * @return void
     */
    public function saved(Entry $entry): void
    {
        // Индексация только если PostType имеет blueprint
        if ($entry->postType?->blueprint_id) {
            try {
                $this->indexer->index($entry);
            } catch (\Exception $e) {
                Log::error("Ошибка автоиндексации Entry {$entry->id}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Handle the Entry "deleted" event.
     *
     * @param Entry $entry
     * @return void
     */
    public function deleted(Entry $entry): void
    {
        // Очистить индексы (CASCADE в БД, но на всякий случай)
        \App\Models\DocValue::where('entry_id', $entry->id)->delete();
        \App\Models\DocRef::where('entry_id', $entry->id)->delete();
    }
}
```

### Регистрация Observer

`app/Providers/AppServiceProvider.php`:

```php
use App\Models\Entry;
use App\Observers\EntryObserver;

public function boot(): void
{
    Entry::observe(EntryObserver::class);
}
```

---

## Обновление Listener для реиндексации

Обновить `RematerializeEmbeds` (из блока D):

```php
// В методе handle() после рематериализации

// 3. Триггер реиндексации зависимого blueprint
dispatch(new ReindexBlueprintEntries($dependentId));

// 4. Каскадное событие
event(new BlueprintStructureChanged($dependent, $event->processedBlueprints));
```

---

## Регистрация сервисов

`app/Providers/AppServiceProvider.php`:

```php
use App\Services\Entry\EntryIndexer;

public function register(): void
{
    // ... existing bindings ...

    $this->app->singleton(EntryIndexer::class);
}
```

---

## Тесты

### Unit: EntryIndexer

`tests/Unit/Services/Entry/EntryIndexerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Entry\EntryIndexer;

beforeEach(function () {
    $this->indexer = app(EntryIndexer::class);
});

test('индексация Entry с blueprint создаёт doc_values', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Test Article'],
    ]);

    $this->indexer->index($entry);

    $docValue = DocValue::where('entry_id', $entry->id)->first();

    expect($docValue)->not->toBeNull()
        ->and($docValue->value_string)->toBe('Test Article')
        ->and($docValue->array_index)->toBe(0);
});

test('индексация массива создаёт несколько doc_values', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'data_type' => 'string',
        'cardinality' => 'many',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['tags' => ['php', 'laravel', 'cms']],
    ]);

    $this->indexer->index($entry);

    $values = DocValue::where('entry_id', $entry->id)->orderBy('array_index')->get();

    expect($values)->toHaveCount(3)
        ->and($values[0]->value_string)->toBe('php')
        ->and($values[0]->array_index)->toBe(1)
        ->and($values[1]->value_string)->toBe('laravel')
        ->and($values[2]->value_string)->toBe('cms');
});

test('индексация ref-поля создаёт doc_refs', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'relatedArticle',
        'full_path' => 'relatedArticle',
        'data_type' => 'ref',
        'is_indexed' => true,
    ]);

    $targetEntry = Entry::factory()->create();
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['relatedArticle' => $targetEntry->id],
    ]);

    $this->indexer->index($entry);

    $docRef = DocRef::where('entry_id', $entry->id)->first();

    expect($docRef)->not->toBeNull()
        ->and($docRef->target_entry_id)->toBe($targetEntry->id)
        ->and($docRef->array_index)->toBe(0);
});

test('реиндексация удаляет старые значения', function () {
    $blueprint = Blueprint::factory()->create();
    $postType = PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Old Title'],
    ]);

    // Первая индексация
    $this->indexer->index($entry);
    expect(DocValue::where('entry_id', $entry->id)->count())->toBe(1);

    // Обновление
    $entry->data_json = ['title' => 'New Title'];
    $entry->save();

    // Реиндексация
    $this->indexer->index($entry);

    $values = DocValue::where('entry_id', $entry->id)->get();

    expect($values)->toHaveCount(1)
        ->and($values[0]->value_string)->toBe('New Title');
});

test('Entry без blueprint не индексируется', function () {
    $postType = PostType::factory()->create(['blueprint_id' => null]);
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'data_json' => ['title' => 'Legacy Entry'],
    ]);

    $this->indexer->index($entry);

    expect(DocValue::where('entry_id', $entry->id)->count())->toBe(0);
});
```

### Feature: Запросы через wherePath

`tests/Feature/Entry/WherePathTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Entry\EntryIndexer;

beforeEach(function () {
    $this->blueprint = Blueprint::factory()->create();
    $this->postType = PostType::factory()->create(['blueprint_id' => $this->blueprint->id]);

    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'data_type' => 'string',
        'is_indexed' => true,
    ]);

    Path::factory()->create([
        'blueprint_id' => $this->blueprint->id,
        'name' => 'price',
        'full_path' => 'price',
        'data_type' => 'int',
        'is_indexed' => true,
    ]);

    $this->indexer = app(EntryIndexer::class);
});

test('wherePath находит Entry по строке', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Laravel Tutorial'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'PHP Basics'],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePath('title', '=', 'Laravel Tutorial')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});

test('wherePath работает с операторами сравнения', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['price' => 50],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['price' => 150],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePath('price', '>', 100)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry2->id);
});

test('wherePathIn находит Entry по списку значений', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 1'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 2'],
    ]);

    $entry3 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'Article 3'],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);
    $this->indexer->index($entry3);

    $results = Entry::wherePathIn('title', ['Article 1', 'Article 3'])->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->all())->toContain($entry1->id, $entry3->id);
});

test('wherePathExists фильтрует Entry с заполненным полем', function () {
    $entry1 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => ['title' => 'With Title'],
    ]);

    $entry2 = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'data_json' => [],
    ]);

    $this->indexer->index($entry1);
    $this->indexer->index($entry2);

    $results = Entry::wherePathExists('title')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1->id);
});
```

---

## Команды

```bash
# Создать trait
mkdir -p app/Traits
touch app/Traits/HasDocumentData.php

# Создать сервис и job
mkdir -p app/Services/Entry
touch app/Services/Entry/EntryIndexer.php
mkdir -p app/Jobs/Blueprint
touch app/Jobs/Blueprint/ReindexBlueprintEntries.php

# Создать observer
mkdir -p app/Observers
touch app/Observers/EntryObserver.php

# Тесты
mkdir -p tests/Unit/Services/Entry
touch tests/Unit/Services/Entry/EntryIndexerTest.php
mkdir -p tests/Feature/Entry
touch tests/Feature/Entry/WherePathTest.php

# Запустить тесты
php artisan test --filter=EntryIndexer
php artisan test --filter=WherePath
php artisan test --filter=ReindexBlueprint
```

---

## Критические моменты

1. **Индексация только для Entry с blueprint:** проверка `$entry->postType?->blueprint_id`
2. **array_index 1-based:** для массивов индекс начинается с 1 (0 для одиночных значений)
3. **DB::transaction:** индексация атомарна (всё или ничего)
4. **Батчинг:** реиндексация по 100 Entry (избежать переполнения памяти)
5. **Автоиндексация:** Observer триггерит автоматически при saved()
6. **Очистка при удалении:** CASCADE в БД + явная очистка в Observer

---

## Использование в коде

```php
use App\Services\Entry\EntryIndexer;

// Ручная индексация
$indexer = app(EntryIndexer::class);
$indexer->index($entry);

// Массовая реиндексация (асинхронно)
dispatch(new ReindexBlueprintEntries($blueprint->id));

// Запросы
Entry::wherePath('author.name', '=', 'John')->get();
Entry::wherePath('price', '>', 100)->orderByPath('price', 'desc')->get();
Entry::wherePathIn('category', ['tech', 'science'])->get();
Entry::whereRef('relatedArticles', 42)->get();
```

---

**Результат:** Entry индексируется автоматически, запросы через wherePath работают, массовая реиндексация в queue, защита от индексации legacy Entry.

**Следующий блок:** H (BlueprintStructureService — объединение всех сервисов).

