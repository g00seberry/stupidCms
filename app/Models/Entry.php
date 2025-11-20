<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasDocumentData;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Eloquent модель для записей контента (Entry).
 *
 * Представляет единицу контента в CMS: статьи, страницы, посты и т.д.
 * Поддерживает мягкое удаление, публикацию по расписанию, связи с термами.
 *
 * @property int $id
 * @property int $post_type_id ID типа записи
 * @property string $title Заголовок записи
 * @property string $slug Уникальный slug записи в рамках типа
 * @property string $status Статус записи: 'draft' или 'published'
 * @property array $data_json Произвольные структурированные данные контента
 * @property array|null $seo_json SEO-метаданные (title, description, keywords и т.д.)
 * @property \Illuminate\Support\Carbon|null $published_at Дата и время публикации (UTC)
 * @property string|null $template_override Кастомный шаблон Blade для рендеринга
 * @property int $author_id ID автора записи
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at Дата мягкого удаления
 *
 * @property-read \App\Models\PostType $postType Тип записи
 * @property-read \App\Models\User $author Автор записи
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Term> $terms Привязанные термы (категории, теги)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocValue> $docValues Индексированные скалярные значения
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocRef> $docRefs Индексированные ссылки на другие Entry (исходящие)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocRef> $docRefsIncoming Входящие ссылки (кто ссылается на этот Entry)
 * @property-read \App\Models\Blueprint|null $blueprint Blueprint через PostType
 */
class Entry extends Model
{
    use HasFactory, SoftDeletes, HasDocumentData;

    /**
     * Статус: черновик.
     */
    public const STATUS_DRAFT = 'draft';

    /**
     * Статус: опубликовано.
     */
    public const STATUS_PUBLISHED = 'published';

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_json' => 'array',
        'seo_json' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Получить список возможных статусов записи.
     *
     * @return array<string> Массив статусов: ['draft', 'published']
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
        ];
    }

    /**
     * Связь с типом записи (PostType).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\PostType, \App\Models\Entry>
     */
    public function postType()
    {
        return $this->belongsTo(PostType::class);
    }

    /**
     * Связь с автором записи.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Entry>
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Связь с термами (категории, теги и т.д.).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Term, \App\Models\Entry>
     */
    public function terms()
    {
        return $this->belongsToMany(Term::class, 'entry_term', 'entry_id', 'term_id')
            ->withTimestamps();
    }

    /**
     * Индексированные скалярные значения из data_json.
     *
     * @return HasMany<DocValue>
     */
    public function docValues(): HasMany
    {
        return $this->hasMany(DocValue::class);
    }

    /**
     * Индексированные ссылки на другие Entry (исходящие).
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


    /**
     * Скоуп: только опубликованные записи.
     *
     * Фильтрует записи со статусом 'published', у которых published_at не null
     * и не превышает текущее время (UTC).
     *
     * @param \Illuminate\Database\Eloquent\Builder<Entry> $q
     * @return \Illuminate\Database\Eloquent\Builder<Entry>
     */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now('UTC'));
    }

    /**
     * Скоуп: записи определённого типа.
     *
     * Фильтрует записи по slug типа записи через связь postType.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Entry> $q
     * @param string $postTypeSlug Slug типа записи
     * @return \Illuminate\Database\Eloquent\Builder<Entry>
     */
    public function scopeOfType(Builder $q, string $postTypeSlug): Builder
    {
        return $q->whereHas('postType', fn($qq) => $qq->where('slug', $postTypeSlug));
    }

    /**
     * Получить публичный URL записи.
     *
     * Для типа 'page' возвращает плоский URL (/slug),
     * для остальных типов — иерархический URL (/type/slug).
     * Если связь postType не загружена, выполняет дополнительный запрос.
     *
     * @return string Публичный URL записи
     */
    public function url(): string
    {
        $slug = $this->slug;
        $type = $this->relationLoaded('postType') ? $this->postType->slug : $this->postType()->value('slug');
        return $type === 'page' ? "/{$slug}" : sprintf('/%s/%s', $type, $slug);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\EntryFactory
     */
    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }
}

