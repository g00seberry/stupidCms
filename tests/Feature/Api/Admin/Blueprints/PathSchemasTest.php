<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты для всех схем Path из документации form-generation-from-path.md.
 *
 * Создаёт реальные структуры через BlueprintStructureService (как в UltraComplexBlueprintSystemTest).
 */
class PathSchemasTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintStructureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlueprintStructureService::class);
    }

    /**
     * Схема 1: Простое поле (корневой уровень).
     */
    public function test_schema_1_simple_field_root_level(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Simple Article',
            'code' => 'simple_article',
        ]);

        $path = $this->service->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_required' => true,
            'is_indexed' => true,
            'sort_order' => 0,
            'validation_rules' => [
                'min' => 1,
                'max' => 500,
            ],
        ]);

        $this->assertEquals('title', $path->name);
        $this->assertEquals('title', $path->full_path);
        $this->assertEquals('string', $path->data_type);
        $this->assertEquals('one', $path->cardinality);
        $this->assertTrue($path->is_required);
        $this->assertTrue($path->is_indexed);
        $this->assertFalse((bool) $path->is_readonly); // is_readonly может быть null, проверяем как bool
        $this->assertNull($path->parent_id);
        $this->assertEquals(['min' => 1, 'max' => 500], $path->validation_rules);

        // Проверка через модель
        $paths = $blueprint->paths()->whereNull('parent_id')->get();
        $this->assertCount(1, $paths);
        $this->assertEquals('title', $paths->first()->name);
    }

    /**
     * Схема 2: Вложенное поле (один уровень вложенности).
     */
    public function test_schema_2_nested_field_one_level(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Author',
            'code' => 'article_author',
        ]);

        // Создаём родительские paths для author.contacts.phone
        $authorPath = $this->service->createPath($blueprint, [
            'name' => 'author',
            'data_type' => 'json',
        ]);

        $contactsPath = $this->service->createPath($blueprint, [
            'name' => 'contacts',
            'data_type' => 'json',
            'parent_id' => $authorPath->id,
        ]);

        $phonePath = $this->service->createPath($blueprint, [
            'name' => 'phone',
            'data_type' => 'string',
            'parent_id' => $contactsPath->id,
            'is_required' => false,
            'is_indexed' => true,
            'validation_rules' => [
                'pattern' => '^\\+?[1-9]\\d{1,14}$',
            ],
        ]);

        $this->assertEquals('phone', $phonePath->name);
        $this->assertEquals('author.contacts.phone', $phonePath->full_path);
        $this->assertEquals('string', $phonePath->data_type);
        $this->assertEquals($contactsPath->id, $phonePath->parent_id);
        $this->assertEquals(['pattern' => '^\\+?[1-9]\\d{1,14}$'], $phonePath->validation_rules);

        // Проверка дерева
        $rootPaths = $blueprint->paths()->whereNull('parent_id')->get();
        $this->assertCount(1, $rootPaths);
        $this->assertEquals('author', $rootPaths->first()->name);
    }

    /**
     * Схема 3: Группа полей (data_type: json).
     */
    public function test_schema_3_field_group_json(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Author Group',
            'code' => 'article_author_group',
        ]);

        // Создаём группу author
        $authorPath = $this->service->createPath($blueprint, [
            'name' => 'author',
            'data_type' => 'json',
            'cardinality' => 'one',
        ]);

        // Создаём поля внутри группы
        $namePath = $this->service->createPath($blueprint, [
            'name' => 'name',
            'data_type' => 'string',
            'parent_id' => $authorPath->id,
            'is_required' => true,
        ]);

        $emailPath = $this->service->createPath($blueprint, [
            'name' => 'email',
            'data_type' => 'string',
            'parent_id' => $authorPath->id,
            'is_required' => true,
        ]);

        $contactsPath = $this->service->createPath($blueprint, [
            'name' => 'contacts',
            'data_type' => 'json',
            'parent_id' => $authorPath->id,
        ]);

        $phonePath = $this->service->createPath($blueprint, [
            'name' => 'phone',
            'data_type' => 'string',
            'parent_id' => $contactsPath->id,
        ]);

        // Проверка структуры
        $this->assertEquals('author', $authorPath->name);
        $this->assertEquals('json', $authorPath->data_type);
        $this->assertEquals('author.name', $namePath->full_path);
        $this->assertEquals('author.email', $emailPath->full_path);
        $this->assertEquals('author.contacts', $contactsPath->full_path);
        $this->assertEquals('author.contacts.phone', $phonePath->full_path);

        // Проверка через модель
        $children = $blueprint->paths()->where('parent_id', $authorPath->id)->get();
        $this->assertCount(3, $children);
        $this->assertTrue($children->contains('name', 'name'));
        $this->assertTrue($children->contains('name', 'email'));
        $this->assertTrue($children->contains('name', 'contacts'));
    }

    /**
     * Схема 4: Массив значений (cardinality: many).
     */
    public function test_schema_4_array_of_values_many(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Tags',
            'code' => 'article_tags',
        ]);

        $path = $this->service->createPath($blueprint, [
            'name' => 'tags',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_required' => false,
            'is_indexed' => true,
        ]);

        $this->assertEquals('tags', $path->name);
        $this->assertEquals('tags', $path->full_path);
        $this->assertEquals('string', $path->data_type);
        $this->assertEquals('many', $path->cardinality);
        $this->assertTrue($path->is_indexed);
    }

    /**
     * Схема 5: Массив групп (cardinality: many + data_type: json).
     */
    public function test_schema_5_array_of_groups_many_json(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Gallery',
            'code' => 'article_gallery',
        ]);

        // Создаём группу gallery с cardinality: many
        $galleryPath = $this->service->createPath($blueprint, [
            'name' => 'gallery',
            'data_type' => 'json',
            'cardinality' => 'many',
        ]);

        // Создаём поля внутри gallery
        $imagePath = $this->service->createPath($blueprint, [
            'name' => 'image',
            'data_type' => 'ref',
            'parent_id' => $galleryPath->id,
            'is_required' => true,
        ]);

        $captionPath = $this->service->createPath($blueprint, [
            'name' => 'caption',
            'data_type' => 'string',
            'parent_id' => $galleryPath->id,
            'is_required' => false,
        ]);

        // Проверка структуры
        $this->assertEquals('gallery', $galleryPath->name);
        $this->assertEquals('json', $galleryPath->data_type);
        $this->assertEquals('many', $galleryPath->cardinality);
        $this->assertEquals('gallery', $galleryPath->full_path);

        $children = $blueprint->paths()->where('parent_id', $galleryPath->id)->get();
        $this->assertCount(2, $children);
        
        $imageChild = $children->firstWhere('name', 'image');
        $captionChild = $children->firstWhere('name', 'caption');

        $this->assertNotNull($imageChild);
        $this->assertEquals('ref', $imageChild->data_type);
        $this->assertNotNull($captionChild);
        $this->assertEquals('string', $captionChild->data_type);
    }

    /**
     * Схема 6: Ссылка на Entry (data_type: ref).
     */
    public function test_schema_6_entry_reference_ref(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Featured Image',
            'code' => 'article_featured',
        ]);

        $path = $this->service->createPath($blueprint, [
            'name' => 'featured_image',
            'data_type' => 'ref',
            'cardinality' => 'one',
            'is_required' => false,
            'is_indexed' => true,
        ]);

        $this->assertEquals('featured_image', $path->name);
        $this->assertEquals('featured_image', $path->full_path);
        $this->assertEquals('ref', $path->data_type);
        $this->assertEquals('one', $path->cardinality);
        $this->assertTrue($path->is_indexed);
    }

    /**
     * Схема 7: Ссылка на массив Entry (data_type: ref, cardinality: many).
     */
    public function test_schema_7_array_of_entry_references_ref_many(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Related',
            'code' => 'article_related',
        ]);

        $path = $this->service->createPath($blueprint, [
            'name' => 'related_articles',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_required' => false,
            'is_indexed' => true,
        ]);

        $this->assertEquals('related_articles', $path->name);
        $this->assertEquals('related_articles', $path->full_path);
        $this->assertEquals('ref', $path->data_type);
        $this->assertEquals('many', $path->cardinality);
        $this->assertTrue($path->is_indexed);
    }

    /**
     * Схема 8: Скопированное поле (is_readonly: true).
     */
    public function test_schema_8_copied_field_readonly(): void
    {
        // Создаём два blueprint'а
        $sourceBlueprint = $this->service->createBlueprint([
            'name' => 'Contact Info',
            'code' => 'contact_info',
        ]);

        $hostBlueprint = $this->service->createBlueprint([
            'name' => 'Article',
            'code' => 'article',
        ]);

        // Создаём path в source blueprint
        $authorPath = $this->service->createPath($sourceBlueprint, [
            'name' => 'author',
            'data_type' => 'json',
        ]);

        $contactsPath = $this->service->createPath($sourceBlueprint, [
            'name' => 'contacts',
            'data_type' => 'json',
            'parent_id' => $authorPath->id,
        ]);

        $phonePath = $this->service->createPath($sourceBlueprint, [
            'name' => 'phone',
            'data_type' => 'string',
            'parent_id' => $contactsPath->id,
        ]);

        // Создаём embed для материализации
        $this->service->createEmbed($hostBlueprint, $sourceBlueprint, null);

        // Проверяем, что скопированные paths созданы
        $hostPaths = $hostBlueprint->paths()->get();
        $copiedPhonePath = $hostPaths->firstWhere('full_path', 'author.contacts.phone');

        $this->assertNotNull($copiedPhonePath);
        $this->assertEquals('author.contacts.phone', $copiedPhonePath->full_path);
        $this->assertTrue($copiedPhonePath->is_readonly);
        $this->assertEquals($sourceBlueprint->id, $copiedPhonePath->source_blueprint_id);
    }

    /**
     * Схема 9: Многоуровневая вложенность.
     */
    public function test_schema_9_multi_level_nesting(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Article with Complex Content',
            'code' => 'article_complex',
        ]);

        // Создаём content.sections[].blocks[].type
        $contentPath = $this->service->createPath($blueprint, [
            'name' => 'content',
            'data_type' => 'json',
        ]);

        $sectionsPath = $this->service->createPath($blueprint, [
            'name' => 'sections',
            'data_type' => 'json',
            'cardinality' => 'many',
            'parent_id' => $contentPath->id,
        ]);

        $titlePath = $this->service->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'parent_id' => $sectionsPath->id,
            'is_required' => true,
        ]);

        $blocksPath = $this->service->createPath($blueprint, [
            'name' => 'blocks',
            'data_type' => 'json',
            'cardinality' => 'many',
            'parent_id' => $sectionsPath->id,
        ]);

        $typePath = $this->service->createPath($blueprint, [
            'name' => 'type',
            'data_type' => 'string',
            'parent_id' => $blocksPath->id,
            'is_required' => true,
        ]);

        $dataPath = $this->service->createPath($blueprint, [
            'name' => 'data',
            'data_type' => 'json',
            'parent_id' => $blocksPath->id,
        ]);

        $textPath = $this->service->createPath($blueprint, [
            'name' => 'text',
            'data_type' => 'text',
            'parent_id' => $dataPath->id,
        ]);

        // Проверка структуры
        $this->assertEquals('content', $contentPath->name);
        $this->assertEquals('content.sections', $sectionsPath->full_path);
        $this->assertEquals('many', $sectionsPath->cardinality);
        $this->assertEquals('content.sections.title', $titlePath->full_path);
        $this->assertEquals('content.sections.blocks', $blocksPath->full_path);
        $this->assertEquals('many', $blocksPath->cardinality);
        $this->assertEquals('content.sections.blocks.type', $typePath->full_path);
        $this->assertEquals('content.sections.blocks.data', $dataPath->full_path);
        $this->assertEquals('content.sections.blocks.data.text', $textPath->full_path);

        // Проверка глубины
        $maxDepth = $blueprint->paths()->get()->max(fn($p) => substr_count($p->full_path, '.'));
        $this->assertGreaterThanOrEqual(4, $maxDepth, 'Should have paths with 4+ dots (5 levels depth)');
    }

    /**
     * Схема 10: Смешанная структура (все типы данных).
     */
    public function test_schema_10_mixed_structure_all_data_types(): void
    {
        $blueprint = $this->service->createBlueprint([
            'name' => 'Complete Article',
            'code' => 'complete_article',
        ]);

        // Создаём группу article
        $articlePath = $this->service->createPath($blueprint, [
            'name' => 'article',
            'data_type' => 'json',
        ]);

        // string
        $titlePath = $this->service->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'parent_id' => $articlePath->id,
            'is_required' => true,
        ]);

        // text
        $contentPath = $this->service->createPath($blueprint, [
            'name' => 'content',
            'data_type' => 'text',
            'parent_id' => $articlePath->id,
        ]);

        // bool
        $publishedPath = $this->service->createPath($blueprint, [
            'name' => 'published',
            'data_type' => 'bool',
            'parent_id' => $articlePath->id,
        ]);

        // int
        $viewsPath = $this->service->createPath($blueprint, [
            'name' => 'views',
            'data_type' => 'int',
            'parent_id' => $articlePath->id,
        ]);

        // float
        $ratingPath = $this->service->createPath($blueprint, [
            'name' => 'rating',
            'data_type' => 'float',
            'parent_id' => $articlePath->id,
            'validation_rules' => [
                'min' => 0,
                'max' => 5,
            ],
        ]);

        // datetime
        $publishedAtPath = $this->service->createPath($blueprint, [
            'name' => 'published_at',
            'data_type' => 'datetime',
            'parent_id' => $articlePath->id,
        ]);

        // date
        $createdDatePath = $this->service->createPath($blueprint, [
            'name' => 'created_date',
            'data_type' => 'date',
            'parent_id' => $articlePath->id,
        ]);

        // ref
        $authorPath = $this->service->createPath($blueprint, [
            'name' => 'author',
            'data_type' => 'ref',
            'parent_id' => $articlePath->id,
        ]);

        // string many
        $tagsPath = $this->service->createPath($blueprint, [
            'name' => 'tags',
            'data_type' => 'string',
            'cardinality' => 'many',
            'parent_id' => $articlePath->id,
        ]);

        // json группа
        $metadataPath = $this->service->createPath($blueprint, [
            'name' => 'metadata',
            'data_type' => 'json',
            'parent_id' => $articlePath->id,
        ]);

        $seoTitlePath = $this->service->createPath($blueprint, [
            'name' => 'seo_title',
            'data_type' => 'string',
            'parent_id' => $metadataPath->id,
        ]);

        $seoDescriptionPath = $this->service->createPath($blueprint, [
            'name' => 'seo_description',
            'data_type' => 'text',
            'parent_id' => $metadataPath->id,
        ]);

        // Проверка структуры
        $children = $blueprint->paths()->where('parent_id', $articlePath->id)->get();
        $this->assertCount(10, $children);

        // Проверяем все типы данных
        $this->assertEquals('string', $titlePath->data_type);
        $this->assertEquals('text', $contentPath->data_type);
        $this->assertEquals('bool', $publishedPath->data_type);
        $this->assertEquals('int', $viewsPath->data_type);
        $this->assertEquals('float', $ratingPath->data_type);
        $this->assertEquals(['min' => 0, 'max' => 5], $ratingPath->validation_rules);
        $this->assertEquals('datetime', $publishedAtPath->data_type);
        $this->assertEquals('date', $createdDatePath->data_type);
        $this->assertEquals('ref', $authorPath->data_type);
        $this->assertEquals('string', $tagsPath->data_type);
        $this->assertEquals('many', $tagsPath->cardinality);
        $this->assertEquals('json', $metadataPath->data_type);

        $metadataChildren = $blueprint->paths()->where('parent_id', $metadataPath->id)->get();
        $this->assertCount(2, $metadataChildren);
        $this->assertTrue($metadataChildren->contains('name', 'seo_title'));
        $this->assertTrue($metadataChildren->contains('name', 'seo_description'));
    }
}
