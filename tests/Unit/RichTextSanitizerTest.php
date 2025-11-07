<?php

namespace Tests\Unit;

use App\Domain\Sanitizer\RichTextSanitizer;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    private RichTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = app(RichTextSanitizer::class);
    }

    public function test_script_removed_and_anchor_kept(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><a href="https://example.com">x</a>';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<a href="https://example.com"', $clean);
    }

    public function test_target_blank_gets_rel_noopener(): void
    {
        $html = '<a href="https://ex.com" target="_blank">ex</a>';
        $clean = $this->sanitizer->sanitize($html);
        // После пост-обработки должен появиться rel="noopener noreferrer"
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
    }

    public function test_target_blank_appends_noopener_when_rel_exists(): void
    {
        $html = '<a href="https://ex.com" target="_blank" rel="nofollow">ex</a>';
        $clean = $this->sanitizer->sanitize($html);
        // Если уже есть rel, добавляем недостающие noopener и noreferrer
        // HTMLPurifier может удалить rel="nofollow", но если rel сохранился, добавляем noopener
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
        // Проверяем, что если rel есть, то он содержит noopener и noreferrer
        if (preg_match('#rel\s*=\s*"([^"]*)"#i', $clean, $matches)) {
            $relTokens = array_map('strtolower', preg_split('/\s+/', trim($matches[1])));
            $this->assertContains('noopener', $relTokens);
            $this->assertContains('noreferrer', $relTokens);
        } else {
            // Если rel удален HTMLPurifier, наш код добавит noopener
            $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        }
    }

    public function test_rel_not_added_when_no_target_blank(): void
    {
        // Ссылки без target="_blank" не должны получать rel="noopener noreferrer"
        $html = '<a href="https://ex.com">Link 1</a><a href="https://other.com" target="_blank">Link 2</a>';
        $clean = $this->sanitizer->sanitize($html);
        
        // Первая ссылка не должна иметь rel="noopener noreferrer"
        $this->assertStringContainsString('<a href="https://ex.com"', $clean);
        // Проверяем, что первая ссылка не имеет rel="noopener noreferrer"
        if (preg_match('#<a\b[^>]*href\s*=\s*"https://ex\.com"[^>]*>#i', $clean, $match)) {
            $this->assertStringNotContainsString('rel="noopener noreferrer"', $match[0]);
        }
        
        // Вторая ссылка должна иметь rel="noopener noreferrer"
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_onclick_attribute_removed(): void
    {
        $html = '<p onclick="alert(1)">Hello</p>';
        $clean = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function test_allowed_tags_preserved(): void
    {
        $html = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p><ul><li>Item 1</li><li>Item 2</li></ul>';
        $clean = $this->sanitizer->sanitize($html);
        
        $this->assertStringContainsString('<h1>', $clean);
        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        $this->assertStringContainsString('<em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>', $clean);
    }

    public function test_javascript_scheme_removed(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $clean = $this->sanitizer->sanitize($html);
        // JavaScript схемы должны быть удалены или заменены
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_http_https_schemes_allowed(): void
    {
        $html = '<a href="https://example.com">HTTPS</a><a href="http://example.com">HTTP</a>';
        $clean = $this->sanitizer->sanitize($html);
        
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('href="http://example.com"', $clean);
    }

    public function test_mailto_scheme_allowed(): void
    {
        $html = '<a href="mailto:test@example.com">Email</a>';
        $clean = $this->sanitizer->sanitize($html);
        $this->assertStringContainsString('href="mailto:test@example.com"', $clean);
    }

    public function test_img_tag_with_allowed_attributes(): void
    {
        $html = '<img src="https://example.com/image.jpg" alt="Image" title="Title" width="100" height="100">';
        $clean = $this->sanitizer->sanitize($html);
        
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $clean);
        $this->assertStringContainsString('alt="Image"', $clean);
    }

    public function test_empty_string_returns_empty(): void
    {
        $clean = $this->sanitizer->sanitize('');
        $this->assertEquals('', $clean);
    }

    public function test_html5_figure_and_figcaption_preserved(): void
    {
        // Проверяем, что custom_definition реально работает и figure/figcaption сохраняются
        $html = '<figure><img src="https://example.com/image.jpg" alt="Image"><figcaption>Caption text</figcaption></figure>';
        $clean = $this->sanitizer->sanitize($html);
        
        // Проверяем, что figure и figcaption сохранились (custom_definition работает)
        $this->assertStringContainsString('<figure>', $clean);
        $this->assertStringContainsString('</figure>', $clean);
        $this->assertStringContainsString('<figcaption>', $clean);
        $this->assertStringContainsString('</figcaption>', $clean);
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('Caption text', $clean);
    }
}

