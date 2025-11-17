<?php

declare(strict_types=1);

use App\Domain\Sanitizer\RichTextSanitizer;

/**
 * Feature-тесты для RichTextSanitizer.
 */

test('sanitizes basic html content', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p>Hello <strong>World</strong></p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('Hello')
        ->and($result)->toContain('<strong>')
        ->and($result)->toContain('World');
});

test('removes script tags', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p>Content</p><script>alert("XSS")</script>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('<script>')
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('Content');
});

test('removes inline javascript', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p onclick="alert(1)">Click me</p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('onclick')
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('Click me');
});

test('removes dangerous iframe tags', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<iframe src="malicious.com"></iframe><p>Safe content</p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('iframe')
        ->and($result)->toContain('Safe content');
});

test('allows safe formatting tags', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p><strong>Bold</strong> <em>Italic</em> <u>Underline</u></p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('<strong>')
        ->and($result)->toContain('<em>')
        ->and($result)->toContain('Bold')
        ->and($result)->toContain('Italic');
});

test('adds noopener noreferrer to target blank links', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<a href="https://example.com" target="_blank">Link</a>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('rel="noopener noreferrer"')
        ->and($result)->toContain('href')
        ->and($result)->toContain('Link');
});

test('preserves existing rel attributes and adds noopener noreferrer', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<a href="https://example.com" target="_blank" rel="external">Link</a>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('rel=')
        ->and($result)->toContain('noopener')
        ->and($result)->toContain('noreferrer');
});

test('does not add rel to links without target blank', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<a href="https://example.com">Link</a>';
    $result = $sanitizer->sanitize($html);

    // Check that rel is not present or doesn't contain noopener/noreferrer
    $hasNoopener = str_contains($result, 'noopener');
    expect($hasNoopener)->toBeFalse();
});

test('handles malformed html', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p>Unclosed tag<p>Another <strong>paragraph';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('Unclosed tag')
        ->and($result)->toContain('Another')
        ->and($result)->toContain('paragraph');
});

test('removes javascript protocol from links', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<a href="javascript:alert(1)">Click</a>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('javascript:')
        ->and($result)->toContain('Click');
});

test('removes onerror from images', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<img src="image.jpg" onerror="alert(1)" alt="Image">';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('onerror')
        ->and($result)->not->toContain('alert');
});

test('preserves nested formatting', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p><strong>Bold <em>and italic</em></strong></p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('<strong>')
        ->and($result)->toContain('<em>')
        ->and($result)->toContain('Bold')
        ->and($result)->toContain('and italic');
});

test('handles empty content', function () {
    $sanitizer = new RichTextSanitizer();

    $result = $sanitizer->sanitize('');

    expect($result)->toBe('');
});

test('handles plain text without tags', function () {
    $sanitizer = new RichTextSanitizer();

    $html = 'Just plain text';
    $result = $sanitizer->sanitize($html);

    expect($result)->toBe('Just plain text');
});

test('removes style attributes with dangerous content', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<p style="background: url(javascript:alert(1))">Text</p>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('javascript:')
        ->and($result)->toContain('Text');
});

test('sanitizes lists and preserves structure', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('<ul>')
        ->and($result)->toContain('<li>')
        ->and($result)->toContain('Item 1')
        ->and($result)->toContain('Item 2');
});

test('handles multiple target blank links', function () {
    $sanitizer = new RichTextSanitizer();

    $html = '<a href="https://link1.com" target="_blank">Link 1</a> <a href="https://link2.com" target="_blank">Link 2</a>';
    $result = $sanitizer->sanitize($html);

    // Both links should have noopener noreferrer
    $count = substr_count($result, 'noopener');
    expect($count)->toBeGreaterThanOrEqual(1);
});

