<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Models\PostType;
use App\Domain\Pages\Validation\UniquePageSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UniquePageSlugRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Создаём PostType 'page' для тестов
        PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    public function test_passes_returns_true_when_slug_is_unique(): void
    {
        $rule = new UniquePageSlug();
        $rule->setData([]);

        $this->assertTrue($rule->passes('slug', 'unique-page-slug'));
    }

    public function test_passes_returns_false_when_slug_exists(): void
    {
        // Создаём Page с slug
        Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Existing Page',
            'slug' => 'existing-slug',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $rule = new UniquePageSlug();
        $rule->setData([]);

        $this->assertFalse($rule->passes('slug', 'existing-slug'));
    }

    public function test_passes_returns_true_when_updating_same_entry(): void
    {
        // Создаём Page
        $entry = Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'My Page',
            'slug' => 'my-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // При обновлении той же записи правило должно пройти
        $rule = new UniquePageSlug();
        $rule->setData(['id' => $entry->id]);

        $this->assertTrue($rule->passes('slug', 'my-page'));
    }

    public function test_passes_returns_false_when_updating_to_existing_slug(): void
    {
        // Создаём две страницы
        $entry1 = Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Page 1',
            'slug' => 'page-1',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $entry2 = Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'draft',
            'data_json' => [],
        ]);

        // Пытаемся обновить entry2, используя slug entry1
        $rule = new UniquePageSlug();
        $rule->setData(['id' => $entry2->id]);

        $this->assertFalse($rule->passes('slug', 'page-1'));
    }

    public function test_passes_normalizes_slug_to_lowercase(): void
    {
        // Создаём Page с lowercase slug
        Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $rule = new UniquePageSlug();
        $rule->setData([]);

        // Проверяем, что uppercase slug считается дублем
        $this->assertFalse($rule->passes('slug', 'TEST-PAGE'));
    }

    public function test_passes_collapses_multiple_dashes(): void
    {
        // Создаём Page
        Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $rule = new UniquePageSlug();
        $rule->setData([]);

        // Проверяем, что slug с множественными дефисами нормализуется
        $this->assertFalse($rule->passes('slug', 'test---page'));
    }

    public function test_message_returns_localized_string(): void
    {
        $rule = new UniquePageSlug();
        $message = $rule->message();

        $this->assertStringContainsString('URL уже используется', $message);
    }

    public function test_validator_integration(): void
    {
        // Создаём Page
        Entry::create([
            'post_type_id' => PostType::where('slug', 'page')->first()->id,
            'title' => 'Page',
            'slug' => 'taken-slug',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $validator = Validator::make(
            ['slug' => 'taken-slug'],
            ['slug' => [new UniquePageSlug()]]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }
}

