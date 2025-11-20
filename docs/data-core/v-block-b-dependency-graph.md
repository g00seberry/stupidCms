# Блок B: Встраивание и граф зависимостей

**Трудоёмкость:** 12 часов (8 ч Must Have + 4 ч опционально)  
**Критичность:** 🔴 Без этого — data corruption  
**Результат:** Валидатор циклов, сервис обхода графа, closure table (опционально)

---

## B.1-B.2. Проверка циклических зависимостей

### 1. Exception для доменных ошибок

```bash
mkdir -p app/Exceptions/Blueprint
```

`app/Exceptions/Blueprint/CyclicDependencyException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: попытка создать циклическую зависимость между blueprint'ами.
 *
 * Выбрасывается при попытке встроить blueprint A в B,
 * если B уже зависит от A (прямо или транзитивно).
 */
class CyclicDependencyException extends LogicException
{
    /**
     * Создать исключение для попытки встроить blueprint в самого себя.
     *
     * @param string $blueprintCode Код blueprint
     * @return self
     */
    public static function selfEmbed(string $blueprintCode): self
    {
        return new self("Нельзя встроить blueprint '{$blueprintCode}' в самого себя.");
    }

    /**
     * Создать исключение для циклической зависимости.
     *
     * @param string $hostCode Код host blueprint (кто встраивает)
     * @param string $embeddedCode Код embedded blueprint (кого встраивают)
     * @return self
     */
    public static function circularDependency(string $hostCode, string $embeddedCode): self
    {
        return new self(
            "Циклическая зависимость: '{$embeddedCode}' уже зависит от '{$hostCode}' " .
            "(прямо или транзитивно). Встраивание невозможно."
        );
    }
}
```

### 2. Сервис обхода графа зависимостей

```bash
mkdir -p app/Services/Blueprint
```

`app/Services/Blueprint/DependencyGraphService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintEmbed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Сервис для работы с графом зависимостей blueprint'ов.
 *
 * Граф зависимостей: B → A означает, что B встраивает A.
 * Один blueprint может быть встроен в другой несколько раз (под разными host_path).
 */
class DependencyGraphService
{
    /**
     * Проверить, существует ли путь от fromId к targetId в графе зависимостей.
     *
     * Использует BFS (поиск в ширину) для обхода графа.
     * Граф строится по уникальным парам (blueprint_id, embedded_blueprint_id).
     *
     * @param int $fromId ID blueprint, от которого ищем путь
     * @param int $targetId ID blueprint, к которому ищем путь
     * @return bool true, если путь существует
     */
    public function hasPathTo(int $fromId, int $targetId): bool
    {
        if ($fromId === $targetId) {
            return true;
        }

        $visited = [];
        $queue = [$fromId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $targetId) {
                return true;
            }

            // Получить все blueprint'ы, которые current встраивает
            $children = $this->getDirectDependencies($current);

            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        return false;
    }

    /**
     * Получить все blueprint'ы, которые прямо зависят от указанного.
     *
     * B зависит от A = B встраивает A.
     *
     * @param int $blueprintId ID blueprint
     * @return array<int> Массив ID зависимых blueprint'ов
     */
    public function getDirectDependencies(int $blueprintId): array
    {
        return BlueprintEmbed::query()
            ->where('blueprint_id', $blueprintId)
            ->pluck('embedded_blueprint_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Получить все blueprint'ы, в которые встроен указанный blueprint.
     *
     * B зависит от A = B встраивает A. Метод возвращает все B для данного A.
     *
     * @param int $blueprintId ID blueprint
     * @return array<int> Массив ID blueprint'ов, которые встраивают данный
     */
    public function getDirectDependents(int $blueprintId): array
    {
        return BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprintId)
            ->pluck('blueprint_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Получить все blueprint'ы, которые транзитивно зависят от указанного.
     *
     * Если A встроен в B, а B встроен в C, то C транзитивно зависит от A.
     * Метод возвращает все C для данного A.
     *
     * @param int $rootBlueprintId ID blueprint
     * @return Collection<int, int> Collection ID blueprint'ов
     */
    public function getAllDependentBlueprintIds(int $rootBlueprintId): Collection
    {
        $result = collect();
        $visited = [];
        $queue = [$rootBlueprintId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Кто встраивает текущий blueprint (прямые зависимые)
            $parents = $this->getDirectDependents($current);

            foreach ($parents as $parentId) {
                if (!isset($visited[$parentId])) {
                    $result->push($parentId);
                    $queue[] = $parentId;
                }
            }
        }

        return $result->unique()->values();
    }

    /**
     * Получить все blueprint'ы, от которых транзитивно зависит указанный.
     *
     * Если B встраивает A, а A встраивает C, то B зависит от C транзитивно.
     * Метод возвращает все C для данного B.
     *
     * @param int $blueprintId ID blueprint
     * @return Collection<int, int> Collection ID blueprint'ов
     */
    public function getAllTransitiveDependencies(int $blueprintId): Collection
    {
        $result = collect();
        $visited = [];
        $queue = [$blueprintId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Кого встраивает текущий blueprint
            $children = $this->getDirectDependencies($current);

            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $result->push($childId);
                    $queue[] = $childId;
                }
            }
        }

        return $result->unique()->values();
    }
}
```

### 3. Валидатор циклических зависимостей

`app/Services/Blueprint/CyclicDependencyValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Models\Blueprint;

/**
 * Валидатор циклических зависимостей между blueprint'ами.
 *
 * Проверяет, что создание нового встраивания не приведёт к циклу в графе.
 */
class CyclicDependencyValidator
{
    /**
     * @param DependencyGraphService $graphService Сервис обхода графа
     */
    public function __construct(
        private readonly DependencyGraphService $graphService
    ) {}

    /**
     * Проверить, что встраивание blueprint'а не создаст цикл.
     *
     * Проверяет:
     * 1. host.id != embedded.id (нельзя встроить в самого себя)
     * 2. Не существует пути embedded → host (иначе цикл)
     *
     * @param Blueprint $host Кто встраивает
     * @param Blueprint $embedded Кого встраивают
     * @return void
     * @throws CyclicDependencyException
     */
    public function ensureNoCyclicDependency(Blueprint $host, Blueprint $embedded): void
    {
        // Проверка 1: нельзя встроить в самого себя
        if ($host->id === $embedded->id) {
            throw CyclicDependencyException::selfEmbed($host->code);
        }

        // Проверка 2: нет пути embedded → host
        // Если embedded уже зависит от host (прямо или транзитивно),
        // то добавление host → embedded создаст цикл
        if ($this->graphService->hasPathTo($embedded->id, $host->id)) {
            throw CyclicDependencyException::circularDependency(
                $host->code,
                $embedded->code
            );
        }
    }

    /**
     * Проверить, можно ли встроить blueprint (обёртка для удобства).
     *
     * @param int $hostId ID host blueprint
     * @param int $embeddedId ID embedded blueprint
     * @return bool true, если встраивание не создаст цикл
     */
    public function canEmbed(int $hostId, int $embeddedId): bool
    {
        if ($hostId === $embeddedId) {
            return false;
        }

        return !$this->graphService->hasPathTo($embeddedId, $hostId);
    }
}
```

### 4. Регистрация в AppServiceProvider

`app/Providers/AppServiceProvider.php` (добавить в метод `register()`):

```php
use App\Services\Blueprint\CyclicDependencyValidator;
use App\Services\Blueprint\DependencyGraphService;

public function register(): void
{
    // ... existing bindings ...

    $this->app->singleton(DependencyGraphService::class);
    $this->app->singleton(CyclicDependencyValidator::class);
}
```

---

## B.3. Closure Table (опционально, для >100 blueprint)

### Миграция

```bash
php artisan make:migration create_blueprint_deps_table
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
        Schema::create('blueprint_deps', function (Blueprint $table) {
            $table->foreignId('ancestor_id')->constrained('blueprints')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('blueprints')->cascadeOnDelete();
            $table->unsignedSmallInteger('depth');

            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id');
            $table->index(['ancestor_id', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_deps');
    }
};
```

### Модель

`app/Models/BlueprintDep.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Closure Table для транзитивных зависимостей blueprint'ов.
 *
 * ancestor_id → descendant_id означает, что descendant зависит от ancestor.
 * depth = количество рёбер между ними.
 *
 * @property int $ancestor_id
 * @property int $descendant_id
 * @property int $depth
 */
class BlueprintDep extends Model
{
    public $timestamps = false;
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];
}
```

### Сервис синхронизации Closure Table

`app/Services/Blueprint/ClosureTableSyncService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintDep;
use App\Models\BlueprintEmbed;
use Illuminate\Support\Facades\DB;

/**
 * Сервис синхронизации Closure Table для оптимизации запросов к графу.
 *
 * Используется только для больших графов (>100 blueprint).
 */
class ClosureTableSyncService
{
    /**
     * Пересоздать Closure Table с нуля.
     *
     * Используется при первичной инициализации или полной пересборке графа.
     *
     * @return void
     */
    public function rebuildClosureTable(): void
    {
        DB::transaction(function () {
            BlueprintDep::query()->delete();

            // 1. Добавить прямые связи (depth = 1)
            $embeds = BlueprintEmbed::query()
                ->select('blueprint_id as ancestor_id', 'embedded_blueprint_id as descendant_id')
                ->get();

            foreach ($embeds as $embed) {
                BlueprintDep::create([
                    'ancestor_id' => $embed->ancestor_id,
                    'descendant_id' => $embed->descendant_id,
                    'depth' => 1,
                ]);
            }

            // 2. Добавить транзитивные связи (depth > 1)
            // Повторяем до тех пор, пока добавляются новые связи
            $maxIterations = 100; // защита от бесконечного цикла
            $iteration = 0;

            do {
                $inserted = DB::insert('
                    INSERT IGNORE INTO blueprint_deps (ancestor_id, descendant_id, depth)
                    SELECT DISTINCT
                        a.ancestor_id,
                        b.descendant_id,
                        a.depth + b.depth AS depth
                    FROM blueprint_deps a
                    JOIN blueprint_deps b ON a.descendant_id = b.ancestor_id
                    WHERE NOT EXISTS (
                        SELECT 1 FROM blueprint_deps c
                        WHERE c.ancestor_id = a.ancestor_id
                          AND c.descendant_id = b.descendant_id
                    )
                ');

                $iteration++;
            } while ($inserted > 0 && $iteration < $maxIterations);
        });
    }

    /**
     * Обновить Closure Table после добавления нового embed.
     *
     * @param int $hostId ID host blueprint
     * @param int $embeddedId ID embedded blueprint
     * @return void
     */
    public function addEmbed(int $hostId, int $embeddedId): void
    {
        DB::transaction(function () use ($hostId, $embeddedId) {
            // 1. Добавить прямую связь
            BlueprintDep::create([
                'ancestor_id' => $hostId,
                'descendant_id' => $embeddedId,
                'depth' => 1,
            ]);

            // 2. Добавить транзитивные связи
            // Все предки host → embedded
            DB::insert('
                INSERT INTO blueprint_deps (ancestor_id, descendant_id, depth)
                SELECT ancestor_id, ?, depth + 1
                FROM blueprint_deps
                WHERE descendant_id = ?
            ', [$embeddedId, $hostId]);

            // host → все потомки embedded
            DB::insert('
                INSERT INTO blueprint_deps (ancestor_id, descendant_id, depth)
                SELECT ?, descendant_id, depth + 1
                FROM blueprint_deps
                WHERE ancestor_id = ?
            ', [$hostId, $embeddedId]);

            // Все предки host → все потомки embedded
            DB::insert('
                INSERT INTO blueprint_deps (ancestor_id, descendant_id, depth)
                SELECT a.ancestor_id, b.descendant_id, a.depth + b.depth + 1
                FROM blueprint_deps a
                CROSS JOIN blueprint_deps b
                WHERE a.descendant_id = ?
                  AND b.ancestor_id = ?
            ', [$hostId, $embeddedId]);
        });
    }

    /**
     * Обновить Closure Table после удаления embed.
     *
     * @param int $hostId ID host blueprint
     * @param int $embeddedId ID embedded blueprint
     * @return void
     */
    public function removeEmbed(int $hostId, int $embeddedId): void
    {
        DB::transaction(function () use ($hostId, $embeddedId) {
            // Удалить все связи, проходящие через удаляемое ребро
            BlueprintDep::query()
                ->whereIn(DB::raw('(ancestor_id, descendant_id)'), function ($query) use ($hostId, $embeddedId) {
                    $query->select('a.ancestor_id', 'b.descendant_id')
                        ->from('blueprint_deps as a')
                        ->join('blueprint_deps as b', function ($join) use ($hostId, $embeddedId) {
                            $join->on('a.descendant_id', '=', DB::raw($hostId))
                                ->where('b.ancestor_id', '=', $embeddedId);
                        });
                })
                ->delete();

            // Пересоздать closure table (проще, чем вычислять что осталось)
            $this->rebuildClosureTable();
        });
    }

    /**
     * Проверить, существует ли путь (используя Closure Table).
     *
     * @param int $fromId
     * @param int $targetId
     * @return bool
     */
    public function hasPath(int $fromId, int $targetId): bool
    {
        if ($fromId === $targetId) {
            return true;
        }

        return BlueprintDep::query()
            ->where('ancestor_id', $fromId)
            ->where('descendant_id', $targetId)
            ->exists();
    }
}
```

### Оптимизированный DependencyGraphService (с Closure Table)

Обновить `DependencyGraphService::hasPathTo()`:

```php
public function __construct(
    private readonly ?ClosureTableSyncService $closureTable = null
) {}

public function hasPathTo(int $fromId, int $targetId): bool
{
    // Если Closure Table включена, используем её
    if ($this->closureTable !== null) {
        return $this->closureTable->hasPath($fromId, $targetId);
    }

    // Иначе BFS (как раньше)
    // ... existing BFS code ...
}
```

---

## Тесты

### Unit: Валидация циклов

`tests/Unit/Services/Blueprint/CyclicDependencyValidatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Services\Blueprint\CyclicDependencyValidator;

beforeEach(function () {
    $this->validator = app(CyclicDependencyValidator::class);
});

test('запрет встраивания в самого себя', function () {
    $blueprint = Blueprint::factory()->create(['code' => 'person']);

    expect(fn() => $this->validator->ensureNoCyclicDependency($blueprint, $blueprint))
        ->toThrow(CyclicDependencyException::class, "Нельзя встроить blueprint 'person' в самого себя");
});

test('запрет прямого цикла A → B → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    // Создаём A → B
    BlueprintEmbed::create([
        'blueprint_id' => $a->id,
        'embedded_blueprint_id' => $b->id,
    ]);

    // Попытка B → A должна провалиться
    expect(fn() => $this->validator->ensureNoCyclicDependency($b, $a))
        ->toThrow(CyclicDependencyException::class, "Циклическая зависимость");
});

test('запрет транзитивного цикла A → B → C → A', function () {
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);
    $c = Blueprint::factory()->create(['code' => 'c']);

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $c->id]);

    expect(fn() => $this->validator->ensureNoCyclicDependency($c, $a))
        ->toThrow(CyclicDependencyException::class);
});

test('разрешено множественное встраивание без цикла', function () {
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);

    // Company → Address дважды (под разными host_path)
    BlueprintEmbed::create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => null,
    ]);

    // Второй embed должен пройти валидацию
    expect(fn() => $this->validator->ensureNoCyclicDependency($company, $address))
        ->not->toThrow(CyclicDependencyException::class);
});
```

### Unit: Граф зависимостей

`tests/Unit/Services/Blueprint/DependencyGraphServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Services\Blueprint\DependencyGraphService;

beforeEach(function () {
    $this->service = app(DependencyGraphService::class);
});

test('hasPathTo находит прямое ребро', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);

    expect($this->service->hasPathTo($a->id, $b->id))->toBeTrue();
    expect($this->service->hasPathTo($b->id, $a->id))->toBeFalse();
});

test('hasPathTo находит транзитивный путь', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $c->id]);

    expect($this->service->hasPathTo($a->id, $c->id))->toBeTrue();
});

test('getAllDependentBlueprintIds возвращает всех зависимых', function () {
    $root = Blueprint::factory()->create(['code' => 'root']);
    $child1 = Blueprint::factory()->create(['code' => 'child1']);
    $child2 = Blueprint::factory()->create(['code' => 'child2']);
    $grandchild = Blueprint::factory()->create(['code' => 'grandchild']);

    // root ← child1
    BlueprintEmbed::create(['blueprint_id' => $child1->id, 'embedded_blueprint_id' => $root->id]);
    // root ← child2
    BlueprintEmbed::create(['blueprint_id' => $child2->id, 'embedded_blueprint_id' => $root->id]);
    // child1 ← grandchild
    BlueprintEmbed::create(['blueprint_id' => $grandchild->id, 'embedded_blueprint_id' => $child1->id]);

    $dependents = $this->service->getAllDependentBlueprintIds($root->id);

    expect($dependents)->toHaveCount(3)
        ->and($dependents->all())->toContain($child1->id, $child2->id, $grandchild->id);
});
```

---

## Команды

```bash
# Создать exception
mkdir -p app/Exceptions/Blueprint
touch app/Exceptions/Blueprint/CyclicDependencyException.php

# Создать сервисы
mkdir -p app/Services/Blueprint
touch app/Services/Blueprint/DependencyGraphService.php
touch app/Services/Blueprint/CyclicDependencyValidator.php

# Опционально: Closure Table
php artisan make:migration create_blueprint_deps_table
php artisan make:model BlueprintDep
touch app/Services/Blueprint/ClosureTableSyncService.php

# Тесты
mkdir -p tests/Unit/Services/Blueprint
touch tests/Unit/Services/Blueprint/CyclicDependencyValidatorTest.php
touch tests/Unit/Services/Blueprint/DependencyGraphServiceTest.php

# Запустить тесты
php artisan test --filter=CyclicDependency
php artisan test --filter=DependencyGraph
```

---

## Критические моменты

1. **Проверка циклов обязательна:** без неё — data corruption при материализации
2. **BFS vs DFS:** BFS эффективнее для неглубоких графов (<10 уровней)
3. **Closure Table:** включать только при >100 blueprint (overhead синхронизации)
4. **Защита от бесконечных циклов:** лимит итераций в `rebuildClosureTable()`

---

## Использование в коде

```php
use App\Services\Blueprint\CyclicDependencyValidator;

// В сервисе создания embed
public function createEmbed(Blueprint $host, Blueprint $embedded, ?Path $hostPath): BlueprintEmbed
{
    // 1. Проверка циклов (обязательно)
    $this->cyclicValidator->ensureNoCyclicDependency($host, $embedded);

    // 2. Создание embed
    $embed = BlueprintEmbed::create([
        'blueprint_id' => $host->id,
        'embedded_blueprint_id' => $embedded->id,
        'host_path_id' => $hostPath?->id,
    ]);

    // 3. Синхронизация Closure Table (если включена)
    if ($this->closureTable) {
        $this->closureTable->addEmbed($host->id, $embedded->id);
    }

    return $embed;
}
```

---

**Результат:** Граф зависимостей защищён от циклов, обход графа работает, closure table готова к использованию.

**Следующий блок:** C (Материализация полей).

