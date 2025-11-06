# 20) Модели Eloquent и связи — детальная постановка

## Цель
Собрать доменный слой моделей Laravel для всех таблиц, описать связи, скоупы, типовые касты полей (JSON/даты), включить SoftDeletes там, где нужно, и прикрыть базовыми тестами.

---

## Что делаем (пошагово)
1. Создаём модели в `app/Models`:  
   `User`, `PostType`, `Entry`, `EntrySlug`, `Taxonomy`, `Term`, `TermTree`, `Media`, `MediaVariant`, `EntryMedia` (pivot), `Option`, `Plugin`, `PluginMigration`, `PluginReserved`, `ReservedRoute`, `Audit`, `Outbox`, `Redirect`.

2. Для моделей настраиваем:
   - `use SoftDeletes;` для: **Entry**, **Term**, **Media**.
   - `$guarded = [];` (или `$fillable` — на ваш вкус).
   - `$casts` для JSON → `array` и дат → `datetime`.
   - Связи: `belongsTo`, `hasMany`, `belongsToMany`, pivot `EntryMedia` с `withPivot('field_key')`.

3. Добавляем скоупы и хелперы:
   - `Entry::published()` — published + `published_at <= now` + not deleted.  
   - `Entry::ofType('page')` — фильтр по типу поста.  
   - `Term::inTaxonomy('tags'|'categories')`.  
   - `Entry::url()` — `/{slug}` для Page, типизированный фолбэк для других типов.  
   - `Option::get/set()` — быстрый доступ к опциям.

4. Пишем минимальные **feature-тесты** связей/скоупов.

---

## Код моделей (готовые заготовки)

> Добавьте недостающие `use` по месту, если IDE подсветит.

### `app/Models/User.php`
```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
}
```

### `app/Models/PostType.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostType extends Model
{
    protected $guarded = [];
    protected $casts = ['options_json' => 'array'];

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }
}
```

### `app/Models/Entry.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Entry extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'data_json'    => 'array',
        'seo_json'     => 'array',
        'published_at' => 'datetime',
    ];

    // Связи
    public function postType() { return $this->belongsTo(PostType::class); }
    public function author()   { return $this->belongsTo(User::class, 'author_id'); }
    public function slugs()    { return $this->hasMany(EntrySlug::class); }

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'entry_term', 'entry_id', 'term_id');
    }

    public function media()
    {
        return $this->belongsToMany(Media::class, 'entry_media', 'entry_id', 'media_id')
            ->using(EntryMedia::class)
            ->withPivot('field_key');
    }

    // Скоупы
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', Carbon::now());
    }

    public function scopeOfType(Builder $q, string $postTypeSlug): Builder
    {
        return $q->whereHas('postType', fn($qq) => $qq->where('slug', $postTypeSlug));
    }

    // Хелпер: публичный URL (для Page — плоский URL)
    public function url(): string
    {
        $slug = $this->slug;
        $type = $this->relationLoaded('postType') ? $this->postType->slug : $this->postType()->value('slug');
        return $type === 'page' ? "/{$slug}" : sprintf('/%s/%s', $type, $slug);
    }
}
```

### `app/Models/EntrySlug.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrySlug extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'entry_slugs';
    protected $casts = ['is_current' => 'boolean', 'created_at' => 'datetime'];

    public function entry() { return $this->belongsTo(Entry::class); }
}
```

### `app/Models/Taxonomy.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    protected $guarded = [];

    public function terms() { return $this->hasMany(Term::class); }
}
```

### `app/Models/Term.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Term extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['meta_json' => 'array'];

    public function taxonomy() { return $this->belongsTo(Taxonomy::class); }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_term', 'term_id', 'entry_id');
    }

    // Closure-table: предки/потомки
    public function ancestors()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'descendant_id', 'ancestor_id')
                    ->withPivot('depth');
    }

    public function descendants()
    {
        return $this->belongsToMany(Term::class, 'term_tree', 'ancestor_id', 'descendant_id')
                    ->withPivot('depth');
    }

    public function scopeInTaxonomy(Builder $q, string $taxonomySlug): Builder
    {
        return $q->whereHas('taxonomy', fn($qq) => $qq->where('slug', $taxonomySlug));
    }
}
```

### `app/Models/TermTree.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTree extends Model
{
    public $timestamps = false;
    protected $table = 'term_tree';
    protected $guarded = [];
    public $incrementing = false;
    protected $primaryKey = null;
}
```

### `app/Models/Media.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['meta_json' => 'array'];

    public function variants() { return $this->hasMany(MediaVariant::class); }

    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'entry_media', 'media_id', 'entry_id')
            ->using(EntryMedia::class)
            ->withPivot('field_key');
    }
}
```

### `app/Models/MediaVariant.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaVariant extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'media_variants';

    public function media() { return $this->belongsTo(Media::class); }
}
```

### `app/Models/EntryMedia.php` (pivot)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EntryMedia extends Pivot
{
    public $timestamps = false;
    protected $table = 'entry_media';
    protected $guarded = [];
}
```

### `app/Models/Option.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $guarded = [];
    protected $casts = ['value_json' => 'array'];

    public static function get(string $ns, string $key, $default = null)
    {
        $row = static::query()
            ->where('namespace', $ns)->where('key', $key)->value('value_json');
        return $row ?? $default;
    }

    public static function set(string $ns, string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['namespace' => $ns, 'key' => $key],
            ['value_json' => $value]
        );
    }
}
```

### Плагины и служебные модели
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $guarded = [];
    protected $casts = ['manifest_json' => 'array', 'enabled' => 'boolean'];

    public function migrations() { return $this->hasMany(PluginMigration::class); }
    public function reserved()   { return $this->hasMany(PluginReserved::class); }
}

class PluginMigration extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'plugin_migrations';

    public function plugin() { return $this->belongsTo(Plugin::class); }
}

class PluginReserved extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'plugin_reserved';

    public function plugin() { return $this->belongsTo(Plugin::class); }
}
```

### Прочие: `ReservedRoute`, `Audit`, `Outbox`, `Redirect`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservedRoute extends Model
{
    protected $guarded = [];
}

class Audit extends Model
{
    protected $guarded = [];
    protected $casts = ['diff_json' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
}

class Outbox extends Model
{
    protected $guarded = [];
    protected $casts = [
        'payload_json' => 'array',
        'attempts'     => 'integer',
        'available_at' => 'datetime',
    ];
}

class Redirect extends Model
{
    protected $guarded = [];
}
```

---

## (Опционально) Фабрики для тестов

### `database/factories/PostTypeFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostTypeFactory extends Factory
{
    protected $model = PostType::class;

    public function definition(): array
    {
        return [
            'slug'         => 'page',
            'name'         => 'Page',
            'template'     => null,
            'options_json' => [],
        ];
    }
}
```

### `database/factories/EntryFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'post_type_id' => PostType::factory(),
            'title'        => 'About',
            'slug'         => 'about',
            'status'       => 'draft',
            'published_at' => null,
            'data_json'    => ['body' => '<p>Hello</p>'],
            'seo_json'     => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn()=>[
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }
}
```

> Аналогично можно добавить фабрики для `Taxonomy`, `Term`, `Media`.

---

## Тесты (PHPUnit) — `tests/Feature/Models/RelationsTest.php`
```php
<?php

namespace Tests\Feature\Models;

use App\Models\{PostType, Entry, Taxonomy, Term, Media};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function entry_belongs_to_post_type()
    {
        $pt = PostType::factory()->create(['slug'=>'page']);
        $entry = Entry::factory()->create(['post_type_id'=>$pt->id]);
        $this->assertTrue($entry->postType->is($pt));
    }

    /** @test */
    public function entry_belongs_to_many_terms()
    {
        $pt = PostType::factory()->create(['slug'=>'page']);
        $entry = Entry::factory()->create(['post_type_id'=>$pt->id]);

        $tax = Taxonomy::create(['slug'=>'tags','name'=>'Tags','hierarchical'=>false]);
        $t1  = Term::create(['taxonomy_id'=>$tax->id,'slug'=>'a','name'=>'A','meta_json'=>[]]);
        $t2  = Term::create(['taxonomy_id'=>$tax->id,'slug'=>'b','name'=>'B','meta_json'=>[]]);

        $entry->terms()->attach([$t1->id, $t2->id]);

        $this->assertCount(2, $entry->terms);
        $this->assertTrue($entry->terms->contains($t1));
        $this->assertTrue($entry->terms->contains($t2));
    }

    /** @test */
    public function term_belongs_to_taxonomy()
    {
        $tax = Taxonomy::create(['slug'=>'categories','name'=>'Categories','hierarchical'=>true]);
        $term= Term::create(['taxonomy_id'=>$tax->id,'slug'=>'news','name'=>'News','meta_json'=>[]]);

        $this->assertTrue($term->taxonomy->is($tax));
    }

    /** @test */
    public function media_variants_and_entry_media_pivot()
    {
        $pt = PostType::factory()->create(['slug'=>'page']);
        $entry = Entry::factory()->create(['post_type_id'=>$pt->id]);

        $media = Media::create([
            'disk'=>'public','path'=>'a.jpg','mime'=>'image/jpeg','size'=>100,'meta_json'=>[]
        ]);
        $media->variants()->create(['variant_key'=>'thumb','path'=>'a_thumb.jpg','width'=>400,'height'=>300,'size'=>10]);

        $entry->media()->attach($media->id, ['field_key'=>'gallery']);
        $this->assertEquals('gallery', $entry->media()->first()->pivot->field_key);
        $this->assertEquals('a_thumb.jpg', $media->variants()->first()->path);
    }

    /** @test */
    public function scope_published_filters_correctly()
    {
        $pt = PostType::factory()->create(['slug'=>'page']);
        $published = Entry::factory()->published()->create(['post_type_id'=>$pt->id, 'slug'=>'about']);
        $draft     = Entry::factory()->create(['post_type_id'=>$pt->id, 'slug'=>'draft']);

        $list = Entry::published()->get();
        $this->assertTrue($list->contains($published));
        $this->assertFalse($list->contains($draft));
    }

    /** @test */
    public function entry_url_returns_flat_slug_for_pages()
    {
        $pt = PostType::factory()->create(['slug'=>'page']);
        $entry = Entry::factory()->create(['post_type_id'=>$pt->id, 'slug'=>'contacts']);
        $this->assertEquals('/contacts', $entry->url());
    }
}
```
---

## Критерии приёмки (расширенные)
1. Все модели существуют в `app/Models`; **Entry**, **Term**, **Media** — с `SoftDeletes`.
2. Касты JSON/дат настроены (см. `$casts` в коде).
3. Связи работают: `Entry->postType/terms/media/slugs`, `Term->taxonomy/ancestors/descendants`, `Media->variants/entries`.
4. В связке `Entry↔Media` используется **pivot `EntryMedia`** с `field_key` (читается через `$pivot`).
5. Скоупы работают: `Entry::published()`, `Entry::ofType()`, `Term::inTaxonomy()`.
6. Хелпер `Entry::url()` возвращает `/{slug}` для Page.
7. Тесты `php artisan test` проходят (см. файл `RelationsTest`).
8. Кодстайл: проект проходит форматирование/линт без ошибок.
