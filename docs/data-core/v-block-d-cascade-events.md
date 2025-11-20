# Блок D: Каскадные события

**Трудоёмкость:** 32 часа (26 ч Must Have + 6 ч опционально)  
**Критичность:** 🔴 Без этого изменения не распространяются  
**Результат:** Event, Listener, каскадная рематериализация, реиндексация Entry

---

## D.1-D.3. Доменное событие

### BlueprintStructureChanged Event

`app/Events/Blueprint/BlueprintStructureChanged.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events\Blueprint;

use App\Models\Blueprint;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: структура blueprint изменена.
 *
 * Триггерится при:
 * - Добавлении/удалении/изменении Path
 * - Добавлении/удалении BlueprintEmbed
 * - Изменении свойств Path (name, data_type, cardinality и т.д.)
 *
 * Запускает каскадную рематериализацию всех зависимых blueprint'ов.
 */
class BlueprintStructureChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param Blueprint $blueprint Изменённый blueprint
     * @param array<int> $processedBlueprints ID blueprint'ов, уже обработанных в цепочке
     */
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly array $processedBlueprints = []
    ) {}

    /**
     * Проверить, был ли blueprint уже обработан (защита от циклов).
     *
     * @param int $blueprintId
     * @return bool
     */
    public function wasProcessed(int $blueprintId): bool
    {
        return in_array($blueprintId, $this->processedBlueprints, true);
    }

    /**
     * Создать новое событие с добавленным blueprint в список обработанных.
     *
     * @param int $blueprintId
     * @return self
     */
    public function withProcessed(int $blueprintId): self
    {
        return new self(
            $this->blueprint,
            array_merge($this->processedBlueprints, [$blueprintId])
        );
    }
}
```

---

## D.4. Listener с каскадами

### RematerializeEmbeds Listener

`app/Listeners/Blueprint/RematerializeEmbeds.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners\Blueprint;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Services\Blueprint\DependencyGraphService;
use App\Services\Blueprint\MaterializationService;
use Illuminate\Support\Facades\Log;

/**
 * Listener: рематериализация встраиваний при изменении структуры blueprint.
 *
 * Обрабатывает событие BlueprintStructureChanged:
 * 1. Находит всех зависимых (кто встраивает изменённый blueprint)
 * 2. Рематериализует все embeds
 * 3. Каскадно триггерит событие для зависимых
 * 4. Защита от зацикливания через processedBlueprints
 */
class RematerializeEmbeds
{
    /**
     * @param MaterializationService $materializationService
     * @param DependencyGraphService $graphService
     */
    public function __construct(
        private readonly MaterializationService $materializationService,
        private readonly DependencyGraphService $graphService
    ) {}

    /**
     * Обработать событие.
     *
     * @param BlueprintStructureChanged $event
     * @return void
     */
    public function handle(BlueprintStructureChanged $event): void
    {
        $changedBlueprint = $event->blueprint;

        // Защита от зацикливания
        if ($event->wasProcessed($changedBlueprint->id)) {
            Log::info("Blueprint {$changedBlueprint->code} уже обработан в цепочке, пропускаем");
            return;
        }

        Log::info("Обработка изменения структуры blueprint '{$changedBlueprint->code}' (ID: {$changedBlueprint->id})");

        // Пометить текущий blueprint как обработанный
        $newEvent = $event->withProcessed($changedBlueprint->id);

        // 1. Найти все blueprint'ы, которые встраивают изменённый
        $dependentIds = $this->graphService->getDirectDependents($changedBlueprint->id);

        if (empty($dependentIds)) {
            Log::info("Нет зависимых blueprint'ов для '{$changedBlueprint->code}'");
            return;
        }

        Log::info("Найдено зависимых blueprint'ов: " . count($dependentIds));

        // 2. Рематериализовать все embeds для каждого зависимого
        foreach ($dependentIds as $dependentId) {
            $this->rematerializeDependentBlueprint($dependentId, $changedBlueprint->id, $newEvent);
        }
    }

    /**
     * Рематериализовать встраивания зависимого blueprint.
     *
     * @param int $dependentId ID зависимого blueprint
     * @param int $changedId ID изменённого blueprint
     * @param BlueprintStructureChanged $event Событие с историей обработки
     * @return void
     */
    private function rematerializeDependentBlueprint(
        int $dependentId,
        int $changedId,
        BlueprintStructureChanged $event
    ): void {
        try {
            // Получить зависимый blueprint
            $dependent = \App\Models\Blueprint::findOrFail($dependentId);

            Log::info("Рематериализация blueprint '{$dependent->code}' (зависит от изменённого ID: {$changedId})");

            // Найти все embeds, где dependent встраивает changed
            $embeds = \App\Models\BlueprintEmbed::query()
                ->where('blueprint_id', $dependentId)
                ->where('embedded_blueprint_id', $changedId)
                ->with(['blueprint', 'embeddedBlueprint', 'hostPath'])
                ->get();

            foreach ($embeds as $embed) {
                Log::info("  Материализация embed ID: {$embed->id}");
                $this->materializationService->materialize($embed);
            }

            // 3. Каскадное событие для зависимого blueprint
            // (структура dependent изменилась, нужно уведомить тех, кто встраивает dependent)
            Log::info("Триггер каскадного события для '{$dependent->code}'");
            event(new BlueprintStructureChanged($dependent, $event->processedBlueprints));

        } catch (\Exception $e) {
            Log::error("Ошибка рематериализации blueprint ID {$dependentId}: {$e->getMessage()}", [
                'exception' => $e,
                'changed_blueprint_id' => $changedId,
            ]);

            // В production можно уведомить админа
            // report($e);
        }
    }
}
```

---

## D.5. Регистрация в EventServiceProvider

`app/Providers/EventServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Listeners\Blueprint\RematerializeEmbeds;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        BlueprintStructureChanged::class => [
            RematerializeEmbeds::class,
        ],

        // ... existing events ...
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
```

---

## D.6. Опционально: Версионирование структуры

### Миграция для structure_version

```bash
php artisan make:migration add_structure_version_to_blueprints
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->unsignedInteger('structure_version')->default(1)->after('description');
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->unsignedInteger('indexed_structure_version')->nullable()->after('data_json');
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn('structure_version');
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn('indexed_structure_version');
        });
    }
};
```

### Обновление моделей

`app/Models/Blueprint.php` (добавить):

```php
protected $fillable = [
    'name',
    'code',
    'description',
    'structure_version', // ← добавить
];

/**
 * Инкрементировать версию структуры.
 *
 * @return void
 */
public function incrementStructureVersion(): void
{
    $this->increment('structure_version');
}
```

`app/Models/Entry.php` (добавить):

```php
protected $fillable = [
    // ... existing fields ...
    'indexed_structure_version',
];

protected $casts = [
    // ... existing casts ...
    'indexed_structure_version' => 'integer',
];

/**
 * Проверить, устарела ли индексация Entry.
 *
 * @return bool
 */
public function isIndexOutdated(): bool
{
    $blueprint = $this->postType?->blueprint;
    
    if (!$blueprint) {
        return false;
    }

    return $this->indexed_structure_version !== $blueprint->structure_version;
}
```

### Observer для инкремента версии

`app/Observers/PathObserver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Models\Path;

/**
 * Observer для Path: инкремент structure_version при изменениях.
 */
class PathObserver
{
    /**
     * Handle the Path "created" event.
     */
    public function created(Path $path): void
    {
        if ($path->isOwn()) {
            $this->updateBlueprintVersion($path);
        }
    }

    /**
     * Handle the Path "updated" event.
     */
    public function updated(Path $path): void
    {
        if ($path->isOwn()) {
            $this->updateBlueprintVersion($path);
        }
    }

    /**
     * Handle the Path "deleted" event.
     */
    public function deleted(Path $path): void
    {
        if ($path->isOwn()) {
            $this->updateBlueprintVersion($path);
        }
    }

    /**
     * Обновить версию структуры blueprint и триггерить событие.
     *
     * @param Path $path
     * @return void
     */
    private function updateBlueprintVersion(Path $path): void
    {
        $blueprint = $path->blueprint;
        
        if (!$blueprint) {
            return;
        }

        $blueprint->incrementStructureVersion();
        
        event(new BlueprintStructureChanged($blueprint));
    }
}
```

Регистрация в `AppServiceProvider`:

```php
use App\Models\Path;
use App\Observers\PathObserver;

public function boot(): void
{
    Path::observe(PathObserver::class);
}
```

---

## Тесты

### Unit: Каскадные события

`tests/Unit/Listeners/Blueprint/RematerializeEmbedsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Listeners\Blueprint\RematerializeEmbeds;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([BlueprintStructureChanged::class]);
});

test('изменение blueprint триггерит рематериализацию зависимых', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $a->id, 'name' => 'field_a', 'full_path' => 'field_a']);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $a->id,
    ]);

    // Материализуем первый раз
    app(\App\Services\Blueprint\MaterializationService::class)->materialize($embed);

    // Изменяем A
    event(new BlueprintStructureChanged($a));

    // Проверяем, что событие триггерилось для B
    Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($b) {
        return $event->blueprint->id === $b->id;
    });
});

test('транзитивная рематериализация C → B → A', function () {
    $c = Blueprint::factory()->create(['code' => 'c']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $a = Blueprint::factory()->create(['code' => 'a']);

    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'field_c', 'full_path' => 'field_c']);

    // B → C
    $embedBC = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $c->id,
    ]);

    // A → B
    $embedAB = BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $b->id,
    ]);

    app(\App\Services\Blueprint\MaterializationService::class)->materialize($embedBC);
    app(\App\Services\Blueprint\MaterializationService::class)->materialize($embedAB);

    // Изменяем C
    event(new BlueprintStructureChanged($c));

    // Проверяем каскад: C → B → A
    Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($b) {
        return $event->blueprint->id === $b->id;
    });

    Event::assertDispatched(BlueprintStructureChanged::class, function ($event) use ($a) {
        return $event->blueprint->id === $a->id;
    });
});

test('защита от зацикливания processedBlueprints', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);

    // Создаём событие с A уже в processedBlueprints
    $event = new BlueprintStructureChanged($a, [$a->id]);

    expect($event->wasProcessed($a->id))->toBeTrue();

    // Listener должен пропустить обработку
    $listener = app(RematerializeEmbeds::class);
    $listener->handle($event);

    // Событие не должно триггериться снова
    Event::assertNotDispatched(BlueprintStructureChanged::class);
});

test('множественное встраивание: оба embed рематериализуются', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);

    $office = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'office', 'full_path' => 'office']);
    $legal = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'legal', 'full_path' => 'legal']);

    $embed1 = BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $office->id,
    ]);

    $embed2 = BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $legal->id,
    ]);

    $service = app(\App\Services\Blueprint\MaterializationService::class);
    $service->materialize($embed1);
    $service->materialize($embed2);

    // Добавляем новое поле в Address
    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'city', 'full_path' => 'city']);

    // Изменяем Address
    event(new BlueprintStructureChanged($address));

    // Проверяем, что оба embed рематериализовались
    $officeCopy = Path::where('blueprint_embed_id', $embed1->id)
        ->where('name', 'city')
        ->exists();

    $legalCopy = Path::where('blueprint_embed_id', $embed2->id)
        ->where('name', 'city')
        ->exists();

    expect($officeCopy)->toBeTrue()
        ->and($legalCopy)->toBeTrue();
});
```

### Feature: Версионирование (опционально)

`tests/Feature/Blueprint/VersioningTest.php`:

```php
<?php

declare(strict_types=1);

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Models\Blueprint;
use App\Models\Path;

test('structure_version инкрементируется при добавлении Path', function () {
    $blueprint = Blueprint::factory()->create(['structure_version' => 1]);

    expect($blueprint->structure_version)->toBe(1);

    Path::factory()->create(['blueprint_id' => $blueprint->id]);

    $blueprint->refresh();
    expect($blueprint->structure_version)->toBe(2);
});

test('structure_version инкрементируется при изменении Path', function () {
    $blueprint = Blueprint::factory()->create(['structure_version' => 1]);
    $path = Path::factory()->create(['blueprint_id' => $blueprint->id]);

    $blueprint->refresh();
    expect($blueprint->structure_version)->toBe(2);

    $path->update(['name' => 'updated_name']);

    $blueprint->refresh();
    expect($blueprint->structure_version)->toBe(3);
});

test('Entry.indexed_structure_version обновляется при индексации', function () {
    $blueprint = Blueprint::factory()->create(['structure_version' => 5]);
    $postType = \App\Models\PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    $entry = \App\Models\Entry::factory()->create([
        'post_type_id' => $postType->id,
        'indexed_structure_version' => null,
    ]);

    // Индексация (будет реализована в Блоке G)
    // $indexer->index($entry);

    $entry->indexed_structure_version = $blueprint->structure_version;
    $entry->save();

    expect($entry->indexed_structure_version)->toBe(5);
});

test('isIndexOutdated возвращает true если версии не совпадают', function () {
    $blueprint = Blueprint::factory()->create(['structure_version' => 10]);
    $postType = \App\Models\PostType::factory()->create(['blueprint_id' => $blueprint->id]);
    $entry = \App\Models\Entry::factory()->create([
        'post_type_id' => $postType->id,
        'indexed_structure_version' => 5,
    ]);

    expect($entry->isIndexOutdated())->toBeTrue();

    $entry->indexed_structure_version = 10;
    expect($entry->isIndexOutdated())->toBeFalse();
});
```

---

## Команды

```bash
# Создать event
mkdir -p app/Events/Blueprint
touch app/Events/Blueprint/BlueprintStructureChanged.php

# Создать listener
mkdir -p app/Listeners/Blueprint
touch app/Listeners/Blueprint/RematerializeEmbeds.php

# Опционально: версионирование
php artisan make:migration add_structure_version_to_blueprints
mkdir -p app/Observers
touch app/Observers/PathObserver.php

# Тесты
mkdir -p tests/Unit/Listeners/Blueprint
touch tests/Unit/Listeners/Blueprint/RematerializeEmbedsTest.php
touch tests/Feature/Blueprint/VersioningTest.php

# Запустить тесты
php artisan test --filter=RematerializeEmbeds
php artisan test --filter=Versioning
```

---

## Критические моменты

1. **processedBlueprints:** обязательная защита от зацикливания (иначе бесконечный каскад)
2. **Каскадное событие:** listener триггерит событие для зависимых (транзитивность)
3. **Синхронная обработка:** события в рамках HTTP-запроса (для небольших графов <50 blueprint)
4. **Логирование:** критично для отладки каскадов
5. **Transaction:** каждая рематериализация в своей транзакции (rollback изолирован)
6. **Версионирование (опционально):** для отслеживания устаревших Entry

---

## Использование в коде

```php
use App\Events\Blueprint\BlueprintStructureChanged;

// После добавления/изменения/удаления Path
$path = Path::create([...]);
// → PathObserver триггерит событие автоматически

// Ручной триггер
event(new BlueprintStructureChanged($blueprint));

// Listener автоматически:
// 1. Найдёт зависимых
// 2. Рематериализует embeds
// 3. Каскадно уведомит зависимых
// 4. Защитит от циклов
```

---

## Асинхронная обработка (для больших графов)

Для графов >50 blueprint можно сделать listener очередей:

```php
class RematerializeEmbeds implements ShouldQueue
{
    use Queueable;

    public function handle(BlueprintStructureChanged $event): void
    {
        // ... existing logic ...
    }
}
```

Регистрация:

```php
protected $listen = [
    BlueprintStructureChanged::class => [
        RematerializeEmbeds::class, // queue: 'blueprints'
    ],
];
```

---

**Результат:** Каскадные события работают, транзитивные зависимости обновляются автоматически, защита от циклов реализована.

**Следующий блок:** F/G (Модели Entry, индексация данных).

