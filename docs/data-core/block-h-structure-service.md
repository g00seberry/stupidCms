# Блок H: BlueprintStructureService

**Трудоёмкость:** 48 часов  
**Критичность:** 🔴 Центральный сервис координации  
**Результат:** Объединяющий сервис CRUD для Blueprint, Path, BlueprintEmbed

---

## H.1. BlueprintStructureService

Центральный сервис, объединяющий все компоненты:

-   Валидацию (CyclicDependencyValidator, PathConflictValidator)
-   Материализацию (MaterializationService)
-   События (BlueprintStructureChanged)

`app/Services/Blueprint/BlueprintStructureService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для работы со структурой Blueprint.
 *
 * Координирует создание/изменение/удаление Blueprint, Path, BlueprintEmbed.
 * Использует валидаторы, материализацию и каскадные события.
 */
class BlueprintStructureService
{
    /**
     * @param CyclicDependencyValidator $cyclicValidator
     * @param PathConflictValidator $conflictValidator
     * @param MaterializationService $materializationService
     */
    public function __construct(
        private readonly CyclicDependencyValidator $cyclicValidator,
        private readonly PathConflictValidator $conflictValidator,
        private readonly MaterializationService $materializationService
    ) {}

    // ============================================
    // CRUD: Blueprint
    // ============================================

    /**
     * Создать новый Blueprint.
     *
     * @param array{name: string, code: string, description?: string} $data
     * @return Blueprint
     */
    public function createBlueprint(array $data): Blueprint
    {
        return Blueprint::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Обновить Blueprint.
     *
     * @param Blueprint $blueprint
     * @param array{name?: string, code?: string, description?: string} $data
     * @return Blueprint
     */
    public function updateBlueprint(Blueprint $blueprint, array $data): Blueprint
    {
        $blueprint->update($data);
        return $blueprint->fresh();
    }

    /**
     * Удалить Blueprint.
     *
     * Проверяет, что blueprint не используется в PostType.
     *
     * @param Blueprint $blueprint
     * @return void
     * @throws \LogicException
     */
    public function deleteBlueprint(Blueprint $blueprint): void
    {
        // Проверить, не используется ли blueprint в PostType
        $usedInPostTypes = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->exists();

        if ($usedInPostTypes) {
            throw new \LogicException(
                "Невозможно удалить blueprint '{$blueprint->code}': " .
                "используется в PostType. Сначала отвяжите PostType от blueprint."
            );
        }

        // Проверить, не встроен ли в другие blueprint
        $embeddedIn = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->exists();

        if ($embeddedIn) {
            throw new \LogicException(
                "Невозможно удалить blueprint '{$blueprint->code}': " .
                "встроен в другие blueprint. Сначала удалите встраивания."
            );
        }

        $blueprint->delete();
    }

    // ============================================
    // CRUD: Path
    // ============================================

    /**
     * Создать собственное поле в Blueprint.
     *
     * @param Blueprint $blueprint
     * @param array{
     *     name: string,
     *     parent_id?: int|null,
     *     data_type: string,
     *     cardinality?: string,
     *     is_required?: bool,
     *     is_indexed?: bool,
     *     sort_order?: int,
     *     validation_rules?: array
     * } $data
     * @return Path
     */
    public function createPath(Blueprint $blueprint, array $data): Path
    {
        return DB::transaction(function () use ($blueprint, $data) {
            // Вычислить full_path
            $parentPath = isset($data['parent_id'])
                ? Path::find($data['parent_id'])
                : null;

            $fullPath = $parentPath
                ? $parentPath->full_path . '.' . $data['name']
                : $data['name'];

            $path = Path::create([
                'blueprint_id' => $blueprint->id,
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'full_path' => $fullPath,
                'data_type' => $data['data_type'],
                'cardinality' => $data['cardinality'] ?? 'one',
                'is_required' => $data['is_required'] ?? false,
                'is_indexed' => $data['is_indexed'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'validation_rules' => $data['validation_rules'] ?? null,
            ]);

            // Событие изменения структуры
            event(new BlueprintStructureChanged($blueprint));

            return $path;
        });
    }

    /**
     * Обновить собственное поле Blueprint.
     *
     * @param Path $path
     * @param array $data
     * @return Path
     * @throws \LogicException
     */
    public function updatePath(Path $path, array $data): Path
    {
        // Валидация: нельзя редактировать скопированные поля
        if ($path->isCopied()) {
            throw new \LogicException(
                "Невозможно редактировать скопированное поле '{$path->full_path}'. " .
                "Измените исходное поле в blueprint '{$path->sourceBlueprint->code}'."
            );
        }

        return DB::transaction(function () use ($path, $data) {
            // Если меняется name или parent_id — пересчитать full_path
            $needsFullPathUpdate = isset($data['name']) || isset($data['parent_id']);

            $path->update($data);

            if ($needsFullPathUpdate) {
                $this->recalculateFullPath($path);
            }

            // Событие изменения структуры
            event(new BlueprintStructureChanged($path->blueprint));

            return $path->fresh();
        });
    }

    /**
     * Удалить собственное поле Blueprint.
     *
     * @param Path $path
     * @return void
     * @throws \LogicException
     */
    public function deletePath(Path $path): void
    {
        // Валидация: нельзя удалять скопированные поля
        if ($path->isCopied()) {
            throw new \LogicException(
                "Невозможно удалить скопированное поле '{$path->full_path}'. " .
                "Удалите встраивание в blueprint '{$path->blueprint->code}'."
            );
        }

        DB::transaction(function () use ($path) {
            $blueprint = $path->blueprint;

            // Удалить (дочерние удалятся CASCADE)
            $path->delete();

            // Событие изменения структуры
            event(new BlueprintStructureChanged($blueprint));
        });
    }

    /**
     * Пересчитать full_path для поля и всех дочерних.
     *
     * @param Path $path
     * @return void
     */
    private function recalculateFullPath(Path $path): void
    {
        $path->refresh();

        $newFullPath = $path->parent
            ? $path->parent->full_path . '.' . $path->name
            : $path->name;

        if ($path->full_path !== $newFullPath) {
            $path->full_path = $newFullPath;
            $path->saveQuietly(); // без триггера событий

            // Рекурсивно обновить дочерние
            foreach ($path->children as $child) {
                $this->recalculateFullPath($child);
            }
        }
    }

    // ============================================
    // CRUD: BlueprintEmbed
    // ============================================

    /**
     * Создать встраивание с полной валидацией и материализацией.
     *
     * @param Blueprint $host Кто встраивает
     * @param Blueprint $embedded Кого встраивают
     * @param Path|null $hostPath Поле-контейнер (NULL = корень)
     * @return BlueprintEmbed
     * @throws CyclicDependencyException
     * @throws PathConflictException
     * @throws \LogicException
     */
    public function createEmbed(
        Blueprint $host,
        Blueprint $embedded,
        ?Path $hostPath = null
    ): BlueprintEmbed {
        return DB::transaction(function () use ($host, $embedded, $hostPath) {
            // 1. Валидация циклов
            $this->cyclicValidator->ensureNoCyclicDependency($host, $embedded);

            // 2. Валидация host_path
            $this->validateHostPath($host, $hostPath);

            // 3. Проверка уникальности (blueprint_id, embedded_blueprint_id, host_path_id)
            $exists = BlueprintEmbed::query()
                ->where('blueprint_id', $host->id)
                ->where('embedded_blueprint_id', $embedded->id)
                ->where('host_path_id', $hostPath?->id)
                ->exists();

            if ($exists) {
                $hostName = $hostPath
                    ? "под полем '{$hostPath->full_path}'"
                    : "в корень";

                throw new \LogicException(
                    "Blueprint '{$embedded->code}' уже встроен в '{$host->code}' {$hostName}."
                );
            }

            // 4. Создание embed
            $embed = BlueprintEmbed::create([
                'blueprint_id' => $host->id,
                'embedded_blueprint_id' => $embedded->id,
                'host_path_id' => $hostPath?->id,
            ]);

            // 5. Материализация (с PRE-CHECK конфликтов внутри)
            $this->materializationService->materialize($embed);

            // 6. Событие для реиндексации
            event(new BlueprintStructureChanged($host));

            Log::info("Создано встраивание: '{$embedded->code}' → '{$host->code}'", [
                'embed_id' => $embed->id,
                'host_path' => $hostPath?->full_path,
            ]);

            return $embed;
        });
    }

    /**
     * Удалить встраивание.
     *
     * Скопированные поля удалятся автоматически (CASCADE).
     *
     * @param BlueprintEmbed $embed
     * @return void
     */
    public function deleteEmbed(BlueprintEmbed $embed): void
    {
        DB::transaction(function () use ($embed) {
            $host = $embed->blueprint;
            $embedded = $embed->embeddedBlueprint;

            // Удалить embed (копии полей удалятся CASCADE)
            $embed->delete();

            // Событие для реиндексации
            event(new BlueprintStructureChanged($host));

            Log::info("Удалено встраивание: '{$embedded->code}' из '{$host->code}'", [
                'embed_id' => $embed->id,
            ]);
        });
    }

    /**
     * Валидировать host_path.
     *
     * @param Blueprint $blueprint
     * @param Path|null $hostPath
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateHostPath(Blueprint $blueprint, ?Path $hostPath): void
    {
        if ($hostPath === null) {
            return; // Встраивание в корень
        }

        // Проверить принадлежность к blueprint
        if ($hostPath->blueprint_id !== $blueprint->id) {
            throw new \InvalidArgumentException(
                "host_path '{$hostPath->full_path}' не принадлежит blueprint '{$blueprint->code}'."
            );
        }

        // Проверить, что host_path — не скопированное поле
        if ($hostPath->isCopied()) {
            throw new \InvalidArgumentException(
                "Нельзя встраивать в скопированное поле '{$hostPath->full_path}'. " .
                "Используйте собственные поля blueprint."
            );
        }

        // Опционально: проверить тип (должна быть группа)
        if ($hostPath->data_type !== 'json') {
            throw new \InvalidArgumentException(
                "host_path '{$hostPath->full_path}' должен быть группой (data_type = 'json')."
            );
        }
    }

    // ============================================
    // Вспомогательные методы
    // ============================================

    /**
     * Получить список blueprint'ов, в которые можно встроить указанный.
     *
     * Исключает сам blueprint и те, которые создадут цикл.
     *
     * @param Blueprint $blueprint
     * @return \Illuminate\Support\Collection<int, Blueprint>
     */
    public function getEmbeddableBlueprintsFor(Blueprint $blueprint): \Illuminate\Support\Collection
    {
        $allBlueprints = Blueprint::all();

        return $allBlueprints->filter(function ($candidate) use ($blueprint) {
            // Нельзя встроить в самого себя
            if ($candidate->id === $blueprint->id) {
                return false;
            }

            // Проверить, не создаст ли цикл
            return $this->cyclicValidator->canEmbed($candidate->id, $blueprint->id);
        });
    }

    /**
     * Получить граф зависимостей blueprint.
     *
     * @param Blueprint $blueprint
     * @return array{
     *     depends_on: array<int>,
     *     depended_by: array<int>
     * }
     */
    public function getDependencyGraph(Blueprint $blueprint): array
    {
        $graphService = app(DependencyGraphService::class);

        return [
            'depends_on' => $graphService->getAllTransitiveDependencies($blueprint->id)->all(),
            'depended_by' => $graphService->getAllDependentBlueprintIds($blueprint->id)->all(),
        ];
    }

    /**
     * Проверить, можно ли удалить Blueprint.
     *
     * @param Blueprint $blueprint
     * @return array{can_delete: bool, reasons: array<string>}
     */
    public function canDeleteBlueprint(Blueprint $blueprint): array
    {
        $reasons = [];

        // Проверить использование в PostType
        $postTypesCount = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->count();

        if ($postTypesCount > 0) {
            $reasons[] = "Используется в {$postTypesCount} PostType";
        }

        // Проверить встраивания
        $embedsCount = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->count();

        if ($embedsCount > 0) {
            $reasons[] = "Встроен в {$embedsCount} других blueprint";
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}
```

---

## Регистрация в AppServiceProvider

`app/Providers/AppServiceProvider.php`:

```php
use App\Services\Blueprint\BlueprintStructureService;

public function register(): void
{
    // ... existing bindings ...

    $this->app->singleton(BlueprintStructureService::class);
}
```

---

## Тесты

### Unit: BlueprintStructureService

`tests/Unit/Services/Blueprint/BlueprintStructureServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Models\PostType;
use App\Services\Blueprint\BlueprintStructureService;

beforeEach(function () {
    $this->service = app(BlueprintStructureService::class);
});

test('createBlueprint создаёт blueprint', function () {
    $blueprint = $this->service->createBlueprint([
        'name' => 'Test Blueprint',
        'code' => 'test_bp',
        'description' => 'Test description',
    ]);

    expect($blueprint)->toBeInstanceOf(Blueprint::class)
        ->and($blueprint->code)->toBe('test_bp')
        ->and($blueprint->name)->toBe('Test Blueprint');
});

test('createPath создаёт поле с корректным full_path', function () {
    $blueprint = Blueprint::factory()->create();

    $path = $this->service->createPath($blueprint, [
        'name' => 'title',
        'data_type' => 'string',
    ]);

    expect($path->full_path)->toBe('title')
        ->and($path->blueprint_id)->toBe($blueprint->id);
});

test('createPath вычисляет full_path для вложенных полей', function () {
    $blueprint = Blueprint::factory()->create();

    $parent = $this->service->createPath($blueprint, [
        'name' => 'author',
        'data_type' => 'json',
    ]);

    $child = $this->service->createPath($blueprint, [
        'name' => 'name',
        'parent_id' => $parent->id,
        'data_type' => 'string',
    ]);

    expect($child->full_path)->toBe('author.name')
        ->and($child->parent_id)->toBe($parent->id);
});

test('updatePath пересчитывает full_path при изменении name', function () {
    $blueprint = Blueprint::factory()->create();

    $parent = $this->service->createPath($blueprint, [
        'name' => 'author',
        'data_type' => 'json',
    ]);

    $child = $this->service->createPath($blueprint, [
        'name' => 'name',
        'parent_id' => $parent->id,
        'data_type' => 'string',
    ]);

    // Изменить parent
    $this->service->updatePath($parent, ['name' => 'writer']);

    $parent->refresh();
    $child->refresh();

    expect($parent->full_path)->toBe('writer')
        ->and($child->full_path)->toBe('writer.name');
});

test('updatePath запрещает редактирование скопированных полей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect(fn() => $this->service->updatePath($copiedPath, ['name' => 'updated']))
        ->toThrow(\LogicException::class, 'Невозможно редактировать скопированное поле');
});

test('deletePath запрещает удаление скопированных полей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    expect(fn() => $this->service->deletePath($copiedPath))
        ->toThrow(\LogicException::class, 'Невозможно удалить скопированное поле');
});

test('createEmbed проверяет циклы', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    // A → B
    $this->service->createEmbed($a, $b);

    // B → A должно провалиться (цикл)
    expect(fn() => $this->service->createEmbed($b, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('createEmbed проверяет конфликты путей', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    // host уже имеет поле 'email'
    Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'email',
        'full_path' => 'email',
    ]);

    // embedded тоже имеет 'email'
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'email',
        'full_path' => 'email',
    ]);

    // Встраивание в корень → конфликт
    expect(fn() => $this->service->createEmbed($host, $embedded))
        ->toThrow(PathConflictException::class);
});

test('createEmbed запрещает дублирование', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    // Первое встраивание
    $this->service->createEmbed($host, $embedded);

    // Второе встраивание в корень → дубликат
    expect(fn() => $this->service->createEmbed($host, $embedded))
        ->toThrow(\LogicException::class, 'уже встроен');
});

test('createEmbed разрешает множественное встраивание под разными host_path', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    $office = $this->service->createPath($host, ['name' => 'office', 'data_type' => 'json']);
    $legal = $this->service->createPath($host, ['name' => 'legal', 'data_type' => 'json']);

    $embed1 = $this->service->createEmbed($host, $embedded, $office);
    $embed2 = $this->service->createEmbed($host, $embedded, $legal);

    expect($embed1->id)->not->toBe($embed2->id);
});

test('deleteEmbed удаляет встраивание и копии', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = $this->service->createEmbed($host, $embedded);

    $copiesCount = Path::where('blueprint_embed_id', $embed->id)->count();
    expect($copiesCount)->toBeGreaterThan(0);

    $this->service->deleteEmbed($embed);

    expect(BlueprintEmbed::find($embed->id))->toBeNull()
        ->and(Path::where('blueprint_embed_id', $embed->id)->count())->toBe(0);
});

test('deleteBlueprint запрещает удаление используемого в PostType', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    expect(fn() => $this->service->deleteBlueprint($blueprint))
        ->toThrow(\LogicException::class, 'используется в PostType');
});

test('deleteBlueprint запрещает удаление встроенного blueprint', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'f1', 'full_path' => 'f1']);

    $this->service->createEmbed($host, $embedded);

    expect(fn() => $this->service->deleteBlueprint($embedded))
        ->toThrow(\LogicException::class, 'встроен в другие blueprint');
});

test('getEmbeddableBlueprintsFor исключает циклы', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);

    // A → B
    $this->service->createEmbed($a, $b);

    $embeddable = $this->service->getEmbeddableBlueprintsFor($a);

    // Можно встроить C (нет цикла)
    expect($embeddable->pluck('id')->all())->toContain($c->id);

    // Нельзя встроить A в B (создаст цикл B → A → B)
    $embeddableForB = $this->service->getEmbeddableBlueprintsFor($b);
    expect($embeddableForB->pluck('id')->all())->not->toContain($a->id);
});

test('canDeleteBlueprint возвращает причины невозможности удаления', function () {
    $blueprint = Blueprint::factory()->create();
    PostType::factory()->create(['blueprint_id' => $blueprint->id]);

    $result = $this->service->canDeleteBlueprint($blueprint);

    expect($result['can_delete'])->toBeFalse()
        ->and($result['reasons'])->toContain('Используется в 1 PostType');
});
```

---

## Команды

```bash
# Создать сервис
mkdir -p app/Services/Blueprint
touch app/Services/Blueprint/BlueprintStructureService.php

# Тесты
mkdir -p tests/Unit/Services/Blueprint
touch tests/Unit/Services/Blueprint/BlueprintStructureServiceTest.php

# Запустить тесты
php artisan test --filter=BlueprintStructureService
```

---

## Критические моменты

1. **DB::transaction:** все операции атомарны
2. **Валидация перед действием:** циклы, конфликты, readonly
3. **События после изменений:** BlueprintStructureChanged триггерит каскады
4. **full_path автовычисляется:** при создании/изменении Path
5. **Защита скопированных полей:** нельзя редактировать/удалять через сервис
6. **Удаление с проверками:** PostType, embeds

---

## Использование в контроллерах

```php
use App\Services\Blueprint\BlueprintStructureService;

class BlueprintController extends Controller
{
    public function __construct(
        private BlueprintStructureService $structureService
    ) {}

    public function store(Request $request)
    {
        $blueprint = $this->structureService->createBlueprint($request->validated());
        return new BlueprintResource($blueprint);
    }

    public function addPath(Request $request, Blueprint $blueprint)
    {
        $path = $this->structureService->createPath(
            $blueprint,
            $request->validated()
        );
        return new PathResource($path);
    }

    public function createEmbed(Request $request, Blueprint $blueprint)
    {
        $embedded = Blueprint::findOrFail($request->input('embedded_blueprint_id'));
        $hostPath = $request->input('host_path_id')
            ? Path::findOrFail($request->input('host_path_id'))
            : null;

        $embed = $this->structureService->createEmbed(
            $blueprint,
            $embedded,
            $hostPath
        );

        return new BlueprintEmbedResource($embed);
    }

    public function destroy(Blueprint $blueprint)
    {
        $check = $this->structureService->canDeleteBlueprint($blueprint);

        if (!$check['can_delete']) {
            return response()->json([
                'message' => 'Невозможно удалить blueprint',
                'reasons' => $check['reasons'],
            ], 422);
        }

        $this->structureService->deleteBlueprint($blueprint);

        return response()->noContent();
    }
}
```

---

## API примеры

```bash
# Создать blueprint
POST /api/blueprints
{
  "name": "Article",
  "code": "article",
  "description": "Blog article structure"
}

# Добавить поле
POST /api/blueprints/1/paths
{
  "name": "title",
  "data_type": "string",
  "is_required": true,
  "is_indexed": true
}

# Добавить вложенное поле
POST /api/blueprints/1/paths
{
  "name": "email",
  "parent_id": 5,
  "data_type": "string"
}

# Создать встраивание
POST /api/blueprints/1/embeds
{
  "embedded_blueprint_id": 2,
  "host_path_id": 5
}

# Удалить встраивание
DELETE /api/embeds/3

# Проверить возможность удаления
GET /api/blueprints/1/can-delete
{
  "can_delete": false,
  "reasons": [
    "Используется в 3 PostType",
    "Встроен в 2 других blueprint"
  ]
}

# Получить граф зависимостей
GET /api/blueprints/1/dependencies
{
  "depends_on": [2, 3, 5],
  "depended_by": [7, 9]
}
```

---

**Результат:** Центральный сервис готов, вся бизнес-логика инкапсулирована, валидация и события работают автоматически.

**Создано 6 документов (196 часов Must Have):**

✅ A: Схема БД (18 ч)  
✅ B: Граф зависимостей (12 ч)  
✅ C: Материализация (40 ч)  
✅ D: Каскадные события (32 ч)  
✅ F+G: Entry и индексация (46 ч)  
✅ H: BlueprintStructureService (48 ч)

**Основной функционал (Must Have) готов!** Остались опциональные блоки: контроллеры (I), тестирование (J), оптимизация (K-M).
