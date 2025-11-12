<?php

namespace Tests\Unit;

use App\Domain\View\BladeTemplateResolver;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    private BladeTemplateResolver $resolver;
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new BladeTemplateResolver(
            default: 'pages.show',
        );

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'template' => null,
            'options_json' => [],
        ]);
    }

    public function test_returns_entry_template_override_when_set(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'about',
            'status' => 'draft',
            'template_override' => 'templates.custom',
            'data_json' => [],
        ]);

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('templates.custom', $result);
    }

    public function test_returns_post_type_template_when_entry_override_not_set(): void
    {
        $this->postType->update(['template' => 'pages.types.article']);

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.types.article', $result);
    }

    public function test_returns_default_when_both_not_set(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.show', $result);
    }

    public function test_entry_override_has_priority_over_post_type_template(): void
    {
        $this->postType->update(['template' => 'pages.types.article']);

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => 'templates.special',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('templates.special', $result);
        $this->assertNotEquals('pages.types.article', $result);
    }

    public function test_handles_missing_post_type_relation(): void
    {
        $this->postType->update(['template' => 'pages.types.blog']);

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);

        // Entry без загруженной связи postType
        // Должен запросить template из БД через value('template')
        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.types.blog', $result);
        $this->assertFalse($entry->relationLoaded('postType'), 
            'Связь postType не должна быть загружена через lazy loading');
    }

    public function test_returns_default_when_post_type_template_empty_string(): void
    {
        $this->postType->update(['template' => '']);

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.show', $result);
    }

    public function test_returns_default_when_entry_override_empty_string(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => '',
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $this->resolver->forEntry($entry);

        $this->assertEquals('pages.show', $result);
    }

    public function test_uses_custom_default_template(): void
    {
        $resolver = new BladeTemplateResolver(
            default: 'custom.default',
        );

        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'draft',
            'template_override' => null,
            'data_json' => [],
        ]);
        $entry->load('postType');

        $result = $resolver->forEntry($entry);

        $this->assertEquals('custom.default', $result);
    }
}
