# План реализации Blueprint-системы для stupidCms

Детальный поэтапный план с разбивкой на модули и задачи.

---

## Общая структура

```
МОДУЛЬ 1: Базовая инфраструктура (миграции + модели)
    ├─ Задача 1.1: Миграции таблиц
    ├─ Задача 1.2: Базовые модели
    └─ Задача 1.3: Фабрики и сидеры

МОДУЛЬ 2: Материализация компонентов
    ├─ Задача 2.1: Логика материализации в Blueprint
    ├─ Задача 2.2: Observers для синхронизации
    └─ Задача 2.3: Job для реиндексации

МОДУЛЬ 3: Индексация документов
    ├─ Задача 3.1: Трейт HasDocumentData
    ├─ Задача 3.2: Скоупы для запросов
    └─ Задача 3.3: Подключение к Entry

МОДУЛЬ 4: Валидация и безопасность
    ├─ Задача 4.1: Request классы
    ├─ Задача 4.2: Валидация циклов
    └─ Задача 4.3: Проверка конфликтов

МОДУЛЬ 5: API контроллеры
    ├─ Задача 5.1: CRUD Blueprint
    ├─ Задача 5.2: CRUD Path
    ├─ Задача 5.3: Управление компонентами
    └─ Задача 5.4: API Resources

МОДУЛЬ 6: Команды и утилиты
    ├─ Задача 6.1: Команда реиндексации
    ├─ Задача 6.2: Экспорт/импорт Blueprint
    └─ Задача 6.3: Диагностика схемы

МОДУЛЬ 7: Тестирование
    ├─ Задача 7.1: Unit тесты
    ├─ Задача 7.2: Feature тесты
    └─ Задача 7.3: Integration тесты

МОДУЛЬ 8: Миграция существующих данных
    ├─ Задача 8.1: Создание default Blueprint
    ├─ Задача 8.2: Миграция Entry
    └─ Задача 8.3: Валидация результата

МОДУЛЬ 9: Оптимизация и мониторинг
    ├─ Задача 9.1: Кеширование
    ├─ Задача 9.2: Batch операции
    └─ Задача 9.3: Логирование и метрики
```

---

## МОДУЛЬ 1: Базовая инфраструктура

**Цель:** Создать таблицы БД, базовые модели и тестовые данные.

**Зависимости:** Нет.

**Время:** 3-5 дней.

---

### Задача 1.1: Миграции таблиц

**Файлы для создания:**

#### 1.1.1. Миграция `blueprints`

**Файл:** `database/migrations/2025_11_19_000010_create_blueprints_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_type_id')
                ->nullable()
                ->constrained('post_types')
                ->onDelete('cascade');
            $table->string('slug', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['full', 'component'])->default('full');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // MySQL-совместимый уникальный индекс
            $table->unique(['post_type_id', 'slug', 'type'], 'unique_slug_type');
            $table->index('type', 'idx_type');
            $table->index(['post_type_id', 'is_default'], 'idx_default');
            $table->index('slug', 'idx_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
```

#### 1.1.2. Миграция `blueprint_components`

**Файл:** `database/migrations/2025_11_19_000020_create_blueprint_components_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_components', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->foreignId('component_id')
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->string('path_prefix', 100);
            $table->timestamps();

            $table->primary(['blueprint_id', 'component_id']);
            $table->index('component_id', 'idx_component');
        });

        // CHECK constraint для MySQL
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE blueprint_components
                ADD CONSTRAINT chk_path_prefix_not_empty
                CHECK (LENGTH(path_prefix) > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_components');
    }
};
```

#### 1.1.3. Миграция `paths`

**Файл:** `database/migrations/2025_11_19_000030_create_paths_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')
                ->constrained('blueprints')
                ->onDelete('cascade');

            // Для материализованных Paths
            $table->foreignId('source_component_id')
                ->nullable()
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->foreignId('source_path_id')
                ->nullable()
                ->constrained('paths')
                ->onDelete('cascade');

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('paths')
                ->onDelete('cascade');

            $table->string('name', 100);
            $table->string('full_path', 500);
            $table->enum('data_type', [
                'string', 'int', 'float', 'bool', 'text', 'json', 'ref'
            ]);
            $table->enum('cardinality', ['one', 'many'])->default('one');
            $table->boolean('is_indexed')->default(true);
            $table->boolean('is_required')->default(false);
            $table->string('ref_target_type', 100)->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('ui_options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blueprint_id', 'full_path'], 'unique_path_per_blueprint');
            $table->index(['blueprint_id', 'is_indexed'], 'idx_indexed');
            $table->index(['source_component_id', 'source_path_id'], 'idx_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paths');
    }
};
```

#### 1.1.4. Миграция добавления `blueprint_id` в `entries`

**Файл:** `database/migrations/2025_11_19_000040_add_blueprint_id_to_entries_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('post_type_id')
                ->constrained('blueprints')
                ->onDelete('set null');

            $table->index('blueprint_id', 'idx_blueprint');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropIndex('idx_blueprint');
            $table->dropColumn('blueprint_id');
        });
    }
};
```

#### 1.1.5. Миграция `doc_values`

**Файл:** `database/migrations/2025_11_19_000050_create_doc_values_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_values', function (Blueprint $table) {
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->onDelete('cascade');
            $table->foreignId('path_id')
                ->constrained('paths')
                ->onDelete('cascade');
            $table->unsignedInteger('idx')->default(0);

            $table->string('value_string', 500)->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->primary(['entry_id', 'path_id', 'idx']);
            $table->index(['entry_id', 'path_id'], 'idx_entry_path');
            $table->index(['path_id', 'value_string'], 'idx_path_string');
            $table->index(['path_id', 'value_int'], 'idx_path_int');
            $table->index(['path_id', 'value_float'], 'idx_path_float');
            $table->index(['path_id', 'value_bool'], 'idx_path_bool');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_values');
    }
};
```

#### 1.1.6. Миграция `doc_refs`

**Файл:** `database/migrations/2025_11_19_000060_create_doc_refs_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_refs', function (Blueprint $table) {
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->onDelete('cascade');
            $table->foreignId('path_id')
                ->constrained('paths')
                ->onDelete('cascade');
            $table->unsignedInteger('idx')->default(0);
            $table->foreignId('target_entry_id')
                ->constrained('entries')
                ->onDelete('cascade');

            $table->timestamp('created_at')->nullable();

            $table->primary(['entry_id', 'path_id', 'idx']);
            $table->index(['entry_id', 'path_id'], 'idx_entry_path');
            $table->index(['path_id', 'target_entry_id'], 'idx_path_target');
            $table->index('target_entry_id', 'idx_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_refs');
    }
};
```

**Проверка:**

```bash
php artisan migrate
php artisan migrate:status
```

---

### Задача 1.2: Базовые модели

**Файлы для создания:**

#### 1.2.1. Модель Blueprint

**Файл:** `app/Models/Blueprint.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Database\Factories\BlueprintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Модель Blueprint — схема полей для Entry.
 *
 * @property int $id
 * @property int|null $post_type_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $type 'full' или 'component'
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\PostType|null $postType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $paths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $ownPaths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $materializedPaths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Entry> $entries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $components
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Blueprint> $usedInBlueprints
 */
class Blueprint extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'post_type_id',
        'slug',
        'name',
        'description',
        'type',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Связи

    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class);
    }

    public function paths(): HasMany
    {
        return $this->hasMany(Path::class);
    }

    public function ownPaths(): HasMany
    {
        return $this->hasMany(Path::class)
            ->whereNull('source_component_id');
    }

    public function materializedPaths(): HasMany
    {
        return $this->hasMany(Path::class)
            ->whereNotNull('source_component_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            Blueprint::class,
            'blueprint_components',
            'blueprint_id',
            'component_id'
        )->withPivot('path_prefix')
         ->withTimestamps();
    }

    public function usedInBlueprints(): BelongsToMany
    {
        return $this->belongsToMany(
            Blueprint::class,
            'blueprint_components',
            'component_id',
            'blueprint_id'
        )->withPivot('path_prefix')
         ->withTimestamps();
    }

    // Скоупы

    public function scopeFull($query)
    {
        return $query->where('type', 'full');
    }

    public function scopeComponent($query)
    {
        return $query->where('type', 'component');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForPostType($query, int $postTypeId)
    {
        return $query->where('post_type_id', $postTypeId);
    }

    // Методы

    public function isComponent(): bool
    {
        return $this->type === 'component';
    }

    /**
     * Получить все Paths (собственные + материализованные).
     * Кешируется на 1 час.
     */
    public function getAllPaths(bool $cached = true): Collection
    {
        if (!$cached) {
            return $this->paths;
        }

        $cacheKey = "blueprint:{$this->id}:all_paths";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->paths()->get();
        });
    }

    /**
     * Найти Path по full_path.
     */
    public function getPathByFullPath(string $fullPath): ?Path
    {
        return $this->getAllPaths()->firstWhere('full_path', $fullPath);
    }

    /**
     * Материализовать Paths из компонента.
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function materializeComponentPaths(Blueprint $component, string $pathPrefix): void
    {
        if ($component->type !== 'component') {
            throw new \InvalidArgumentException('Можно материализовать только component Blueprint');
        }

        if (empty($pathPrefix)) {
            throw new \InvalidArgumentException('path_prefix обязателен');
        }

        $this->validateNoPathConflicts($component, $pathPrefix);

        DB::transaction(function () use ($component, $pathPrefix) {
            foreach ($component->ownPaths as $sourcePath) {
                if ($sourcePath->parent_id !== null) {
                    throw new \LogicException(
                        "Path '{$sourcePath->full_path}' в компоненте имеет parent_id. " .
                        "Вложенные Paths в компонентах не поддерживаются."
                    );
                }

                Path::create([
                    'blueprint_id' => $this->id,
                    'source_component_id' => $component->id,
                    'source_path_id' => $sourcePath->id,
                    'parent_id' => null,
                    'name' => $sourcePath->name,
                    'full_path' => $pathPrefix . '.' . $sourcePath->full_path,
                    'data_type' => $sourcePath->data_type,
                    'cardinality' => $sourcePath->cardinality,
                    'is_indexed' => $sourcePath->is_indexed,
                    'is_required' => $sourcePath->is_required,
                    'ref_target_type' => $sourcePath->ref_target_type,
                    'validation_rules' => $sourcePath->validation_rules,
                    'ui_options' => $sourcePath->ui_options,
                ]);
            }
        });

        $this->invalidatePathsCache();
    }

    /**
     * Удалить материализованные Paths компонента.
     */
    public function dematerializeComponentPaths(Blueprint $component): void
    {
        Path::where('blueprint_id', $this->id)
            ->where('source_component_id', $component->id)
            ->delete();

        $this->invalidatePathsCache();
    }

    /**
     * Инвалидировать кеш Paths.
     */
    public function invalidatePathsCache(): void
    {
        Cache::forget("blueprint:{$this->id}:all_paths");
    }

    /**
     * Проверить конфликты full_path.
     *
     * @throws \LogicException
     */
    private function validateNoPathConflicts(Blueprint $component, string $pathPrefix): void
    {
        $existingPaths = $this->paths()->pluck('full_path');

        foreach ($component->ownPaths as $sourcePath) {
            $newFullPath = $pathPrefix . '.' . $sourcePath->full_path;

            if ($existingPaths->contains($newFullPath)) {
                throw new \LogicException(
                    "Конфликт: Path '{$newFullPath}' уже существует в Blueprint '{$this->slug}'"
                );
            }
        }
    }

    protected static function newFactory(): BlueprintFactory
    {
        return BlueprintFactory::new();
    }
}
```

#### 1.2.2. Модель Path

**Файл:** `app/Models/Path.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\PathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Модель Path — метаданные поля в Blueprint.
 *
 * @property int $id
 * @property int $blueprint_id
 * @property int|null $source_component_id
 * @property int|null $source_path_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $full_path
 * @property string $data_type
 * @property string $cardinality
 * @property bool $is_indexed
 * @property bool $is_required
 * @property string|null $ref_target_type
 * @property array|null $validation_rules
 * @property array|null $ui_options
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Path|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $children
 */
class Path extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'blueprint_id',
        'source_component_id',
        'source_path_id',
        'parent_id',
        'name',
        'full_path',
        'data_type',
        'cardinality',
        'is_indexed',
        'is_required',
        'ref_target_type',
        'validation_rules',
        'ui_options',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'ui_options' => 'array',
        'is_indexed' => 'boolean',
        'is_required' => 'boolean',
    ];

    // Связи

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Path::class, 'parent_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(DocValue::class);
    }

    public function refs(): HasMany
    {
        return $this->hasMany(DocRef::class);
    }

    // Методы

    public function isRef(): bool
    {
        return $this->data_type === 'ref';
    }

    public function isMany(): bool
    {
        return $this->cardinality === 'many';
    }

    protected static function newFactory(): PathFactory
    {
        return PathFactory::new();
    }
}
```

#### 1.2.3. Модель DocValue

**Файл:** `app/Models/DocValue.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель DocValue — индексированное скалярное значение.
 *
 * @property int $entry_id
 * @property int $path_id
 * @property int $idx
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property string|null $value_text
 * @property array|null $value_json
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @property-read \App\Models\Entry $entry
 * @property-read \App\Models\Path $path
 */
class DocValue extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'path_id',
        'idx',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_text',
        'value_json',
    ];

    protected $casts = [
        'value_json' => 'array',
        'value_bool' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /**
     * Получить значение из нужного value_* поля.
     */
    public function getValue(): mixed
    {
        return match($this->path->data_type) {
            'string' => $this->value_string,
            'int' => $this->value_int,
            'float' => $this->value_float,
            'bool' => $this->value_bool,
            'text' => $this->value_text,
            'json' => $this->value_json,
            default => null,
        };
    }
}
```

#### 1.2.4. Модель DocRef

**Файл:** `app/Models/DocRef.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель DocRef — индексированная ссылка Entry → Entry.
 *
 * @property int $entry_id
 * @property int $path_id
 * @property int $idx
 * @property int $target_entry_id
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @property-read \App\Models\Entry $owner
 * @property-read \App\Models\Entry $target
 * @property-read \App\Models\Path $path
 */
class DocRef extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'path_id',
        'idx',
        'target_entry_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'target_entry_id');
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }
}
```

**Проверка:**

```bash
php artisan tinker
>>> App\Models\Blueprint::count()
>>> App\Models\Path::count()
```

---

### Задача 1.3: Фабрики и сидеры

#### 1.3.1. Фабрика Blueprint

**Файл:** `database/factories/BlueprintFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    public function definition(): array
    {
        return [
            'post_type_id' => PostType::factory(),
            'slug' => $this->faker->unique()->slug,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence,
            'type' => 'full',
            'is_default' => false,
        ];
    }

    public function component(): self
    {
        return $this->state(fn (array $attributes) => [
            'post_type_id' => null,
            'type' => 'component',
        ]);
    }

    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
```

#### 1.3.2. Фабрика Path

**Файл:** `database/factories/PathFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Path;
use App\Models\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class PathFactory extends Factory
{
    protected $model = Path::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word;

        return [
            'blueprint_id' => Blueprint::factory(),
            'name' => $name,
            'full_path' => $name,
            'data_type' => $this->faker->randomElement([
                'string', 'int', 'float', 'bool', 'text', 'json'
            ]),
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
            'ref_target_type' => null,
            'validation_rules' => null,
            'ui_options' => null,
        ];
    }

    public function ref(string $targetType = 'any'): self
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'ref',
            'ref_target_type' => $targetType,
        ]);
    }

    public function many(): self
    {
        return $this->state(fn (array $attributes) => [
            'cardinality' => 'many',
        ]);
    }
}
```

#### 1.3.3. Сидер для тестовых данных

**Файл:** `database/seeders/BlueprintSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Database\Seeder;

class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        // Компонент: SEO Fields
        $seoComponent = Blueprint::create([
            'slug' => 'seo_fields',
            'name' => 'SEO Fields',
            'description' => 'SEO метаданные',
            'type' => 'component',
            'post_type_id' => null,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaTitle',
            'full_path' => 'metaTitle',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaDescription',
            'full_path' => 'metaDescription',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => false,
            'is_required' => false,
        ]);

        // Компонент: Author Info
        $authorComponent = Blueprint::create([
            'slug' => 'author_info',
            'name' => 'Author Info',
            'description' => 'Информация об авторе',
            'type' => 'component',
            'post_type_id' => null,
        ]);

        Path::create([
            'blueprint_id' => $authorComponent->id,
            'name' => 'name',
            'full_path' => 'name',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
        ]);

        // Full Blueprint: Article
        $articlePostType = PostType::where('slug', 'article')->first();
        if ($articlePostType) {
            $articleBlueprint = Blueprint::create([
                'post_type_id' => $articlePostType->id,
                'slug' => 'article_full',
                'name' => 'Article Full',
                'description' => 'Полная схема статьи',
                'type' => 'full',
                'is_default' => true,
            ]);

            Path::create([
                'blueprint_id' => $articleBlueprint->id,
                'name' => 'content',
                'full_path' => 'content',
                'data_type' => 'text',
                'cardinality' => 'one',
                'is_indexed' => false,
            ]);

            Path::create([
                'blueprint_id' => $articleBlueprint->id,
                'name' => 'relatedArticles',
                'full_path' => 'relatedArticles',
                'data_type' => 'ref',
                'cardinality' => 'many',
                'is_indexed' => true,
                'ref_target_type' => 'article',
            ]);

            // Attach компонентов
            $articleBlueprint->components()->attach([
                $seoComponent->id => ['path_prefix' => 'seo'],
                $authorComponent->id => ['path_prefix' => 'customAuthor'],
            ]);
        }
    }
}
```

**Запуск:**

```bash
php artisan db:seed --class=BlueprintSeeder
```

---

**✅ МОДУЛЬ 1 ЗАВЕРШЁН**

**Проверка:**

```bash
# Запуск миграций
php artisan migrate

# Проверка таблиц
php artisan tinker
>>> Schema::hasTable('blueprints')
>>> Schema::hasTable('paths')
>>> Schema::hasTable('doc_values')

# Запуск сидера
php artisan db:seed --class=BlueprintSeeder

# Проверка данных
>>> App\Models\Blueprint::count()
>>> App\Models\Path::count()
```

---

## МОДУЛЬ 2: Материализация компонентов

**Цель:** Реализовать автоматическую материализацию Paths при attach/detach компонентов.

**Зависимости:** МОДУЛЬ 1.

**Время:** 2-3 дня.

---

### Задача 2.1: Логика материализации в Blueprint

**Уже реализовано в Задаче 1.2.1:**

-   `Blueprint::materializeComponentPaths()`
-   `Blueprint::dematerializeComponentPaths()`
-   `Blueprint::validateNoPathConflicts()`

**Проверка:**

```bash
php artisan tinker
>>> $blueprint = App\Models\Blueprint::full()->first()
>>> $component = App\Models\Blueprint::component()->first()
>>> $blueprint->materializeComponentPaths($component, 'test')
>>> $blueprint->paths()->where('source_component_id', $component->id)->count()
```

---

### Задача 2.2: Observers для синхронизации

#### 2.2.1. BlueprintObserver

**Файл:** `app/Observers/BlueprintObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Blueprint;

class BlueprintObserver
{
    /**
     * Не используется напрямую.
     * Материализация вызывается вручную в контроллере.
     */
}
```

**Примечание:** Материализация триггерится не через Observer, а через явные вызовы в контроллере при attach/detach. Observer'ы понадобятся для Path.

#### 2.2.2. PathObserver

**Файл:** `app/Observers/PathObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Path;

class PathObserver
{
    /**
     * При изменении Path в компоненте — синхронизировать материализованные копии.
     */
    public function updated(Path $sourcePath): void
    {
        if ($sourcePath->source_component_id === null && $sourcePath->blueprint->isComponent()) {
            $this->syncMaterializedPaths($sourcePath);
        }
    }

    /**
     * При удалении Path — удалить материализованные копии.
     */
    public function deleted(Path $sourcePath): void
    {
        if ($sourcePath->source_component_id === null && $sourcePath->blueprint->isComponent()) {
            Path::where('source_path_id', $sourcePath->id)->delete();
        }
    }

    private function syncMaterializedPaths(Path $sourcePath): void
    {
        $materializedPaths = Path::where('source_path_id', $sourcePath->id)->get();
        $affectedBlueprintIds = [];

        foreach ($materializedPaths as $matPath) {
            $pathPrefix = $matPath->blueprint->components()
                ->where('component_id', $sourcePath->blueprint_id)
                ->first()
                ?->pivot
                ->path_prefix;

            $updates = [
                'data_type' => $sourcePath->data_type,
                'cardinality' => $sourcePath->cardinality,
                'is_indexed' => $sourcePath->is_indexed,
                'is_required' => $sourcePath->is_required,
                'ref_target_type' => $sourcePath->ref_target_type,
                'validation_rules' => $sourcePath->validation_rules,
                'ui_options' => $sourcePath->ui_options,
            ];

            if ($sourcePath->wasChanged('name') || $sourcePath->wasChanged('full_path')) {
                $updates['name'] = $sourcePath->name;
                $updates['full_path'] = $pathPrefix . '.' . $sourcePath->full_path;
            }

            $matPath->update($updates);
            $matPath->blueprint->invalidatePathsCache();
            $matPath->blueprint->touch();

            $affectedBlueprintIds[] = $matPath->blueprint_id;
        }

        if ($this->requiresReindexing($sourcePath)) {
            $uniqueBlueprintIds = array_unique($affectedBlueprintIds);

            foreach ($uniqueBlueprintIds as $blueprintId) {
                dispatch(new ReindexBlueprintEntries($blueprintId));
            }
        }
    }

    private function requiresReindexing(Path $sourcePath): bool
    {
        return $sourcePath->wasChanged([
            'data_type',
            'cardinality',
            'is_indexed',
            'full_path',
        ]);
    }
}
```

**Регистрация Observer:**

**Файл:** `app/Providers/AppServiceProvider.php`

```php
use App\Models\Path;
use App\Observers\PathObserver;

public function boot(): void
{
    Path::observe(PathObserver::class);
}
```

---

### Задача 2.3: Job для реиндексации

**Файл:** `app/Jobs/ReindexBlueprintEntries.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Blueprint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReindexBlueprintEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $blueprintId
    ) {}

    public function handle(): void
    {
        $blueprint = Blueprint::find($this->blueprintId);

        if (!$blueprint) {
            Log::warning("Blueprint {$this->blueprintId} not found for reindexing");
            return;
        }

        $count = 0;

        $blueprint->entries()->chunk(100, function ($entries) use (&$count) {
            foreach ($entries as $entry) {
                $entry->syncDocumentIndex();
                $count++;
            }
        });

        Log::info("Reindexed {$count} entries for Blueprint {$blueprint->slug}");
    }
}
```

**Проверка:**

```bash
php artisan tinker
>>> dispatch(new App\Jobs\ReindexBlueprintEntries(1))
>>> php artisan queue:work --once
```

---

**✅ МОДУЛЬ 2 ЗАВЕРШЁН**

---

## МОДУЛЬ 3: Индексация документов

**Цель:** Реализовать трейт HasDocumentData для автоматической индексации data_json.

**Зависимости:** МОДУЛЬ 1, МОДУЛЬ 2.

**Время:** 3-4 дня.

---

### Задача 3.1: Трейт HasDocumentData

**Файл:** `app/Traits/HasDocumentData.php`

_(Код слишком длинный, см. в v2_fixed документе, строки 759-830)_

**Ключевые методы:**

-   `bootHasDocumentData()` — хук saved
-   `syncDocumentIndex()` — основная логика
-   `syncScalarPath()` — индексация скаляров
-   `syncRefPath()` — индексация ссылок
-   `getValueFieldForType()` — маппинг data*type → value*\*

---

### Задача 3.2: Скоупы для запросов

В трейте `HasDocumentData`:

-   `scopeWherePath()` — фильтрация по скалярам
-   `scopeWherePathTyped()` — с явным указанием типа
-   `scopeWhereRef()` — фильтрация по ссылкам

---

### Задача 3.3: Подключение к Entry

**Файл:** `app/Models/Entry.php`

```php
use App\Traits\HasDocumentData;

class Entry extends Model
{
    use HasFactory, SoftDeletes, HasDocumentData;

    // ... existing code ...

    // Добавить связи:
    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function values()
    {
        return $this->hasMany(DocValue::class, 'entry_id');
    }

    public function refs()
    {
        return $this->hasMany(DocRef::class, 'entry_id');
    }
}
```

**Проверка:**

```bash
php artisan tinker
>>> $entry = App\Models\Entry::factory()->create([
...     'blueprint_id' => 1,
...     'data_json' => ['content' => 'Test', 'seo' => ['metaTitle' => 'Hello']]
... ])
>>> $entry->values()->count()
>>> $entry->values()->where('value_string', 'Hello')->exists()
```

---

**✅ МОДУЛЬ 3 ЗАВЕРШЁН**

---

## МОДУЛЬ 4: Валидация и безопасность

**Цель:** Создать Request классы для валидации данных на уровне API.

**Зависимости:** МОДУЛЬ 1.

**Время:** 2-3 дня.

---

### Задача 4.1: Request классы

#### 4.1.1. StoreBlueprintRequest

**Файл:** `app/Http/Requests/StoreBlueprintRequest.php`

_(См. код в v2_fixed, строки 969-1036)_

**Ключевые валидации:**

-   Уникальность slug с учётом type (MySQL workaround)
-   post_type_id обязателен для type=full
-   post_type_id=null для type=component

#### 4.1.2. StorePathRequest

**Файл:** `app/Http/Requests/StorePathRequest.php`

_(См. код в v2_fixed, строки 1044-1102)_

**Ключевые валидации:**

-   Запрет parent_id в компонентах
-   ref_target_type обязателен для data_type=ref
-   Уникальность full_path в рамках Blueprint

#### 4.1.3. AttachComponentRequest

**Файл:** `app/Http/Requests/AttachComponentRequest.php`

_(См. код в v2_fixed, строки 1110-1179)_

**Ключевые валидации:**

-   Только type=component
-   Запрет self
-   Проверка циклов
-   Проверка конфликтов Path

---

### Задача 4.2: Валидация циклов

Уже реализовано в AttachComponentRequest (упрощённая версия).

**Расширенная версия (опционально):**

**Файл:** `app/Services/BlueprintCycleDetector.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use Illuminate\Support\Collection;

class BlueprintCycleDetector
{
    /**
     * Проверить наличие цикла при добавлении компонента.
     *
     * @throws \LogicException
     */
    public function detectCycle(Blueprint $blueprint, Blueprint $component): void
    {
        if ($this->hasCycle($blueprint, $component, new Collection())) {
            throw new \LogicException(
                "Обнаружен цикл в композиции Blueprint: " .
                "{$blueprint->slug} → {$component->slug}"
            );
        }
    }

    /**
     * Рекурсивная проверка цикла.
     */
    private function hasCycle(
        Blueprint $blueprint,
        Blueprint $component,
        Collection $visited
    ): bool {
        // Если component уже использует blueprint — цикл
        if ($component->components()->where('component_id', $blueprint->id)->exists()) {
            return true;
        }

        // Отмечаем component как посещённый
        $visited->push($component->id);

        // Проверяем транзитивные зависимости
        foreach ($component->components as $nestedComponent) {
            if ($visited->contains($nestedComponent->id)) {
                continue;
            }

            if ($this->hasCycle($blueprint, $nestedComponent, $visited->copy())) {
                return true;
            }
        }

        return false;
    }
}
```

**Использование в AttachComponentRequest:**

```php
public function rules(): array
{
    return [
        'component_id' => [
            // ... existing rules ...
            function ($attribute, $value, $fail) {
                $blueprint = $this->route('blueprint');
                $component = Blueprint::find($value);

                if (!$component) {
                    return;
                }

                try {
                    app(BlueprintCycleDetector::class)->detectCycle($blueprint, $component);
                } catch (\LogicException $e) {
                    $fail($e->getMessage());
                }
            },
        ],
    ];
}
```

---

### Задача 4.3: Проверка конфликтов

Уже реализовано в:

-   `Blueprint::validateNoPathConflicts()` (Модуль 1)
-   `AttachComponentRequest` проверка конфликтов (Модуль 4)

---

**✅ МОДУЛЬ 4 ЗАВЕРШЁН**

---

## МОДУЛЬ 5: API контроллеры

**Цель:** Создать CRUD контроллеры для управления Blueprint, Path и компонентами.

**Зависимости:** МОДУЛЬ 1, МОДУЛЬ 4.

**Время:** 4-5 дней.

---

### Задача 5.1: CRUD Blueprint

**Файл:** `app/Http/Controllers/Admin/BlueprintController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlueprintRequest;
use App\Http\Resources\BlueprintResource;
use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API для управления Blueprint.
 *
 * @group Blueprint Management
 */
class BlueprintController extends Controller
{
    /**
     * Список Blueprint.
     */
    public function index(Request $request)
    {
        $query = Blueprint::with('postType');

        if ($request->has('post_type_id')) {
            $query->where('post_type_id', $request->input('post_type_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $blueprints = $query->paginate(20);

        return BlueprintResource::collection($blueprints);
    }

    /**
     * Показать Blueprint.
     */
    public function show(Blueprint $blueprint)
    {
        $blueprint->load(['postType', 'paths', 'components']);

        return new BlueprintResource($blueprint);
    }

    /**
     * Создать Blueprint.
     */
    public function store(StoreBlueprintRequest $request)
    {
        $blueprint = Blueprint::create($request->validated());

        return new BlueprintResource($blueprint);
    }

    /**
     * Обновить Blueprint.
     */
    public function update(StoreBlueprintRequest $request, Blueprint $blueprint)
    {
        $blueprint->update($request->validated());

        return new BlueprintResource($blueprint);
    }

    /**
     * Удалить Blueprint.
     */
    public function destroy(Blueprint $blueprint): JsonResponse
    {
        // Проверка: нельзя удалить Blueprint с entries
        if ($blueprint->entries()->exists()) {
            return response()->json([
                'message' => 'Cannot delete Blueprint with existing entries',
                'entries_count' => $blueprint->entries()->count(),
            ], 422);
        }

        $blueprint->delete();

        return response()->json(['message' => 'Blueprint deleted'], 200);
    }
}
```

---

### Задача 5.2: CRUD Path

**Файл:** `app/Http/Controllers/Admin/PathController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePathRequest;
use App\Http\Resources\PathResource;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Http\Request;

/**
 * API для управления Path.
 *
 * @group Path Management
 */
class PathController extends Controller
{
    /**
     * Список Paths Blueprint.
     */
    public function index(Request $request, Blueprint $blueprint)
    {
        $query = $blueprint->paths()->with('blueprint');

        if ($request->boolean('own_only')) {
            $query->whereNull('source_component_id');
        }

        return PathResource::collection($query->get());
    }

    /**
     * Показать Path.
     */
    public function show(Blueprint $blueprint, Path $path)
    {
        return new PathResource($path);
    }

    /**
     * Создать Path.
     */
    public function store(StorePathRequest $request, Blueprint $blueprint)
    {
        $path = Path::create($request->validated());

        // Инвалидация кеша
        $blueprint->invalidatePathsCache();

        return new PathResource($path);
    }

    /**
     * Обновить Path.
     */
    public function update(StorePathRequest $request, Blueprint $blueprint, Path $path)
    {
        $path->update($request->validated());

        $blueprint->invalidatePathsCache();

        return new PathResource($path);
    }

    /**
     * Удалить Path.
     */
    public function destroy(Blueprint $blueprint, Path $path)
    {
        // PathObserver автоматически удалит материализованные копии
        $path->delete();

        $blueprint->invalidatePathsCache();

        return response()->json(['message' => 'Path deleted'], 200);
    }
}
```

---

### Задача 5.3: Управление компонентами

**Файл:** `app/Http/Controllers/Admin/BlueprintComponentController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachComponentRequest;
use App\Http\Resources\BlueprintResource;
use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use Illuminate\Http\JsonResponse;

/**
 * API для управления компонентами Blueprint.
 *
 * @group Blueprint Components
 */
class BlueprintComponentController extends Controller
{
    /**
     * Добавить компонент к Blueprint.
     */
    public function attach(AttachComponentRequest $request, Blueprint $blueprint): JsonResponse
    {
        $componentId = $request->input('component_id');
        $pathPrefix = $request->input('path_prefix');

        $component = Blueprint::findOrFail($componentId);

        // Материализация Paths
        $blueprint->materializeComponentPaths($component, $pathPrefix);

        // Attach компонента
        $blueprint->components()->attach($componentId, [
            'path_prefix' => $pathPrefix,
        ]);

        // Реиндексация существующих entries
        dispatch(new ReindexBlueprintEntries($blueprint->id));

        return response()->json([
            'message' => 'Component attached successfully',
            'blueprint' => new BlueprintResource($blueprint->fresh('components')),
        ], 200);
    }

    /**
     * Удалить компонент из Blueprint.
     */
    public function detach(Blueprint $blueprint, Blueprint $component): JsonResponse
    {
        // Дематериализация Paths
        $blueprint->dematerializeComponentPaths($component);

        // Detach компонента
        $blueprint->components()->detach($component->id);

        // Реиндексация entries
        dispatch(new ReindexBlueprintEntries($blueprint->id));

        return response()->json([
            'message' => 'Component detached successfully',
        ], 200);
    }

    /**
     * Список компонентов Blueprint.
     */
    public function index(Blueprint $blueprint)
    {
        return BlueprintResource::collection(
            $blueprint->components()->get()
        );
    }
}
```

---

### Задача 5.4: API Resources

#### 5.4.1. BlueprintResource

**Файл:** `app/Http/Resources/BlueprintResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlueprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_type_id' => $this->post_type_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Связи
            'post_type' => $this->whenLoaded('postType', function () {
                return [
                    'id' => $this->postType->id,
                    'slug' => $this->postType->slug,
                    'name' => $this->postType->name,
                ];
            }),

            'paths' => PathResource::collection($this->whenLoaded('paths')),
            'components' => BlueprintResource::collection($this->whenLoaded('components')),

            // Статистика
            'entries_count' => $this->when(
                isset($this->entries_count),
                $this->entries_count
            ),
        ];
    }
}
```

#### 5.4.2. PathResource

**Файл:** `app/Http/Resources/PathResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'source_component_id' => $this->source_component_id,
            'source_path_id' => $this->source_path_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'full_path' => $this->full_path,
            'data_type' => $this->data_type,
            'cardinality' => $this->cardinality,
            'is_indexed' => $this->is_indexed,
            'is_required' => $this->is_required,
            'ref_target_type' => $this->ref_target_type,
            'validation_rules' => $this->validation_rules,
            'ui_options' => $this->ui_options,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Флаги
            'is_materialized' => $this->source_component_id !== null,
            'is_ref' => $this->isRef(),
            'is_many' => $this->isMany(),
        ];
    }
}
```

---

### Задача 5.5: Роуты

**Файл:** `routes/api_admin.php`

```php
use App\Http\Controllers\Admin\BlueprintController;
use App\Http\Controllers\Admin\PathController;
use App\Http\Controllers\Admin\BlueprintComponentController;

// Blueprint
Route::apiResource('blueprints', BlueprintController::class);

// Paths в контексте Blueprint
Route::prefix('blueprints/{blueprint}')->group(function () {
    Route::apiResource('paths', PathController::class);

    // Компоненты
    Route::post('components', [BlueprintComponentController::class, 'attach']);
    Route::get('components', [BlueprintComponentController::class, 'index']);
    Route::delete('components/{component}', [BlueprintComponentController::class, 'detach']);
});
```

---

**✅ МОДУЛЬ 5 ЗАВЕРШЁН**

---

## МОДУЛЬ 6: Команды и утилиты

**Цель:** Создать Artisan команды для управления системой.

**Зависимости:** МОДУЛЬ 1, МОДУЛЬ 2, МОДУЛЬ 3.

**Время:** 2-3 дня.

---

### Задача 6.1: Команда реиндексации

**Файл:** `app/Console/Commands/ReindexEntriesCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Console\Command;

class ReindexEntriesCommand extends Command
{
    protected $signature = 'entries:reindex
                            {--post-type= : Slug PostType}
                            {--blueprint= : Slug Blueprint}
                            {--queue : Use queue for async processing}';

    protected $description = 'Reindex entries doc_values and doc_refs';

    public function handle(): int
    {
        $postTypeSlug = $this->option('post-type');
        $blueprintSlug = $this->option('blueprint');
        $useQueue = $this->option('queue');

        $query = Entry::whereNotNull('blueprint_id');

        if ($postTypeSlug) {
            $postType = PostType::where('slug', $postTypeSlug)->firstOrFail();
            $query->where('post_type_id', $postType->id);
            $this->info("Filtering by PostType: {$postTypeSlug}");
        }

        if ($blueprintSlug) {
            $blueprint = Blueprint::where('slug', $blueprintSlug)->firstOrFail();
            $query->where('blueprint_id', $blueprint->id);
            $this->info("Filtering by Blueprint: {$blueprintSlug}");
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No entries to reindex');
            return 0;
        }

        $this->info("Found {$total} entries to reindex");

        if ($useQueue) {
            // Group by blueprint_id and dispatch jobs
            $blueprintIds = $query->distinct('blueprint_id')->pluck('blueprint_id');

            foreach ($blueprintIds as $blueprintId) {
                dispatch(new ReindexBlueprintEntries($blueprintId));
                $this->info("Dispatched job for Blueprint ID: {$blueprintId}");
            }

            $this->info('Jobs dispatched successfully');
        } else {
            // Synchronous processing
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById(100, function ($entries) use ($bar) {
                foreach ($entries as $entry) {
                    $entry->syncDocumentIndex();
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info('Reindexing completed');
        }

        return 0;
    }
}
```

**Использование:**

```bash
# Все Entry
php artisan entries:reindex

# Конкретный PostType
php artisan entries:reindex --post-type=article

# Конкретный Blueprint
php artisan entries:reindex --blueprint=article_full

# Асинхронно через очередь
php artisan entries:reindex --queue
```

---

### Задача 6.2: Экспорт/импорт Blueprint

**Файл:** `app/Console/Commands/ExportBlueprintCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportBlueprintCommand extends Command
{
    protected $signature = 'blueprint:export
                            {slug : Blueprint slug}
                            {--output= : Output file path}';

    protected $description = 'Export Blueprint schema to JSON';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $blueprint = Blueprint::where('slug', $slug)
            ->with(['paths', 'components.paths'])
            ->firstOrFail();

        $export = [
            'slug' => $blueprint->slug,
            'name' => $blueprint->name,
            'description' => $blueprint->description,
            'type' => $blueprint->type,
            'paths' => $blueprint->ownPaths->map(fn($path) => [
                'name' => $path->name,
                'full_path' => $path->full_path,
                'data_type' => $path->data_type,
                'cardinality' => $path->cardinality,
                'is_indexed' => $path->is_indexed,
                'is_required' => $path->is_required,
                'ref_target_type' => $path->ref_target_type,
                'validation_rules' => $path->validation_rules,
                'ui_options' => $path->ui_options,
            ])->toArray(),
            'components' => $blueprint->components->map(fn($component) => [
                'slug' => $component->slug,
                'path_prefix' => $component->pivot->path_prefix,
            ])->toArray(),
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $outputPath = $this->option('output') ?? storage_path("blueprints/{$slug}.json");

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $json);

        $this->info("Blueprint exported to: {$outputPath}");

        return 0;
    }
}
```

**Файл:** `app/Console/Commands/ImportBlueprintCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportBlueprintCommand extends Command
{
    protected $signature = 'blueprint:import
                            {file : JSON file path}
                            {--post-type= : PostType slug (for full blueprints)}
                            {--force : Overwrite existing blueprint}';

    protected $description = 'Import Blueprint schema from JSON';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $data = json_decode(File::get($filePath), true);

        if (!$data) {
            $this->error('Invalid JSON file');
            return 1;
        }

        // Проверка существования
        $existing = Blueprint::where('slug', $data['slug'])->first();

        if ($existing && !$this->option('force')) {
            $this->error("Blueprint '{$data['slug']}' already exists. Use --force to overwrite.");
            return 1;
        }

        DB::transaction(function () use ($data, $existing) {
            if ($existing) {
                $existing->paths()->delete();
                $existing->components()->detach();
                $blueprint = $existing;
                $blueprint->update([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            } else {
                $postTypeId = null;
                if ($data['type'] === 'full') {
                    $postTypeSlug = $this->option('post-type');
                    if (!$postTypeSlug) {
                        throw new \InvalidArgumentException('--post-type required for full blueprints');
                    }
                    $postType = PostType::where('slug', $postTypeSlug)->firstOrFail();
                    $postTypeId = $postType->id;
                }

                $blueprint = Blueprint::create([
                    'post_type_id' => $postTypeId,
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'type' => $data['type'],
                ]);
            }

            // Create paths
            foreach ($data['paths'] as $pathData) {
                Path::create([
                    'blueprint_id' => $blueprint->id,
                    ...$pathData,
                ]);
            }

            // Attach components
            foreach ($data['components'] ?? [] as $componentData) {
                $component = Blueprint::where('slug', $componentData['slug'])->first();
                if ($component) {
                    $blueprint->materializeComponentPaths($component, $componentData['path_prefix']);
                    $blueprint->components()->attach($component->id, [
                        'path_prefix' => $componentData['path_prefix'],
                    ]);
                }
            }
        });

        $this->info("Blueprint '{$data['slug']}' imported successfully");

        return 0;
    }
}
```

---

### Задача 6.3: Диагностика схемы

**Файл:** `app/Console/Commands/DiagnoseBlueprintCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;

class DiagnoseBlueprintCommand extends Command
{
    protected $signature = 'blueprint:diagnose {slug : Blueprint slug}';

    protected $description = 'Diagnose Blueprint schema and show statistics';

    public function handle(): int
    {
        $blueprint = Blueprint::where('slug', $this->argument('slug'))
            ->with(['paths', 'components', 'entries'])
            ->firstOrFail();

        $this->info("Blueprint: {$blueprint->name} ({$blueprint->slug})");
        $this->info("Type: {$blueprint->type}");
        $this->newLine();

        // Paths
        $ownPaths = $blueprint->ownPaths;
        $materializedPaths = $blueprint->materializedPaths;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Own Paths', $ownPaths->count()],
                ['Materialized Paths', $materializedPaths->count()],
                ['Total Paths', $blueprint->paths->count()],
                ['Indexed Paths', $blueprint->paths->where('is_indexed', true)->count()],
                ['Required Paths', $blueprint->paths->where('is_required', true)->count()],
                ['Ref Paths', $blueprint->paths->where('data_type', 'ref')->count()],
                ['Components', $blueprint->components->count()],
                ['Entries', $blueprint->entries->count()],
            ]
        );

        $this->newLine();
        $this->info('Paths by data_type:');
        $byType = $blueprint->paths->groupBy('data_type');
        foreach ($byType as $type => $paths) {
            $this->line("  {$type}: {$paths->count()}");
        }

        return 0;
    }
}
```

---

**✅ МОДУЛЬ 6 ЗАВЕРШЁН**

---

## МОДУЛЬ 7: Тестирование

**Цель:** Написать полное покрытие тестами (unit, feature, integration).

**Зависимости:** МОДУЛЬ 1-6.

**Время:** 5-7 дней.

---

### Задача 7.1: Unit тесты

#### 7.1.1. Тесты модели Blueprint

**Файл:** `tests/Unit/Models/BlueprintTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_full_blueprint(): void
    {
        $blueprint = Blueprint::factory()->create(['type' => 'full']);

        expect($blueprint->type)->toBe('full');
        expect($blueprint->isComponent())->toBeFalse();
    }

    public function test_can_create_component_blueprint(): void
    {
        $blueprint = Blueprint::factory()->component()->create();

        expect($blueprint->type)->toBe('component');
        expect($blueprint->isComponent())->toBeTrue();
    }

    public function test_can_get_all_paths_with_cache(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->count(3)->create(['blueprint_id' => $blueprint->id]);

        $paths = $blueprint->getAllPaths();

        expect($paths)->toHaveCount(3);

        // Проверка кеширования
        Path::factory()->create(['blueprint_id' => $blueprint->id]);
        $cachedPaths = $blueprint->getAllPaths();

        expect($cachedPaths)->toHaveCount(3); // Кеш не обновлён
    }

    public function test_can_get_path_by_full_path(): void
    {
        $blueprint = Blueprint::factory()->create();
        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'test.field',
        ]);

        $found = $blueprint->getPathByFullPath('test.field');

        expect($found->id)->toBe($path->id);
    }

    public function test_cache_invalidates_correctly(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->count(2)->create(['blueprint_id' => $blueprint->id]);

        $paths = $blueprint->getAllPaths();
        expect($paths)->toHaveCount(2);

        $blueprint->invalidatePathsCache();
        Path::factory()->create(['blueprint_id' => $blueprint->id]);

        $freshPaths = $blueprint->getAllPaths();
        expect($freshPaths)->toHaveCount(3); // Кеш обновлён
    }
}
```

#### 7.1.2. Тесты материализации

**Файл:** `tests/Unit/Models/BlueprintMaterializationTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintMaterializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_materialize_component_paths(): void
    {
        $component = Blueprint::factory()->component()->create();
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'name' => 'title',
            'full_path' => 'title',
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');

        $materializedPath = $blueprint->paths()->where('source_component_id', $component->id)->first();

        expect($materializedPath)->not->toBeNull();
        expect($materializedPath->full_path)->toBe('seo.title');
        expect($materializedPath->name)->toBe('title');
    }

    public function test_cannot_materialize_non_component(): void
    {
        $blueprint = Blueprint::factory()->create(['type' => 'full']);
        $notComponent = Blueprint::factory()->create(['type' => 'full']);

        expect(fn () => $blueprint->materializeComponentPaths($notComponent, 'test'))
            ->toThrow(\InvalidArgumentException::class);
    }

    public function test_cannot_materialize_with_empty_prefix(): void
    {
        $blueprint = Blueprint::factory()->create();
        $component = Blueprint::factory()->component()->create();

        expect(fn () => $blueprint->materializeComponentPaths($component, ''))
            ->toThrow(\InvalidArgumentException::class);
    }

    public function test_cannot_materialize_component_with_parent_id(): void
    {
        $component = Blueprint::factory()->component()->create();
        $parentPath = Path::factory()->create(['blueprint_id' => $component->id]);
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'parent_id' => $parentPath->id,
        ]);

        $blueprint = Blueprint::factory()->create();

        expect(fn () => $blueprint->materializeComponentPaths($component, 'test'))
            ->toThrow(\LogicException::class);
    }

    public function test_detects_path_conflicts(): void
    {
        $component = Blueprint::factory()->component()->create();
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
        ]);

        $blueprint = Blueprint::factory()->create();
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'seo.title',
        ]);

        expect(fn () => $blueprint->materializeComponentPaths($component, 'seo'))
            ->toThrow(\LogicException::class);
    }

    public function test_can_dematerialize_component_paths(): void
    {
        $component = Blueprint::factory()->component()->create();
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');

        expect($blueprint->materializedPaths)->toHaveCount(1);

        $blueprint->dematerializeComponentPaths($component);

        expect($blueprint->fresh()->materializedPaths)->toHaveCount(0);
    }
}
```

#### 7.1.3. Тесты HasDocumentData

**Файл:** `tests/Unit/Traits/HasDocumentDataTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasDocumentDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_scalar_values_on_save(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'title',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['title' => 'Test Title'],
        ]);

        expect($entry->values)->toHaveCount(1);
        expect($entry->values->first()->value_string)->toBe('Test Title');
    }

    public function test_syncs_ref_values_on_save(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'relatedPost',
            'data_type' => 'ref',
            'cardinality' => 'one',
            'is_indexed' => true,
        ]);

        $targetEntry = Entry::factory()->create(['blueprint_id' => $blueprint->id]);
        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['relatedPost' => $targetEntry->id],
        ]);

        expect($entry->refs)->toHaveCount(1);
        expect($entry->refs->first()->target_entry_id)->toBe($targetEntry->id);
    }

    public function test_syncs_many_cardinality_values(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'tags',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
        ]);

        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['tags' => ['php', 'laravel', 'testing']],
        ]);

        expect($entry->values)->toHaveCount(3);
        expect($entry->values->pluck('value_string')->toArray())
            ->toBe(['php', 'laravel', 'testing']);
    }

    public function test_deletes_old_values_before_reindex(): void
    {
        $blueprint = Blueprint::factory()->create();
        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'title',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['title' => 'Old Title'],
        ]);

        expect($entry->values)->toHaveCount(1);

        $entry->update(['data_json' => ['title' => 'New Title']]);

        expect($entry->fresh()->values)->toHaveCount(1);
        expect($entry->fresh()->values->first()->value_string)->toBe('New Title');
    }
}
```

---

### Задача 7.2: Feature тесты

#### 7.2.1. Тесты API Blueprint

**Файл:** `tests/Feature/Api/BlueprintApiTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Blueprint;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_blueprints(): void
    {
        Blueprint::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/blueprints');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_blueprints_by_type(): void
    {
        Blueprint::factory()->count(2)->create(['type' => 'full']);
        Blueprint::factory()->component()->create();

        $response = $this->getJson('/api/admin/blueprints?type=component');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_full_blueprint(): void
    {
        $postType = PostType::factory()->create();

        $response = $this->postJson('/api/admin/blueprints', [
            'post_type_id' => $postType->id,
            'slug' => 'article-full',
            'name' => 'Article Full',
            'type' => 'full',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'article-full');

        $this->assertDatabaseHas('blueprints', [
            'slug' => 'article-full',
            'type' => 'full',
        ]);
    }

    public function test_can_create_component_blueprint(): void
    {
        $response = $this->postJson('/api/admin/blueprints', [
            'slug' => 'seo-fields',
            'name' => 'SEO Fields',
            'type' => 'component',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('blueprints', [
            'slug' => 'seo-fields',
            'type' => 'component',
            'post_type_id' => null,
        ]);
    }

    public function test_validates_unique_slug_per_type(): void
    {
        $postType = PostType::factory()->create();
        Blueprint::factory()->create([
            'post_type_id' => $postType->id,
            'slug' => 'duplicate',
            'type' => 'full',
        ]);

        $response = $this->postJson('/api/admin/blueprints', [
            'post_type_id' => $postType->id,
            'slug' => 'duplicate',
            'name' => 'Duplicate',
            'type' => 'full',
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_update_blueprint(): void
    {
        $blueprint = Blueprint::factory()->create();

        $response = $this->putJson("/api/admin/blueprints/{$blueprint->id}", [
            'slug' => $blueprint->slug,
            'name' => 'Updated Name',
            'type' => $blueprint->type,
            'post_type_id' => $blueprint->post_type_id,
        ]);

        $response->assertOk();
        expect($blueprint->fresh()->name)->toBe('Updated Name');
    }

    public function test_cannot_delete_blueprint_with_entries(): void
    {
        $blueprint = Blueprint::factory()->hasEntries(1)->create();

        $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('blueprints', ['id' => $blueprint->id]);
    }

    public function test_can_delete_blueprint_without_entries(): void
    {
        $blueprint = Blueprint::factory()->create();

        $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}");

        $response->assertOk();
        $this->assertSoftDeleted('blueprints', ['id' => $blueprint->id]);
    }
}
```

#### 7.2.2. Тесты API Components

**Файл:** `tests/Feature/Api/BlueprintComponentApiTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BlueprintComponentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_attach_component(): void
    {
        Queue::fake();

        $blueprint = Blueprint::factory()->create();
        $component = Blueprint::factory()->component()->create();
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
        ]);

        $response = $this->postJson("/api/admin/blueprints/{$blueprint->id}/components", [
            'component_id' => $component->id,
            'path_prefix' => 'seo',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('blueprint_components', [
            'blueprint_id' => $blueprint->id,
            'component_id' => $component->id,
            'path_prefix' => 'seo',
        ]);

        // Проверка материализации
        $this->assertDatabaseHas('paths', [
            'blueprint_id' => $blueprint->id,
            'full_path' => 'seo.title',
            'source_component_id' => $component->id,
        ]);

        Queue::assertPushed(ReindexBlueprintEntries::class);
    }

    public function test_cannot_attach_self_as_component(): void
    {
        $blueprint = Blueprint::factory()->component()->create();

        $response = $this->postJson("/api/admin/blueprints/{$blueprint->id}/components", [
            'component_id' => $blueprint->id,
            'path_prefix' => 'test',
        ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_attach_full_blueprint_as_component(): void
    {
        $blueprint = Blueprint::factory()->create();
        $notComponent = Blueprint::factory()->create(['type' => 'full']);

        $response = $this->postJson("/api/admin/blueprints/{$blueprint->id}/components", [
            'component_id' => $notComponent->id,
            'path_prefix' => 'test',
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_detach_component(): void
    {
        Queue::fake();

        $blueprint = Blueprint::factory()->create();
        $component = Blueprint::factory()->component()->create();
        Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
        ]);

        $blueprint->materializeComponentPaths($component, 'seo');
        $blueprint->components()->attach($component->id, ['path_prefix' => 'seo']);

        $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}/components/{$component->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('blueprint_components', [
            'blueprint_id' => $blueprint->id,
            'component_id' => $component->id,
        ]);

        $this->assertDatabaseMissing('paths', [
            'blueprint_id' => $blueprint->id,
            'source_component_id' => $component->id,
        ]);

        Queue::assertPushed(ReindexBlueprintEntries::class);
    }
}
```

#### 7.2.3. Тесты Observers

**Файл:** `tests/Feature/Observers/PathObserverTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PathObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_materialized_paths_on_update(): void
    {
        $component = Blueprint::factory()->component()->create();
        $sourcePath = Path::factory()->create([
            'blueprint_id' => $component->id,
            'name' => 'title',
            'full_path' => 'title',
            'data_type' => 'string',
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');
        $blueprint->components()->attach($component->id, ['path_prefix' => 'seo']);

        $materializedPath = $blueprint->paths()->where('source_path_id', $sourcePath->id)->first();

        $sourcePath->update(['data_type' => 'text']);

        expect($materializedPath->fresh()->data_type)->toBe('text');
    }

    public function test_syncs_full_path_on_rename(): void
    {
        $component = Blueprint::factory()->component()->create();
        $sourcePath = Path::factory()->create([
            'blueprint_id' => $component->id,
            'name' => 'oldName',
            'full_path' => 'oldName',
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');
        $blueprint->components()->attach($component->id, ['path_prefix' => 'seo']);

        $sourcePath->update([
            'name' => 'newName',
            'full_path' => 'newName',
        ]);

        $materializedPath = $blueprint->paths()->where('source_path_id', $sourcePath->id)->first();

        expect($materializedPath->full_path)->toBe('seo.newName');
        expect($materializedPath->name)->toBe('newName');
    }

    public function test_dispatches_reindex_job_on_indexed_field_change(): void
    {
        Queue::fake();

        $component = Blueprint::factory()->component()->create();
        $sourcePath = Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
            'is_indexed' => true,
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');
        $blueprint->components()->attach($component->id, ['path_prefix' => 'seo']);

        $sourcePath->update(['data_type' => 'text']);

        Queue::assertPushed(ReindexBlueprintEntries::class);
    }

    public function test_deletes_materialized_paths_on_source_deletion(): void
    {
        $component = Blueprint::factory()->component()->create();
        $sourcePath = Path::factory()->create([
            'blueprint_id' => $component->id,
            'full_path' => 'title',
        ]);

        $blueprint = Blueprint::factory()->create();
        $blueprint->materializeComponentPaths($component, 'seo');

        $materializedPath = $blueprint->paths()->where('source_path_id', $sourcePath->id)->first();
        expect($materializedPath)->not->toBeNull();

        $sourcePath->delete();

        $this->assertDatabaseMissing('paths', ['id' => $materializedPath->id]);
    }
}
```

---

### Задача 7.3: Integration тесты

#### 7.3.1. E2E тест полного workflow

**Файл:** `tests/Feature/Integration/BlueprintWorkflowTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_blueprint_workflow(): void
    {
        // 1. Создание компонента SEO
        $seoComponent = Blueprint::create([
            'slug' => 'seo',
            'name' => 'SEO Fields',
            'type' => 'component',
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaTitle',
            'full_path' => 'metaTitle',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
        ]);

        // 2. Создание полного Blueprint
        $postType = PostType::factory()->create(['slug' => 'article']);
        $blueprint = Blueprint::create([
            'post_type_id' => $postType->id,
            'slug' => 'article-full',
            'name' => 'Article Full',
            'type' => 'full',
            'is_default' => true,
        ]);

        Path::create([
            'blueprint_id' => $blueprint->id,
            'name' => 'content',
            'full_path' => 'content',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => false,
        ]);

        // 3. Attach компонента
        $blueprint->materializeComponentPaths($seoComponent, 'seo');
        $blueprint->components()->attach($seoComponent->id, ['path_prefix' => 'seo']);

        // 4. Проверка материализации
        $materializedPath = $blueprint->paths()
            ->where('source_component_id', $seoComponent->id)
            ->first();

        expect($materializedPath)->not->toBeNull();
        expect($materializedPath->full_path)->toBe('seo.metaTitle');

        // 5. Создание Entry с данными
        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'content' => 'Test article content',
                'seo' => [
                    'metaTitle' => 'SEO Title',
                ],
            ],
        ]);

        // 6. Проверка индексации
        expect($entry->values)->toHaveCount(1); // только metaTitle (indexed)

        $value = $entry->values->first();
        expect($value->path->full_path)->toBe('seo.metaTitle');
        expect($value->value_string)->toBe('SEO Title');

        // 7. Проверка запроса
        $found = Entry::wherePath('seo.metaTitle', '=', 'SEO Title')->first();
        expect($found->id)->toBe($entry->id);

        // 8. Изменение Path в компоненте
        $seoMetaTitlePath = $seoComponent->paths()->first();
        $seoMetaTitlePath->update(['data_type' => 'text']);

        // 9. Проверка синхронизации
        expect($materializedPath->fresh()->data_type)->toBe('text');

        // 10. Detach компонента
        $blueprint->dematerializeComponentPaths($seoComponent);
        $blueprint->components()->detach($seoComponent->id);

        // 11. Проверка удаления материализованных Paths
        $this->assertDatabaseMissing('paths', [
            'blueprint_id' => $blueprint->id,
            'source_component_id' => $seoComponent->id,
        ]);
    }

    public function test_ref_indexing_workflow(): void
    {
        $blueprint = Blueprint::factory()->create();

        Path::create([
            'blueprint_id' => $blueprint->id,
            'name' => 'relatedPosts',
            'full_path' => 'relatedPosts',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
            'ref_target_type' => 'any',
        ]);

        $target1 = Entry::factory()->create(['blueprint_id' => $blueprint->id]);
        $target2 = Entry::factory()->create(['blueprint_id' => $blueprint->id]);

        $entry = Entry::factory()->create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'relatedPosts' => [$target1->id, $target2->id],
            ],
        ]);

        expect($entry->refs)->toHaveCount(2);
        expect($entry->refs->pluck('target_entry_id')->toArray())
            ->toBe([$target1->id, $target2->id]);

        // Поиск обратных ссылок
        $foundByRef = Entry::whereRef('relatedPosts', $target1->id)->first();
        expect($foundByRef->id)->toBe($entry->id);
    }
}
```

---

**✅ МОДУЛЬ 7 ЗАВЕРШЁН**

---

## МОДУЛЬ 8: Миграция существующих данных

**Цель:** Мигрировать существующие Entry на новую систему Blueprint.

**Зависимости:** МОДУЛЬ 1-6.

**Время:** 2-3 дня.

---

### Задача 8.1: Создание default Blueprint

**Файл:** `app/Console/Commands/MigrateEntriesToBlueprintsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Console\Command;

class MigrateEntriesToBlueprintsCommand extends Command
{
    protected $signature = 'entries:migrate-to-blueprints
                            {--dry-run : Simulate without changes}';

    protected $description = 'Migrate existing entries to Blueprint system';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }

        $postTypes = PostType::all();

        foreach ($postTypes as $postType) {
            $this->info("Processing PostType: {$postType->slug}");

            // Создать или найти default Blueprint
            $blueprint = Blueprint::firstOrCreate(
                [
                    'post_type_id' => $postType->id,
                    'is_default' => true,
                ],
                [
                    'slug' => "{$postType->slug}_default",
                    'name' => "{$postType->name} Default",
                    'description' => "Default blueprint for {$postType->name}",
                    'type' => 'full',
                ]
            );

            $this->line("  Blueprint: {$blueprint->slug}");

            // Найти Entries без blueprint_id
            $entries = Entry::where('post_type_id', $postType->id)
                ->whereNull('blueprint_id')
                ->get();

            if ($entries->isEmpty()) {
                $this->line("  No entries to migrate");
                continue;
            }

            $this->line("  Found {$entries->count()} entries to migrate");

            if (!$dryRun) {
                foreach ($entries as $entry) {
                    $entry->update(['blueprint_id' => $blueprint->id]);
                }

                $this->info("  ✓ Migrated {$entries->count()} entries");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('DRY RUN completed. Run without --dry-run to apply changes.');
        } else {
            $this->info('Migration completed successfully!');
            $this->info('Run: php artisan entries:reindex --queue');
        }

        return 0;
    }
}
```

**Использование:**

```bash
# Проверка без изменений
php artisan entries:migrate-to-blueprints --dry-run

# Миграция
php artisan entries:migrate-to-blueprints

# Реиндексация
php artisan entries:reindex --queue
```

---

### Задача 8.2: Миграция Entry

Уже реализовано в Задаче 8.1.

---

### Задача 8.3: Валидация результата

**Файл:** `app/Console/Commands/ValidateBlueprintMigrationCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Entry;
use Illuminate\Console\Command;

class ValidateBlueprintMigrationCommand extends Command
{
    protected $signature = 'entries:validate-migration';

    protected $description = 'Validate Blueprint migration results';

    public function handle(): int
    {
        $this->info('Validating Blueprint migration...');
        $this->newLine();

        // 1. Проверка Entry без blueprint_id
        $orphanEntries = Entry::whereNull('blueprint_id')->count();

        if ($orphanEntries > 0) {
            $this->error("✗ Found {$orphanEntries} entries without blueprint_id");
        } else {
            $this->info('✓ All entries have blueprint_id');
        }

        // 2. Проверка Blueprint без PostType
        $orphanBlueprints = Blueprint::where('type', 'full')
            ->whereNull('post_type_id')
            ->count();

        if ($orphanBlueprints > 0) {
            $this->error("✗ Found {$orphanBlueprints} full blueprints without post_type_id");
        } else {
            $this->info('✓ All full blueprints have post_type_id');
        }

        // 3. Статистика индексации
        $totalEntries = Entry::count();
        $indexedCount = Entry::whereHas('values')->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', $totalEntries],
                ['Entries with indexes', $indexedCount],
                ['Coverage', round(($indexedCount / $totalEntries) * 100, 2) . '%'],
            ]
        );

        // 4. Рекомендации
        $this->newLine();

        if ($orphanEntries > 0) {
            $this->warn('Run: php artisan entries:migrate-to-blueprints');
        }

        if ($indexedCount < $totalEntries) {
            $this->warn('Run: php artisan entries:reindex --queue');
        }

        return $orphanEntries === 0 && $orphanBlueprints === 0 ? 0 : 1;
    }
}
```

---

**✅ МОДУЛЬ 8 ЗАВЕРШЁН**

---

## МОДУЛЬ 9: Оптимизация и мониторинг

**Цель:** Оптимизировать производительность и добавить мониторинг.

**Зависимости:** МОДУЛЬ 1-8.

**Время:** 3-4 дня.

---

### Задача 9.1: Кеширование

Уже реализовано:

-   `Blueprint::getAllPaths()` — кеш на 1 час
-   `Blueprint::invalidatePathsCache()` — инвалидация

**Дополнительное кеширование (опционально):**

**Файл:** `app/Services/BlueprintCacheService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use Illuminate\Support\Facades\Cache;

class BlueprintCacheService
{
    private const TTL = 3600; // 1 час

    public function getBlueprintSchema(int $blueprintId): array
    {
        $cacheKey = "blueprint:{$blueprintId}:schema";

        return Cache::remember($cacheKey, self::TTL, function () use ($blueprintId) {
            $blueprint = Blueprint::with(['paths', 'components'])->findOrFail($blueprintId);

            return [
                'id' => $blueprint->id,
                'slug' => $blueprint->slug,
                'name' => $blueprint->name,
                'paths' => $blueprint->getAllPaths()->map(fn($path) => [
                    'id' => $path->id,
                    'full_path' => $path->full_path,
                    'data_type' => $path->data_type,
                    'cardinality' => $path->cardinality,
                    'is_indexed' => $path->is_indexed,
                    'is_required' => $path->is_required,
                ])->toArray(),
            ];
        });
    }

    public function invalidateBlueprint(int $blueprintId): void
    {
        Cache::forget("blueprint:{$blueprintId}:schema");
    }
}
```

---

### Задача 9.2: Batch операции

**Оптимизация `syncDocumentIndex()` с batch insert:**

**Файл:** `app/Traits/HasDocumentData.php` (уже частично реализовано)

```php
private function syncScalarPath(Path $path, mixed $value): void
{
    if ($path->cardinality === 'one') {
        $this->syncSingleScalarValue($path, $value);
    } else {
        $this->syncManyScalarValues($path, $value);
    }
}

private function syncManyScalarValues(Path $path, array $values): void
{
    $valueField = $this->getValueFieldForType($path->data_type);
    $records = [];

    foreach ($values as $idx => $value) {
        $records[] = [
            'entry_id' => $this->id,
            'path_id' => $path->id,
            'idx' => $idx,
            $valueField => $value,
            'created_at' => now(),
        ];
    }

    // Batch insert
    if (!empty($records)) {
        DocValue::insert($records);
    }
}
```

---

### Задача 9.3: Логирование и метрики

**Файл:** `app/Observers/EntryIndexingObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Entry;
use Illuminate\Support\Facades\Log;

class EntryIndexingObserver
{
    public function saved(Entry $entry): void
    {
        $startTime = microtime(true);

        // Индексация выполняется в HasDocumentData

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($duration > 100) {
            Log::warning('Slow entry indexing', [
                'entry_id' => $entry->id,
                'blueprint_id' => $entry->blueprint_id,
                'duration_ms' => $duration,
                'values_count' => $entry->values()->count(),
                'refs_count' => $entry->refs()->count(),
            ]);
        }
    }
}
```

**Регистрация:**

```php
Entry::observe(EntryIndexingObserver::class);
```

---

**✅ МОДУЛЬ 9 ЗАВЕРШЁН**

---

## Финальный чеклист

### Проверка перед деплоем:

```bash
# 1. Тесты
php artisan test

# 2. Линтеры
composer phpcs
composer phpstan

# 3. Миграция
php artisan migrate

# 4. Сидеры
php artisan db:seed --class=BlueprintSeeder

# 5. Генерация документации
composer scribe:gen
php artisan docs:generate

# 6. Миграция данных
php artisan entries:migrate-to-blueprints --dry-run
php artisan entries:migrate-to-blueprints

# 7. Реиндексация
php artisan entries:reindex --queue

# 8. Валидация
php artisan entries:validate-migration

# 9. Диагностика
php artisan blueprint:diagnose article_full
```

---

## Сроки реализации

| Модуль       | Задачи                     | Время          | Зависимости    |
| ------------ | -------------------------- | -------------- | -------------- |
| **МОДУЛЬ 1** | Базовая инфраструктура     | 3-5 дней       | —              |
| **МОДУЛЬ 2** | Материализация компонентов | 2-3 дня        | МОДУЛЬ 1       |
| **МОДУЛЬ 3** | Индексация документов      | 3-4 дня        | МОДУЛЬ 1, 2    |
| **МОДУЛЬ 4** | Валидация и безопасность   | 2-3 дня        | МОДУЛЬ 1       |
| **МОДУЛЬ 5** | API контроллеры            | 4-5 дней       | МОДУЛЬ 1, 4    |
| **МОДУЛЬ 6** | Команды и утилиты          | 2-3 дня        | МОДУЛЬ 1, 2, 3 |
| **МОДУЛЬ 7** | Тестирование               | 5-7 дней       | МОДУЛЬ 1-6     |
| **МОДУЛЬ 8** | Миграция данных            | 2-3 дня        | МОДУЛЬ 1-6     |
| **МОДУЛЬ 9** | Оптимизация                | 3-4 дня        | МОДУЛЬ 1-8     |
| **ИТОГО**    |                            | **26-37 дней** |                |

---

## Порядок внедрения

**Рекомендуемая последовательность:**

1. **Неделя 1:** МОДУЛЬ 1 + МОДУЛЬ 2
2. **Неделя 2:** МОДУЛЬ 3 + МОДУЛЬ 4
3. **Неделя 3:** МОДУЛЬ 5
4. **Неделя 4:** МОДУЛЬ 6 + начало МОДУЛЬ 7
5. **Неделя 5:** Завершение МОДУЛЬ 7 + МОДУЛЬ 8
6. **Неделя 6:** МОДУЛЬ 9 + code review + bugfixing

---

## Критерии готовности

-   [ ] Все миграции применены
-   [ ] Все тесты проходят (>90% coverage)
-   [ ] Документация API актуальна (Scribe)
-   [ ] Существующие Entry мигрированы на Blueprint
-   [ ] Реиндексация завершена без ошибок
-   [ ] Валидация миграции прошла успешно
-   [ ] Performance baseline зафиксирован
-   [ ] Мониторинг настроен (логи медленных запросов)

---

**🎯 План готов к реализации!**
