# Блок A: Схема базы данных

**Трудоёмкость:** 18 часов  
**Критичность:** 🔴 Блокирует всё  
**Результат:** 7 миграций, 5 моделей, интеграция с PostType

---

## A.1. Требования к СУБД

**MySQL 8.0.16+** — для CHECK constraints.

Проверка версии:

```bash
php artisan tinker
DB::select('SELECT VERSION()');
```

---

## A.2-A.5. Миграции (5 файлов, строгий порядок)

### Миграция 1: `blueprints`

```bash
php artisan make:migration create_blueprints_table
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
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
```

### Миграция 2: `paths` (БЕЗ FK `blueprint_embed_id`)

```bash
php artisan make:migration create_paths_table
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_blueprint_id')->nullable()
                ->constrained('blueprints')->restrictOnDelete();
            $table->unsignedBigInteger('blueprint_embed_id')->nullable(); // FK добавим позже
            $table->foreignId('parent_id')->nullable()
                ->constrained('paths')->cascadeOnDelete();

            $table->string('name');
            $table->string('full_path', 2048);
            $table->enum('data_type', ['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref']);
            $table->enum('cardinality', ['one', 'many'])->default('one');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->timestamps();

            $table->unique(['blueprint_id', 'full_path'], 'uq_paths_full_path_per_blueprint');

            $table->index('blueprint_id');
            $table->index('source_blueprint_id');
            $table->index(['blueprint_id', 'parent_id', 'sort_order'], 'idx_paths_blueprint_parent');
        });

        // CHECK constraint для readonly инварианта
        DB::statement('
            ALTER TABLE paths ADD CONSTRAINT chk_paths_readonly_consistency CHECK (
                (source_blueprint_id IS NULL AND blueprint_embed_id IS NULL)
                OR (source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NOT NULL AND is_readonly = 1)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('paths');
    }
};
```

### Миграция 3: `blueprint_embeds`

```bash
php artisan make:migration create_blueprint_embeds_table
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
        Schema::create('blueprint_embeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embedded_blueprint_id')
                ->constrained('blueprints')->restrictOnDelete();
            $table->foreignId('host_path_id')->nullable()
                ->constrained('paths')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['blueprint_id', 'embedded_blueprint_id', 'host_path_id'],
                'uq_blueprint_embed'
            );
            $table->index('embedded_blueprint_id', 'idx_embeds_embedded');
            $table->index('blueprint_id', 'idx_embeds_blueprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_embeds');
    }
};
```

### Миграция 4: FK `paths.blueprint_embed_id`

```bash
php artisan make:migration add_blueprint_embed_fk_to_paths
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
        Schema::table('paths', function (Blueprint $table) {
            $table->foreign('blueprint_embed_id', 'fk_paths_blueprint_embed')
                ->references('id')
                ->on('blueprint_embeds')
                ->cascadeOnDelete();

            $table->index('blueprint_embed_id', 'idx_paths_embed');
        });
    }

    public function down(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->dropForeign('fk_paths_blueprint_embed');
            $table->dropIndex('idx_paths_embed');
        });
    }
};
```

### Миграция 5: `post_types.blueprint_id`

```bash
php artisan make:migration add_blueprint_id_to_post_types
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
        Schema::table('post_types', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('options_json')
                ->constrained('blueprints')
                ->restrictOnDelete();

            $table->index('blueprint_id', 'idx_post_types_blueprint');
        });
    }

    public function down(): void
    {
        Schema::table('post_types', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropIndex('idx_post_types_blueprint');
            $table->dropColumn('blueprint_id');
        });
    }
};
```

---

## A.6-A.8. Миграции индексации (2 файла)

### Миграция 6: `doc_values`

```bash
php artisan make:migration create_doc_values_table
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
        Schema::create('doc_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->integer('array_index')->nullable();

            // Скалярные значения (по типу поля)
            $table->string('value_string', 500)->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamps();

            $table->unique(['entry_id', 'path_id', 'array_index'], 'uq_doc_values_entry_path_idx');

            // Индексы для быстрых запросов
            $table->index('path_id');
            $table->index('value_string');
            $table->index('value_int');
            $table->index('value_float');
            $table->index('value_bool');
            $table->index('value_date');
            $table->index('value_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_values');
    }
};
```

### Миграция 7: `doc_refs`

```bash
php artisan make:migration create_doc_refs_table
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
        Schema::create('doc_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->integer('array_index')->nullable();
            $table->foreignId('target_entry_id')->constrained('entries')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['entry_id', 'path_id', 'array_index'], 'uq_doc_refs_entry_path_idx');

            $table->index('path_id');
            $table->index('target_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_refs');
    }
};
```

---

## Модели

### 1. `app/Models/Blueprint.php`

```bash
php artisan make:model Blueprint
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Шаблон структуры данных для Entry.
 *
 * @property int $id
 * @property string $name Название blueprint
 * @property string $code Уникальный код
 * @property string|null $description Описание
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $paths
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlueprintEmbed> $embeds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BlueprintEmbed> $embeddedIn
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PostType> $postTypes
 */
class Blueprint extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Поля blueprint'а (собственные + материализованные).
     *
     * @return HasMany<Path>
     */
    public function paths(): HasMany
    {
        return $this->hasMany(Path::class);
    }

    /**
     * Встраивания этого blueprint в другие (где данный blueprint = host).
     *
     * @return HasMany<BlueprintEmbed>
     */
    public function embeds(): HasMany
    {
        return $this->hasMany(BlueprintEmbed::class, 'blueprint_id');
    }

    /**
     * Где этот blueprint встроен в другие (где данный blueprint = embedded).
     *
     * @return HasMany<BlueprintEmbed>
     */
    public function embeddedIn(): HasMany
    {
        return $this->hasMany(BlueprintEmbed::class, 'embedded_blueprint_id');
    }

    /**
     * PostType, использующие этот blueprint.
     *
     * @return HasMany<PostType>
     */
    public function postTypes(): HasMany
    {
        return $this->hasMany(PostType::class);
    }
}
```

### 2. `app/Models/Path.php`

```bash
php artisan make:model Path
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Поле внутри blueprint с материализованным full_path.
 *
 * @property int $id
 * @property int $blueprint_id Владелец поля
 * @property int|null $source_blueprint_id Откуда скопировано (если копия)
 * @property int|null $blueprint_embed_id К какому embed привязано (если копия)
 * @property int|null $parent_id Родительский path
 * @property string $name Локальное имя поля
 * @property string $full_path Материализованный путь (e.g., 'author.contacts.phone')
 * @property string $data_type string|text|int|float|bool|date|datetime|json|ref
 * @property string $cardinality one|many
 * @property bool $is_required
 * @property bool $is_indexed
 * @property bool $is_readonly Нельзя редактировать (копия)
 * @property int $sort_order
 * @property array|null $validation_rules JSON-правила валидации
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Blueprint|null $sourceBlueprint
 * @property-read \App\Models\BlueprintEmbed|null $blueprintEmbed
 * @property-read \App\Models\Path|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Path> $children
 */
class Path extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'blueprint_id',
        'parent_id',
        'name',
        'data_type',
        'cardinality',
        'is_required',
        'is_indexed',
        'sort_order',
        'validation_rules',
    ];

    /**
     * ЗАЩИТА ПОЛЕЙ: нельзя массово заполнить (только через сервис).
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'source_blueprint_id',
        'blueprint_embed_id',
        'is_readonly',
        'full_path',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_required' => 'boolean',
        'is_indexed' => 'boolean',
        'is_readonly' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Владелец поля.
     *
     * @return BelongsTo<Blueprint, Path>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Откуда скопировано (если копия).
     *
     * @return BelongsTo<Blueprint, Path>
     */
    public function sourceBlueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'source_blueprint_id');
    }

    /**
     * К какому embed привязано (если копия).
     *
     * @return BelongsTo<BlueprintEmbed, Path>
     */
    public function blueprintEmbed(): BelongsTo
    {
        return $this->belongsTo(BlueprintEmbed::class);
    }

    /**
     * Родительский path (для вложенных полей).
     *
     * @return BelongsTo<Path, Path>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'parent_id');
    }

    /**
     * Дочерние paths.
     *
     * @return HasMany<Path>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Path::class, 'parent_id');
    }

    /**
     * Собственное поле (не копия)?
     *
     * @return bool
     */
    public function isOwn(): bool
    {
        return $this->source_blueprint_id === null;
    }

    /**
     * Копия из другого blueprint?
     *
     * @return bool
     */
    public function isCopied(): bool
    {
        return $this->source_blueprint_id !== null;
    }
}
```

### 3. `app/Models/BlueprintEmbed.php`

```bash
php artisan make:model BlueprintEmbed
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Связь встраивания blueprint'а.
 *
 * @property int $id
 * @property int $blueprint_id Кто встраивает (host)
 * @property int $embedded_blueprint_id Кого встраивают
 * @property int|null $host_path_id Под каким полем (NULL = корень)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint $blueprint
 * @property-read \App\Models\Blueprint $embeddedBlueprint
 * @property-read \App\Models\Path|null $hostPath
 */
class BlueprintEmbed extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'blueprint_id',
        'embedded_blueprint_id',
        'host_path_id',
    ];

    /**
     * Host blueprint (кто встраивает).
     *
     * @return BelongsTo<Blueprint, BlueprintEmbed>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Embedded blueprint (кого встраивают).
     *
     * @return BelongsTo<Blueprint, BlueprintEmbed>
     */
    public function embeddedBlueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'embedded_blueprint_id');
    }

    /**
     * Поле-контейнер (под которым живёт embedded).
     *
     * @return BelongsTo<Path, BlueprintEmbed>
     */
    public function hostPath(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'host_path_id');
    }
}
```

### 4. Обновить `app/Models/PostType.php`

```php
// В класс PostType добавить:

protected $fillable = [
    'slug',
    'name',
    'options_json',
    'blueprint_id',  // ← добавить
];

/**
 * Blueprint, определяющий структуру Entry этого типа.
 *
 * @return BelongsTo<Blueprint, PostType>
 */
public function blueprint(): BelongsTo
{
    return $this->belongsTo(Blueprint::class);
}
```

### 5. `app/Models/DocValue.php` и `app/Models/DocRef.php`

```bash
php artisan make:model DocValue
php artisan make:model DocRef
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индексированное скалярное значение из Entry.data_json.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int|null $array_index
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property string|null $value_date
 * @property string|null $value_datetime
 * @property string|null $value_text
 * @property array|null $value_json
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DocValue extends Model
{
    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_date',
        'value_datetime',
        'value_text',
        'value_json',
    ];

    protected $casts = [
        'value_bool' => 'boolean',
        'value_json' => 'array',
    ];

    /** @return BelongsTo<Entry, DocValue> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<Path, DocValue> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индексированная ссылка на другой Entry.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int|null $array_index
 * @property int $target_entry_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DocRef extends Model
{
    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'target_entry_id',
    ];

    /** @return BelongsTo<Entry, DocRef> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<Path, DocRef> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(Path::class);
    }

    /** @return BelongsTo<Entry, DocRef> */
    public function targetEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'target_entry_id');
    }
}
```

---

## Команды

```bash
# Создать миграции
php artisan make:migration create_blueprints_table
php artisan make:migration create_paths_table
php artisan make:migration create_blueprint_embeds_table
php artisan make:migration add_blueprint_embed_fk_to_paths
php artisan make:migration add_blueprint_id_to_post_types
php artisan make:migration create_doc_values_table
php artisan make:migration create_doc_refs_table

# Создать модели
php artisan make:model Blueprint
php artisan make:model Path
php artisan make:model BlueprintEmbed
php artisan make:model DocValue
php artisan make:model DocRef

# Запустить миграции (СТРОГО в порядке создания)
php artisan migrate

# Проверить структуру
php artisan schema:dump
```

---

## Проверка

```bash
# Проверить версию MySQL
php artisan tinker
>>> DB::select('SELECT VERSION()');

# Проверить таблицы
>>> DB::select('SHOW TABLES');

# Проверить CHECK constraint
>>> DB::select('SHOW CREATE TABLE paths');
```

---

## Критические моменты

1. **Порядок миграций:** Нарушение = SQL error (взаимные FK)
2. **CHECK constraint:** MySQL 8.0.16+ обязателен
3. **$guarded в Path:** `source_blueprint_id`, `blueprint_embed_id`, `is_readonly`, `full_path` — только через сервис
4. **PostType.blueprint_id:** nullable для обратной совместимости

---

**Результат:** База данных готова, модели созданы, интеграция с PostType выполнена.

**Следующий блок:** B (Встраивание и граф зависимостей).

