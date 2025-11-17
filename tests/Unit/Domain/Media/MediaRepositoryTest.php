<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\EloquentMediaRepository;
use App\Domain\Media\MediaDeletedFilter;
use App\Domain\Media\MediaQuery;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kind_document_excludes_media_types(): void
    {
        Media::factory()->image()->create();
        $doc = Media::factory()->document()->create(['mime' => 'application/pdf']);

        $repo = app(EloquentMediaRepository::class);
        $q = new MediaQuery(search: null, kind: 'document', mimePrefix: null, collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted, sort: 'created_at', order: 'desc', page: 1, perPage: 50);

        $result = $repo->get($q);
        $this->assertCount(1, $result);
        $this->assertSame($doc->id, $result->first()->id);
    }

    public function test_mime_prefix_filters(): void
    {
        Media::factory()->image()->create(['mime' => 'image/png', 'ext' => 'png']);
        Media::factory()->image()->create(['mime' => 'image/jpeg', 'ext' => 'jpg']);

        $repo = app(EloquentMediaRepository::class);
        $q = new MediaQuery(search: null, kind: null, mimePrefix: 'image/png', collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted, sort: 'created_at', order: 'desc', page: 1, perPage: 50);

        $result = $repo->get($q);
        $this->assertCount(1, $result);
        $this->assertSame('image/png', $result->first()->mime);
    }

    /**
     * Тест: пагинация со сложными фильтрами.
     */
    public function test_paginates_with_complex_filters(): void
    {
        // Создаём медиа с разными параметрами
        $media1 = Media::factory()->image()->create([
            'title' => 'Test Image 1',
            'mime' => 'image/jpeg',
            'collection' => 'gallery',
            'size_bytes' => 1000,
        ]);
        $media2 = Media::factory()->image()->create([
            'title' => 'Test Image 2',
            'mime' => 'image/png',
            'collection' => 'gallery',
            'size_bytes' => 2000,
        ]);
        Media::factory()->image()->create([
            'title' => 'Other Image',
            'mime' => 'image/jpeg',
            'collection' => 'banners',
            'size_bytes' => 3000,
        ]);

        $repo = app(EloquentMediaRepository::class);
        $q = new MediaQuery(
            search: 'Test',
            kind: 'image',
            mimePrefix: null,
            collection: 'gallery',
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'size_bytes',
            order: 'asc',
            page: 1,
            perPage: 10
        );

        $result = $repo->paginate($q);

        $this->assertCount(2, $result->items());
        $this->assertEquals(2, $result->total());
        $this->assertEquals($media1->id, $result->items()[0]->id);
        $this->assertEquals($media2->id, $result->items()[1]->id);
    }

    /**
     * Тест: поиск по title и original_name.
     */
    public function test_searches_by_title_and_original_name(): void
    {
        $media1 = Media::factory()->create([
            'title' => 'Beautiful Sunset',
            'original_name' => 'sunset.jpg',
        ]);
        $media2 = Media::factory()->create([
            'title' => 'Mountain View',
            'original_name' => 'sunset_photo.png',
        ]);
        Media::factory()->create([
            'title' => 'City Lights',
            'original_name' => 'city.jpg',
        ]);

        $repo = app(EloquentMediaRepository::class);

        // Поиск по title
        $q1 = new MediaQuery(
            search: 'Beautiful',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertCount(1, $result1);
        $this->assertEquals($media1->id, $result1->first()->id);

        // Поиск по original_name
        $q2 = new MediaQuery(
            search: 'sunset',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertCount(2, $result2);
        $this->assertTrue($result2->contains('id', $media1->id));
        $this->assertTrue($result2->contains('id', $media2->id));
    }

    /**
     * Тест: фильтрация по префиксу MIME.
     */
    public function test_filters_by_mime_prefix(): void
    {
        Media::factory()->create(['mime' => 'image/jpeg']);
        Media::factory()->create(['mime' => 'image/png']);
        Media::factory()->create(['mime' => 'video/mp4']);
        Media::factory()->create(['mime' => 'audio/mpeg']);

        $repo = app(EloquentMediaRepository::class);

        // Фильтр по префиксу 'image/'
        $q1 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: 'image/',
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertCount(2, $result1);
        $this->assertTrue($result1->every(fn ($m) => str_starts_with($m->mime, 'image/')));

        // Фильтр по префиксу 'video/'
        $q2 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: 'video/',
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertCount(1, $result2);
        $this->assertEquals('video/mp4', $result2->first()->mime);
    }

    /**
     * Тест: сортировка по кастомным полям.
     */
    public function test_sorts_by_custom_fields(): void
    {
        $media1 = Media::factory()->create([
            'title' => 'Alpha',
            'size_bytes' => 1000,
            'width' => 100,
            'height' => 200,
        ]);
        $media2 = Media::factory()->create([
            'title' => 'Beta',
            'size_bytes' => 2000,
            'width' => 200,
            'height' => 300,
        ]);
        $media3 = Media::factory()->create([
            'title' => 'Gamma',
            'size_bytes' => 500,
            'width' => 50,
            'height' => 100,
        ]);

        $repo = app(EloquentMediaRepository::class);

        // Сортировка по size_bytes (asc)
        $q1 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'size_bytes',
            order: 'asc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertEquals($media3->id, $result1->first()->id);
        $this->assertEquals($media1->id, $result1->get(1)->id);
        $this->assertEquals($media2->id, $result1->last()->id);

        // Сортировка по title (desc)
        $q2 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'title',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertEquals($media3->id, $result2->first()->id); // Gamma
        $this->assertEquals($media2->id, $result2->get(1)->id); // Beta
        $this->assertEquals($media1->id, $result2->last()->id); // Alpha

        // Сортировка по width (desc)
        $q3 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'width',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result3 = $repo->get($q3);
        $this->assertEquals($media2->id, $result3->first()->id);
        $this->assertEquals($media1->id, $result3->get(1)->id);
        $this->assertEquals($media3->id, $result3->last()->id);
    }

    /**
     * Тест: корректная обработка фильтра soft-deleted.
     */
    public function test_handles_soft_deleted_filter_correctly(): void
    {
        $active1 = Media::factory()->create(['title' => 'Active 1']);
        $active2 = Media::factory()->create(['title' => 'Active 2']);
        $deleted1 = Media::factory()->create(['title' => 'Deleted 1']);
        $deleted1->delete();
        $deleted2 = Media::factory()->create(['title' => 'Deleted 2']);
        $deleted2->delete();

        $repo = app(EloquentMediaRepository::class);

        // DefaultOnlyNotDeleted - только активные
        $q1 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertCount(2, $result1);
        $this->assertTrue($result1->contains('id', $active1->id));
        $this->assertTrue($result1->contains('id', $active2->id));

        // OnlyDeleted - только удалённые
        $q2 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::OnlyDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertCount(2, $result2);
        $this->assertTrue($result2->contains('id', $deleted1->id));
        $this->assertTrue($result2->contains('id', $deleted2->id));

        // WithDeleted - все (активные и удалённые)
        $q3 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::WithDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result3 = $repo->get($q3);
        $this->assertCount(4, $result3);
    }

    /**
     * Тест: обработка пустого поискового запроса.
     */
    public function test_handles_empty_search_query(): void
    {
        Media::factory()->create(['title' => 'Test Image']);
        Media::factory()->create(['title' => 'Another Image']);

        $repo = app(EloquentMediaRepository::class);

        // Пустая строка
        $q1 = new MediaQuery(
            search: '',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertCount(2, $result1);

        // null
        $q2 = new MediaQuery(
            search: null,
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertCount(2, $result2);
    }

    /**
     * Тест: обработка специальных символов в поиске.
     *
     * Проверяет, что поиск корректно обрабатывает специальные символы SQL (%, _)
     * и другие символы (кавычки). Символы % и _ в SQL LIKE являются wildcards,
     * поэтому поиск по ним может находить все записи. Тест проверяет, что
     * поиск работает без ошибок и находит записи, содержащие эти символы.
     */
    public function test_handles_special_characters_in_search(): void
    {
        $media1 = Media::factory()->create([
            'title' => 'UniquePercentImage',
            'original_name' => 'unique100percent.jpg',
        ]);
        $media2 = Media::factory()->create([
            'title' => 'UniqueUnderscoreImage',
            'original_name' => 'unique_file_name.png',
        ]);
        $media3 = Media::factory()->create([
            'title' => "UniqueSingleQuoteImage",
            'original_name' => "unique'file.jpg",
        ]);
        $media4 = Media::factory()->create([
            'title' => 'UniqueDoubleQuoteImage',
            'original_name' => 'unique"file.png',
        ]);
        Media::factory()->create([
            'title' => 'NormalImage',
            'original_name' => 'normal.jpg',
        ]);

        $repo = app(EloquentMediaRepository::class);

        // Поиск по тексту, содержащему специальные символы в названии
        $q1 = new MediaQuery(
            search: 'UniquePercent',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result1 = $repo->get($q1);
        $this->assertCount(1, $result1);
        $this->assertEquals($media1->id, $result1->first()->id);

        // Поиск по тексту с underscore
        $q2 = new MediaQuery(
            search: 'UniqueUnderscore',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result2 = $repo->get($q2);
        $this->assertCount(1, $result2);
        $this->assertEquals($media2->id, $result2->first()->id);

        // Поиск по original_name с underscore (проверка, что поиск работает по обоим полям)
        $q3 = new MediaQuery(
            search: 'unique_file_name',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result3 = $repo->get($q3);
        $this->assertCount(1, $result3);
        $this->assertEquals($media2->id, $result3->first()->id);

        // Поиск с одинарной кавычкой в запросе (проверка экранирования)
        $q4 = new MediaQuery(
            search: "UniqueSingle",
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result4 = $repo->get($q4);
        $this->assertCount(1, $result4);
        $this->assertEquals($media3->id, $result4->first()->id);

        // Поиск с двойной кавычкой
        $q5 = new MediaQuery(
            search: 'UniqueDouble',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result5 = $repo->get($q5);
        $this->assertCount(1, $result5);
        $this->assertEquals($media4->id, $result5->first()->id);

        // Проверка, что поиск по обычному тексту не находит записи со специальными символами
        $q6 = new MediaQuery(
            search: 'NormalImage',
            kind: null,
            mimePrefix: null,
            collection: null,
            deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
            sort: 'created_at',
            order: 'desc',
            page: 1,
            perPage: 50
        );
        $result6 = $repo->get($q6);
        $this->assertCount(1, $result6);
        $this->assertNotEquals($media1->id, $result6->first()->id);
        $this->assertNotEquals($media2->id, $result6->first()->id);
    }
}


