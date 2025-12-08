<?php

declare(strict_types=1);

use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplatePathValidator;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Feature-тесты для BladeTemplateResolver.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->postType = PostType::factory()->create(['name' => 'Article']);
    $this->validator = new TemplatePathValidator();
});

test('returns default template when no specific templates exist', function () {
    $resolver = new BladeTemplateResolver($this->validator, 'templates.index');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.index');
});

test('uses template override when specified', function () {
    // Create a temporary view for testing
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/custom-template.blade.php', '<div>Custom</div>');

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'templates.custom-template',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.custom-template');
    
    // Cleanup
    unlink($templatesDir . '/custom-template.blade.php');
});

test('template override is normalized and prefixed with templates', function () {
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/normalized.blade.php', '<div>Normalized</div>');

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'normalized', // Without templates. prefix
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.normalized');
    
    // Cleanup
    unlink($templatesDir . '/normalized.blade.php');
});

test('throws exception when template override does not exist', function () {
    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'templates.nonexistent-template',
    ]);

    $resolver->forEntry($entry);
})->throws(InvalidArgumentException::class, 'Template override');

test('uses post type template when specified', function () {
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/article.blade.php', '<div>Article</div>');

    $postType = PostType::factory()->create([
        'name' => 'Article',
        'template' => 'templates.article',
    ]);

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.article');
    
    // Cleanup
    unlink($templatesDir . '/article.blade.php');
});

test('post type template is normalized and prefixed with templates', function () {
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/product.blade.php', '<div>Product</div>');

    $postType = PostType::factory()->create([
        'name' => 'Product',
        'template' => 'product', // Without templates. prefix
    ]);

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.product');
    
    // Cleanup
    unlink($templatesDir . '/product.blade.php');
});

test('template override has highest priority over post type template', function () {
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/override.blade.php', '<div>Override</div>');
    file_put_contents($templatesDir . '/article.blade.php', '<div>Article</div>');

    $postType = PostType::factory()->create([
        'name' => 'Article',
        'template' => 'templates.article',
    ]);

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'templates.override',
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.override');
    
    // Cleanup
    unlink($templatesDir . '/override.blade.php');
    unlink($templatesDir . '/article.blade.php');
});

test('uses default template when post type template is not set', function () {
    $postType = PostType::factory()->create([
        'name' => 'Article',
        'template' => null,
    ]);

    $resolver = new BladeTemplateResolver($this->validator, 'templates.index');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.index');
});

test('can use custom default template', function () {
    $resolver = new BladeTemplateResolver($this->validator, 'templates.custom-default');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.custom-default');
});

test('handles entry with loaded post type relationship', function () {
    View::addNamespace('templates', resource_path('views/templates'));
    $templatesDir = resource_path('views/templates');
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0755, true);
    }
    file_put_contents($templatesDir . '/article.blade.php', '<div>Article</div>');

    $postType = PostType::factory()->create([
        'name' => 'Article',
        'template' => 'templates.article',
    ]);

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
    ]);
    
    // Load relationship
    $entry->load('postType');

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.article');
    
    // Cleanup
    unlink($templatesDir . '/article.blade.php');
});

test('handles entry without post type', function () {
    $resolver = new BladeTemplateResolver($this->validator, 'templates.fallback');
    
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    // Manually unset the post_type_id to simulate missing post type
    $entry->post_type_id = null;

    $template = $resolver->forEntry($entry);

    expect($template)->toBe('templates.fallback');
});

test('throws exception when post type template does not exist', function () {
    $postType = PostType::factory()->create([
        'name' => 'Article',
        'template' => 'templates.nonexistent',
    ]);

    $resolver = new BladeTemplateResolver($this->validator);
    
    $entry = Entry::factory()->create([
        'post_type_id' => $postType->id,
        'author_id' => $this->user->id,
    ]);

    $resolver->forEntry($entry);
})->throws(InvalidArgumentException::class, 'Template');
