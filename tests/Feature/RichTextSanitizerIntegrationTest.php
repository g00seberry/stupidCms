<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\PostType;
use Tests\Support\FeatureTestCase;

class RichTextSanitizerIntegrationTest extends FeatureTestCase
{
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    public function test_entry_with_script_in_body_html_renders_safely(): void
    {
        // Создаем Entry с <script> в body_html
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<p>Hello</p><script>alert("XSS")</script><a href="https://example.com">Link</a>',
            ],
        ]);

        // Проверяем, что санитизированная версия создана
        $this->assertNotNull($entry->data_json['body_html_sanitized'] ?? null);
        
        // Проверяем, что скрипт удален из санитизированной версии
        $sanitized = $entry->data_json['body_html_sanitized'];
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        
        // Проверяем, что безопасные элементы сохранены
        $this->assertStringContainsString('<p>Hello</p>', $sanitized);
        $this->assertStringContainsString('<a href="https://example.com"', $sanitized);
        
        // Проверяем, что оригинальный body_html сохранен
        $this->assertStringContainsString('<script>alert("XSS")</script>', $entry->data_json['body_html']);
    }

    public function test_entry_with_target_blank_gets_rel_noopener(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<a href="https://example.com" target="_blank">Link</a>',
            ],
        ]);

        $sanitized = $entry->data_json['body_html_sanitized'];
        
        // Проверяем, что добавлен rel="noopener noreferrer"
        $this->assertStringContainsString('rel="noopener noreferrer"', $sanitized);
        $this->assertStringContainsString('<a href="https://example.com"', $sanitized);
    }

    public function test_entry_update_sanitizes_changed_data_json(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<p>Original</p>',
            ],
        ]);

        $originalSanitized = $entry->data_json['body_html_sanitized'];

        // Обновляем data_json
        $entry->update([
            'data_json' => [
                'body_html' => '<p>Updated<script>alert(1)</script></p>',
            ],
        ]);

        // Проверяем, что санитизированная версия обновилась
        $entry->refresh();
        $newSanitized = $entry->data_json['body_html_sanitized'];
        
        $this->assertNotEquals($originalSanitized, $newSanitized);
        $this->assertStringNotContainsString('<script', $newSanitized);
        $this->assertStringContainsString('<p>Updated</p>', $newSanitized);
    }

    public function test_entry_with_figure_figcaption_preserved_in_sanitized(): void
    {
        // Проверяем, что custom_definition работает и figure/figcaption сохраняются в *_sanitized
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Figure Page',
            'slug' => 'figure-page',
            'status' => 'published',
            'published_at' => now(),
            'data_json' => [
                'body_html' => '<figure><img src="https://example.com/image.jpg" alt="Image"><figcaption>Image caption</figcaption></figure>',
            ],
        ]);

        // Проверяем, что figure и figcaption сохранились в санитизированной версии
        $sanitized = $entry->data_json['body_html_sanitized'] ?? '';
        $this->assertStringContainsString('<figure>', $sanitized);
        $this->assertStringContainsString('</figure>', $sanitized);
        $this->assertStringContainsString('<figcaption>', $sanitized);
        $this->assertStringContainsString('</figcaption>', $sanitized);
        $this->assertStringContainsString('<img', $sanitized);
        $this->assertStringContainsString('Image caption', $sanitized);
        
        // Проверяем, что оригинал сохранен
        $this->assertStringContainsString('<figure>', $entry->data_json['body_html']);
    }
}

