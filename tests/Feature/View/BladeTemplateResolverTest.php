<?php

declare(strict_types=1);

use App\Domain\View\BladeTemplateResolver;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Feature-тесты для BladeTemplateResolver.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->postType = PostType::factory()->create(['slug' => 'article']);
});

test('returns default template when no specific templates exist', function () {
    $resolver = new BladeTemplateResolver('entry');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'test-article',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('entry');
});

test('uses template override when specified', function () {
    // Create a temporary view for testing
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/custom-template.blade.php', '<div>Custom</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'custom-template',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('custom-template');
    
    // Cleanup
    unlink(__DIR__ . '/custom-template.blade.php');
});

test('throws exception when template override does not exist', function () {
    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'nonexistent-template',
    ]);

    $resolver->forEntry($entry);
})->throws(InvalidArgumentException::class, 'Template override');

test('uses post type specific template when it exists', function () {
    // Create a temporary view
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/entry--article.blade.php', '<div>Article</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'test-slug',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('entry--article');
    
    // Cleanup
    unlink(__DIR__ . '/entry--article.blade.php');
});

test('uses entry specific template when it exists', function () {
    // Create temporary views
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/entry--article--special.blade.php', '<div>Special</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'special',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('entry--article--special');
    
    // Cleanup
    unlink(__DIR__ . '/entry--article--special.blade.php');
});

test('template override has highest priority', function () {
    // Create all possible templates
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/override.blade.php', '<div>Override</div>');
    file_put_contents(__DIR__ . '/entry--article--test.blade.php', '<div>Specific</div>');
    file_put_contents(__DIR__ . '/entry--article.blade.php', '<div>Type</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'test',
        'template_override' => 'override',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('override');
    
    // Cleanup
    unlink(__DIR__ . '/override.blade.php');
    unlink(__DIR__ . '/entry--article--test.blade.php');
    unlink(__DIR__ . '/entry--article.blade.php');
});

test('entry specific template has priority over post type template', function () {
    // Create both templates
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/entry--article--priority.blade.php', '<div>Specific</div>');
    file_put_contents(__DIR__ . '/entry--article.blade.php', '<div>Type</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'priority',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('entry--article--priority');
    
    // Cleanup
    unlink(__DIR__ . '/entry--article--priority.blade.php');
    unlink(__DIR__ . '/entry--article.blade.php');
});

test('can use custom default template', function () {
    $resolver = new BladeTemplateResolver('custom-default');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('custom-default');
});

test('handles entry with loaded post type relationship', function () {
    View::addLocation(__DIR__);
    file_put_contents(__DIR__ . '/entry--article.blade.php', '<div>Article</div>');

    $resolver = new BladeTemplateResolver();
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    // Load relationship
    $entry->load('postType');

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('entry--article');
    
    // Cleanup
    unlink(__DIR__ . '/entry--article.blade.php');
});

test('handles entry without post type slug', function () {
    $resolver = new BladeTemplateResolver('fallback');
    
    // Create entry without post type
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    // Manually unset the post_type_id to simulate missing post type
    // (This is edge case testing)
    $entry->post_type_id = null;

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('fallback');
});

