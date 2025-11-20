# Блок C: Материализация полей

**Трудоёмкость:** 40 часов  
**Критичность:** 🔴 Без этого система не работает  
**Результат:** Рекурсивный копировщик, PRE-CHECK конфликтов, защита от переполнения стека

---

## C.1-C.2. Exceptions

### PathConflictException

`app/Exceptions/Blueprint/PathConflictException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: конфликт full_path при встраивании blueprint.
 *
 * Выбрасывается, когда материализация создаст path с full_path,
 * который уже существует в host blueprint.
 */
class PathConflictException extends LogicException
{
    /**
     * Создать исключение для конфликта путей.
     *
     * @param string $hostCode Код host blueprint
     * @param string $embeddedCode Код embedded blueprint
     * @param array<string> $conflictingPaths Список конфликтующих путей
     * @return self
     */
    public static function create(
        string $hostCode,
        string $embeddedCode,
        array $conflictingPaths
    ): self {
        $pathsList = implode(', ', array_map(fn($p) => "'$p'", $conflictingPaths));
        
        return new self(
            "Невозможно встроить blueprint '{$embeddedCode}' в '{$hostCode}': " .
            "конфликт путей: {$pathsList}. " .
            "Переименуйте поля или измените host_path."
        );
    }
}
```

### MaxDepthExceededException

`app/Exceptions/Blueprint/MaxDepthExceededException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: превышена максимальная глубина вложенности встраиваний.
 */
class MaxDepthExceededException extends LogicException
{
    /**
     * @param int $maxDepth Максимально допустимая глубина
     * @return self
     */
    public static function create(int $maxDepth): self
    {
        return new self(
            "Превышена максимальная глубина вложенности встраиваний ({$maxDepth}). " .
            "Упростите структуру blueprint'ов."
        );
    }
}
```

---

## C.3. Валидатор конфликтов путей

`app/Services/Blueprint/PathConflictValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\Path;

/**
 * Валидатор конфликтов full_path при материализации.
 *
 * PRE-CHECK: проверяет конфликты ДО начала копирования.
 */
class PathConflictValidator
{
    /**
     * Проверить, что материализация не создаст конфликтов full_path.
     *
     * @param Blueprint $embeddedBlueprint Кого встраиваем
     * @param Blueprint $hostBlueprint В кого встраиваем
     * @param string|null $baseParentPath Базовый путь (или null для корня)
     * @return void
     * @throws PathConflictException
     */
    public function validateNoConflicts(
        Blueprint $embeddedBlueprint,
        Blueprint $hostBlueprint,
        ?string $baseParentPath
    ): void {
        // 1. Собрать все будущие пути (включая транзитивные)
        $futurePaths = $this->collectFuturePathsRecursive(
            $embeddedBlueprint,
            $baseParentPath
        );

        // 2. Проверить пересечения с существующими путями
        $existingPaths = Path::query()
            ->where('blueprint_id', $hostBlueprint->id)
            ->whereIn('full_path', $futurePaths)
            ->pluck('full_path')
            ->all();

        if (!empty($existingPaths)) {
            throw PathConflictException::create(
                $hostBlueprint->code,
                $embeddedBlueprint->code,
                $existingPaths
            );
        }
    }

    /**
     * Рекурсивно собрать все full_path, которые появятся при материализации.
     *
     * @param Blueprint $blueprint
     * @param string|null $baseParentPath
     * @param int $depth Текущая глубина рекурсии
     * @return array<string>
     */
    private function collectFuturePathsRecursive(
        Blueprint $blueprint,
        ?string $baseParentPath,
        int $depth = 0
    ): array {
        // Защита от слишком глубокой вложенности
        if ($depth > 10) {
            return [];
        }

        $paths = [];

        // Собрать собственные поля (без source_blueprint_id)
        $ownPaths = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->get(['name', 'full_path', 'id']);

        // Создать map: id → name для быстрого поиска
        $pathNames = $ownPaths->pluck('name', 'id')->all();

        foreach ($ownPaths as $path) {
            $futureFullPath = $baseParentPath
                ? $baseParentPath . '.' . $path->name
                : $path->name;

            $paths[] = $futureFullPath;
        }

        // Рекурсивно обойти внутренние embeds
        $innerEmbeds = $blueprint->embeds()->with('hostPath', 'embeddedBlueprint')->get();

        foreach ($innerEmbeds as $innerEmbed) {
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed под конкретным полем
                $hostPathName = $pathNames[$innerHostPath->id] ?? $innerHostPath->name;
                $newBasePath = $baseParentPath
                    ? $baseParentPath . '.' . $hostPathName
                    : $hostPathName;
            } else {
                // Embed в корень
                $newBasePath = $baseParentPath;
            }

            $childPaths = $this->collectFuturePathsRecursive(
                $innerEmbed->embeddedBlueprint,
                $newBasePath,
                $depth + 1
            );

            $paths = array_merge($paths, $childPaths);
        }

        return $paths;
    }
}
```

---

## C.4. Сервис материализации

`app/Services/Blueprint/MaterializationService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\DB;

/**
 * Сервис рекурсивной материализации встраиваний.
 *
 * Копирует структуру embedded blueprint в host blueprint,
 * включая все транзитивные встраивания.
 */
class MaterializationService
{
    /**
     * Максимальная глубина вложенности встраиваний.
     */
    private const MAX_EMBED_DEPTH = 5;

    /**
     * @param PathConflictValidator $conflictValidator
     */
    public function __construct(
        private readonly PathConflictValidator $conflictValidator
    ) {}

    /**
     * Материализовать встраивание со всеми транзитивными зависимостями.
     *
     * Синхронная операция в рамках DB::transaction.
     *
     * @param BlueprintEmbed $embed Встраивание для материализации
     * @return void
     * @throws PathConflictException
     * @throws MaxDepthExceededException
     */
    public function materialize(BlueprintEmbed $embed): void
    {
        $hostBlueprint = $embed->blueprint;
        $embeddedBlueprint = $embed->embeddedBlueprint;
        $hostPath = $embed->hostPath;

        DB::transaction(function () use ($embed, $hostBlueprint, $embeddedBlueprint, $hostPath) {
            $baseParentId = $hostPath?->id;
            $baseParentPath = $hostPath?->full_path;

            // 1. PRE-CHECK: проверка конфликтов full_path
            $this->conflictValidator->validateNoConflicts(
                $embeddedBlueprint,
                $hostBlueprint,
                $baseParentPath
            );

            // 2. Удалить старые копии (включая транзитивные)
            Path::where('blueprint_embed_id', $embed->id)->delete();

            // 3. Рекурсивно скопировать структуру
            $this->copyBlueprintRecursive(
                blueprint: $embeddedBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $baseParentId,
                baseParentPath: $baseParentPath,
                rootEmbed: $embed,
                depth: 0
            );
        });
    }

    /**
     * Рекурсивно скопировать структуру blueprint (включая транзитивные embeds).
     *
     * @param Blueprint $blueprint Исходный blueprint (A, C, D, ...)
     * @param Blueprint $hostBlueprint Целевой blueprint (B)
     * @param int|null $baseParentId ID родительского path в B
     * @param string|null $baseParentPath full_path родителя в B
     * @param BlueprintEmbed $rootEmbed Корневой embed B→A (для blueprint_embed_id)
     * @param int $depth Текущая глубина рекурсии
     * @return void
     * @throws MaxDepthExceededException
     */
    private function copyBlueprintRecursive(
        Blueprint $blueprint,
        Blueprint $hostBlueprint,
        ?int $baseParentId,
        ?string $baseParentPath,
        BlueprintEmbed $rootEmbed,
        int $depth
    ): void {
        // Защита от переполнения стека
        if ($depth >= self::MAX_EMBED_DEPTH) {
            throw MaxDepthExceededException::create(self::MAX_EMBED_DEPTH);
        }

        // 1. Получить собственные поля blueprint (без source_blueprint_id)
        $sourcePaths = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->orderByRaw('LENGTH(full_path), full_path') // родители раньше детей
            ->get();

        // 2. Карта соответствия: source path id → copy (id, full_path)
        $idMap = [];
        $pathMap = [];

        foreach ($sourcePaths as $source) {
            // Создать копию
            $copy = $source->replicate([
                'blueprint_id',
                'parent_id',
                'full_path',
                'source_blueprint_id',
                'blueprint_embed_id',
                'is_readonly',
            ]);

            // Служебные поля
            $copy->blueprint_id = $hostBlueprint->id;
            $copy->source_blueprint_id = $blueprint->id;
            $copy->blueprint_embed_id = $rootEmbed->id; // ВСЕ копии привязаны к корневому embed
            $copy->is_readonly = true;

            // Вычислить parent_id и full_path
            if ($source->parent_id === null) {
                // Поле верхнего уровня → привязать к baseParent
                $parentId = $baseParentId;
                $parentPath = $baseParentPath;
            } else {
                // Дочернее поле → найти копию родителя
                $parentId = $idMap[$source->parent_id] ?? null;
                $parentPath = $pathMap[$source->parent_id] ?? null;
            }

            $copy->parent_id = $parentId;
            $copy->full_path = $parentPath
                ? $parentPath . '.' . $copy->name
                : $copy->name;

            // Сохранить (UNIQUE constraint требует корректный full_path)
            $copy->save();

            // Запомнить соответствие
            $idMap[$source->id] = $copy->id;
            $pathMap[$source->id] = $copy->full_path;
        }

        // 3. Рекурсивно развернуть внутренние embeds
        $innerEmbeds = $blueprint->embeds()
            ->with(['hostPath', 'embeddedBlueprint'])
            ->get();

        foreach ($innerEmbeds as $innerEmbed) {
            /** @var BlueprintEmbed $innerEmbed */
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed привязан к полю → найти копию этого поля
                $sourceHostId = $innerHostPath->id;

                if (!isset($idMap[$sourceHostId])) {
                    // Теоретически не должно случиться
                    throw new \LogicException(
                        "Не найдена копия host_path для embed {$innerEmbed->id}"
                    );
                }

                $childBaseParentId = $idMap[$sourceHostId];
                $childBaseParentPath = $pathMap[$sourceHostId];
            } else {
                // Embed в корень → базовый путь остаётся тем же
                $childBaseParentId = $baseParentId;
                $childBaseParentPath = $baseParentPath;
            }

            $childBlueprint = $innerEmbed->embeddedBlueprint;

            // Рекурсивный вызов
            $this->copyBlueprintRecursive(
                blueprint: $childBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $childBaseParentId,
                baseParentPath: $childBaseParentPath,
                rootEmbed: $rootEmbed, // НЕ меняется!
                depth: $depth + 1
            );
        }
    }

    /**
     * Рематериализовать все embeds указанного blueprint.
     *
     * Используется при изменении структуры blueprint.
     *
     * @param Blueprint $blueprint
     * @return void
     */
    public function rematerializeAllEmbeds(Blueprint $blueprint): void
    {
        // Найти все места, где blueprint встроен в другие
        $embeds = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->with(['blueprint', 'embeddedBlueprint', 'hostPath'])
            ->get();

        foreach ($embeds as $embed) {
            $this->materialize($embed);
        }
    }
}
```

---

## C.5. Регистрация в AppServiceProvider

`app/Providers/AppServiceProvider.php` (добавить):

```php
use App\Services\Blueprint\MaterializationService;
use App\Services\Blueprint\PathConflictValidator;

public function register(): void
{
    // ... existing bindings ...

    $this->app->singleton(PathConflictValidator::class);
    $this->app->singleton(MaterializationService::class);
}
```

---

## Тесты

### Unit: PRE-CHECK конфликтов

`tests/Unit/Services/Blueprint/PathConflictValidatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\PathConflictValidator;

beforeEach(function () {
    $this->validator = app(PathConflictValidator::class);
});

test('конфликт путей выбрасывает исключение', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

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
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, null))
        ->toThrow(PathConflictException::class, "конфликт путей: 'email'");
});

test('конфликт с вложенным путём', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    // host имеет meta.created_at
    $meta = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'meta',
        'full_path' => 'meta',
    ]);

    Path::factory()->create([
        'blueprint_id' => $host->id,
        'parent_id' => $meta->id,
        'name' => 'created_at',
        'full_path' => 'meta.created_at',
    ]);

    // embedded имеет created_at
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'created_at',
        'full_path' => 'created_at',
    ]);

    // Встраиваем embedded под meta → конфликт meta.created_at
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, 'meta'))
        ->toThrow(PathConflictException::class, "meta.created_at");
});

test('нет конфликта при встраивании под разными базовыми путями', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create([
        'blueprint_id' => $host->id,
        'full_path' => 'office.address',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'full_path' => 'address',
    ]);

    // Встраиваем под 'legal' → legal.address ≠ office.address
    expect(fn() => $this->validator->validateNoConflicts($embedded, $host, 'legal'))
        ->not->toThrow(PathConflictException::class);
});

test('транзитивные пути проверяются', function () {
    // Blueprint C
    $c = Blueprint::factory()->create(['code' => 'c']);
    Path::factory()->create(['blueprint_id' => $c->id, 'name' => 'field_c', 'full_path' => 'field_c']);

    // Blueprint A встраивает C
    $a = Blueprint::factory()->create(['code' => 'a']);
    $groupC = Path::factory()->create([
        'blueprint_id' => $a->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
    ]);

    $embedAC = BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $c->id,
        'host_path_id' => $groupC->id,
    ]);

    // Blueprint B уже имеет author.group_c.field_c
    $b = Blueprint::factory()->create(['code' => 'b']);
    $author = Path::factory()->create(['blueprint_id' => $b->id, 'name' => 'author', 'full_path' => 'author']);
    $groupCinB = Path::factory()->create([
        'blueprint_id' => $b->id,
        'parent_id' => $author->id,
        'name' => 'group_c',
        'full_path' => 'author.group_c',
    ]);
    Path::factory()->create([
        'blueprint_id' => $b->id,
        'parent_id' => $groupCinB->id,
        'name' => 'field_c',
        'full_path' => 'author.group_c.field_c',
    ]);

    // Попытка встроить A под 'author' → конфликт транзитивного пути
    expect(fn() => $this->validator->validateNoConflicts($a, $b, 'author'))
        ->toThrow(PathConflictException::class, "author.group_c.field_c");
});
```

### Unit: Материализация

`tests/Unit/Services/Blueprint/MaterializationServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\MaterializationService;

beforeEach(function () {
    $this->service = app(MaterializationService::class);
});

test('простое встраивание создаёт копии полей', function () {
    $host = Blueprint::factory()->create(['code' => 'host']);
    $embedded = Blueprint::factory()->create(['code' => 'embedded']);

    // Embedded поля
    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field1',
        'full_path' => 'field1',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'field2',
        'full_path' => 'field2',
    ]);

    // Создаём embed
    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => null, // в корень
    ]);

    // Материализуем
    $this->service->materialize($embed);

    // Проверяем копии
    $copies = Path::where('blueprint_id', $host->id)
        ->where('blueprint_embed_id', $embed->id)
        ->get();

    expect($copies)->toHaveCount(2)
        ->and($copies->pluck('name')->all())->toContain('field1', 'field2')
        ->and($copies->pluck('full_path')->all())->toContain('field1', 'field2')
        ->and($copies->every(fn($p) => $p->is_readonly))->toBeTrue()
        ->and($copies->every(fn($p) => $p->source_blueprint_id === $embedded->id))->toBeTrue();
});

test('встраивание под host_path создаёт вложенные пути', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    $hostPath = Path::factory()->create([
        'blueprint_id' => $host->id,
        'name' => 'author',
        'full_path' => 'author',
    ]);

    Path::factory()->create([
        'blueprint_id' => $embedded->id,
        'name' => 'name',
        'full_path' => 'name',
    ]);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $hostPath->id,
    ]);

    $this->service->materialize($embed);

    $copy = Path::where('blueprint_id', $host->id)
        ->where('blueprint_embed_id', $embed->id)
        ->where('name', 'name')
        ->first();

    expect($copy)->not->toBeNull()
        ->and($copy->full_path)->toBe('author.name')
        ->and($copy->parent_id)->toBe($hostPath->id);
});

test('транзитивное встраивание D → C → A → B', function () {
    // Blueprint D
    $d = Blueprint::factory()->create(['code' => 'd']);
    Path::factory()->create(['blueprint_id' => $d->id, 'name' => 'field_d', 'full_path' => 'field_d']);

    // Blueprint C + embed D
    $c = Blueprint::factory()->create(['code' => 'c']);
    $groupD = Path::factory()->create([
        'blueprint_id' => $c->id,
        'name' => 'group_d',
        'full_path' => 'group_d',
    ]);
    $embedCD = BlueprintEmbed::create([
        'blueprint_id' => $c->id,
        'embedded_blueprint_id' => $d->id,
        'host_path_id' => $groupD->id,
    ]);
    $this->service->materialize($embedCD);

    // Blueprint A + embed C
    $a = Blueprint::factory()->create(['code' => 'a']);
    $groupC = Path::factory()->create([
        'blueprint_id' => $a->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
    ]);
    $embedAC = BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $c->id,
        'host_path_id' => $groupC->id,
    ]);
    $this->service->materialize($embedAC);

    // Blueprint B + embed A
    $b = Blueprint::factory()->create(['code' => 'b']);
    $groupA = Path::factory()->create([
        'blueprint_id' => $b->id,
        'name' => 'group_a',
        'full_path' => 'group_a',
    ]);
    $embedBA = BlueprintEmbed::create([
        'blueprint_id' => $b->id,
        'embedded_blueprint_id' => $a->id,
        'host_path_id' => $groupA->id,
    ]);
    $this->service->materialize($embedBA);

    // Проверяем транзитивное поле из D
    $transitiveField = Path::where('blueprint_id', $b->id)
        ->where('full_path', 'group_a.group_c.group_d.field_d')
        ->first();

    expect($transitiveField)->not->toBeNull()
        ->and($transitiveField->source_blueprint_id)->toBe($d->id)
        ->and($transitiveField->blueprint_embed_id)->toBe($embedBA->id); // корневой embed B→A
});

test('множественное встраивание Address в Company', function () {
    $company = Blueprint::factory()->create(['code' => 'company']);
    $address = Blueprint::factory()->create(['code' => 'address']);

    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'street', 'full_path' => 'street']);
    Path::factory()->create(['blueprint_id' => $address->id, 'name' => 'city', 'full_path' => 'city']);

    $office = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'office', 'full_path' => 'office']);
    $legal = Path::factory()->create(['blueprint_id' => $company->id, 'name' => 'legal', 'full_path' => 'legal']);

    // Два embed'а одного blueprint
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

    $this->service->materialize($embed1);
    $this->service->materialize($embed2);

    // Проверяем раздельные копии
    $officePaths = Path::where('blueprint_embed_id', $embed1->id)->pluck('full_path')->all();
    $legalPaths = Path::where('blueprint_embed_id', $embed2->id)->pluck('full_path')->all();

    expect($officePaths)->toContain('office.street', 'office.city')
        ->and($legalPaths)->toContain('legal.street', 'legal.city');
});

test('рематериализация удаляет старые копии', function () {
    $host = Blueprint::factory()->create();
    $embedded = Blueprint::factory()->create();

    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field1', 'full_path' => 'field1']);

    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
    ]);

    // Первая материализация
    $this->service->materialize($embed);
    $countBefore = Path::where('blueprint_embed_id', $embed->id)->count();

    // Добавляем новое поле в embedded
    Path::factory()->create(['blueprint_id' => $embedded->id, 'name' => 'field2', 'full_path' => 'field2']);

    // Рематериализация
    $this->service->materialize($embed);
    $countAfter = Path::where('blueprint_embed_id', $embed->id)->count();

    expect($countAfter)->toBe(2) // field1 + field2
        ->and($countBefore)->toBe(1);
});

test('превышение максимальной глубины выбрасывает исключение', function () {
    // Создать цепочку длиннее MAX_EMBED_DEPTH (5)
    $blueprints = collect(range(0, 6))->map(fn($i) => Blueprint::factory()->create(['code' => "bp$i"]));

    foreach ($blueprints as $i => $bp) {
        Path::factory()->create([
            'blueprint_id' => $bp->id,
            'name' => "field$i",
            'full_path' => "field$i",
        ]);

        if ($i < $blueprints->count() - 1) {
            $group = Path::factory()->create([
                'blueprint_id' => $bp->id,
                'name' => "group$i",
                'full_path' => "group$i",
            ]);

            $embed = BlueprintEmbed::create([
                'blueprint_id' => $bp->id,
                'embedded_blueprint_id' => $blueprints[$i + 1]->id,
                'host_path_id' => $group->id,
            ]);

            if ($i > 0) {
                $this->service->materialize($embed);
            }
        }
    }

    $rootEmbed = BlueprintEmbed::create([
        'blueprint_id' => $blueprints[0]->id,
        'embedded_blueprint_id' => $blueprints[1]->id,
        'host_path_id' => Path::where('blueprint_id', $blueprints[0]->id)->where('name', 'group0')->first()->id,
    ]);

    expect(fn() => $this->service->materialize($rootEmbed))
        ->toThrow(MaxDepthExceededException::class);
});
```

---

## Команды

```bash
# Создать exceptions
mkdir -p app/Exceptions/Blueprint
touch app/Exceptions/Blueprint/PathConflictException.php
touch app/Exceptions/Blueprint/MaxDepthExceededException.php

# Создать сервисы
mkdir -p app/Services/Blueprint
touch app/Services/Blueprint/PathConflictValidator.php
touch app/Services/Blueprint/MaterializationService.php

# Тесты
mkdir -p tests/Unit/Services/Blueprint
touch tests/Unit/Services/Blueprint/PathConflictValidatorTest.php
touch tests/Unit/Services/Blueprint/MaterializationServiceTest.php

# Запустить тесты
php artisan test --filter=PathConflictValidator
php artisan test --filter=MaterializationService
```

---

## Критические моменты

1. **PRE-CHECK обязателен:** проверять конфликты ДО начала копирования (иначе SQL error)
2. **Рекурсия до конца:** транзитивные embeds разворачиваются полностью
3. **Один blueprint_embed_id:** все копии привязаны к корневому embed (удаление одной командой)
4. **MAX_EMBED_DEPTH = 5:** защита от переполнения стека
5. **Синхронная обработка:** в рамках HTTP-запроса + DB::transaction
6. **full_path вычисляется:** нельзя сохранить path с пустым full_path (UNIQUE constraint)

---

## Использование в коде

```php
use App\Services\Blueprint\MaterializationService;

// После создания embed
$embed = BlueprintEmbed::create([...]);
$materializationService->materialize($embed);

// После изменения структуры blueprint
event(new BlueprintStructureChanged($blueprint));
// → listener вызовет rematerializeAllEmbeds($blueprint)
```

---

**Результат:** Рекурсивная материализация работает, транзитивные зависимости разворачиваются, конфликты проверяются заранее.

**Следующий блок:** D (Каскадные события при изменении структуры).

