<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Models\PostType;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UniqueEntrySlugRuleTest extends TestCase
{
    use RefreshDatabase;

    protected PostType $pagePostType;
    protected PostType $articlePostType;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Создаём PostType 'page' и 'article' для тестов
        $this->pagePostType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $this->articlePostType = PostType::create([
            'slug' => 'article',
            'name' => 'Article',
            'options_json' => [],
        ]);
    }

    public function test_passes_when_slug_is_unique(): void
    {
        $rule = new UniqueEntrySlug('page');

        $validator = Validator::make(
            ['slug' => 'unique-page-slug'],
            ['slug' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_slug_exists_in_same_post_type(): void
    {
        // Создаём Page с slug
        Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'Existing Page',
            'slug' => 'existing-slug',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $rule = new UniqueEntrySlug('page');

        $validator = Validator::make(
            ['slug' => 'existing-slug'],
            ['slug' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('already taken', $validator->errors()->first('slug'));
    }

    public function test_passes_when_updating_same_entry(): void
    {
        // Создаём Page
        $entry = Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'My Page',
            'slug' => 'my-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // При обновлении той же записи правило должно пройти
        $rule = new UniqueEntrySlug('page', $entry->id);

        $validator = Validator::make(
            ['slug' => 'my-page'],
            ['slug' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_updating_to_existing_slug(): void
    {
        // Создаём две страницы
        Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'Page 1',
            'slug' => 'page-1',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $entry2 = Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Пытаемся обновить entry2, используя slug entry1
        $rule = new UniqueEntrySlug('page', $entry2->id);

        $validator = Validator::make(
            ['slug' => 'page-1'],
            ['slug' => [$rule]]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_slug_can_be_reused_across_different_post_types(): void
    {
        // Создаём Page с slug 'test'
        Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'Test Page',
            'slug' => 'test',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Проверяем, что тот же slug можно использовать для Article
        $rule = new UniqueEntrySlug('article');

        $validator = Validator::make(
            ['slug' => 'test'],
            ['slug' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_includes_soft_deleted_entries(): void
    {
        // Создаём и удаляем Page
        $entry = Entry::create([
            'post_type_id' => $this->pagePostType->id,
            'title' => 'Deleted Page',
            'slug' => 'deleted-slug',
            'status' => 'draft',
            'data_json' => [],
        ]);
        $entry->delete();

        // Проверяем, что slug soft-deleted записи считается занятым
        $rule = new UniqueEntrySlug('page');

        $validator = Validator::make(
            ['slug' => 'deleted-slug'],
            ['slug' => [$rule]]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_fails_when_post_type_does_not_exist(): void
    {
        $rule = new UniqueEntrySlug('nonexistent-type');

        $validator = Validator::make(
            ['slug' => 'test-slug'],
            ['slug' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('post type does not exist', $validator->errors()->first('slug'));
    }

    public function test_ignores_empty_slug(): void
    {
        $rule = new UniqueEntrySlug('page');

        $validator = Validator::make(
            ['slug' => ''],
            ['slug' => [$rule]]
        );

        // Empty slug should pass (other rules like 'required' handle emptiness)
        $this->assertTrue($validator->passes());
    }
}

