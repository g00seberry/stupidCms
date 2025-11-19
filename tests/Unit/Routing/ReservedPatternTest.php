<?php

declare(strict_types=1);

use App\Domain\Routing\ReservedPattern;
use Tests\TestCase;

/**
 * Unit-тесты для ReservedPattern.
 */

uses(TestCase::class);

test('generates slug regex pattern', function () {
    $pattern = ReservedPattern::slugRegex();

    expect($pattern)->toBeString()
        ->and($pattern)->toContain('^')
        ->and($pattern)->toContain('$');
});

test('slug regex matches valid slug', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", 'valid-slug'))->toBe(1)
        ->and(preg_match("#{$pattern}#", 'slug123'))->toBe(1)
        ->and(preg_match("#{$pattern}#", 'my-page'))->toBe(1);
});

test('slug regex rejects invalid characters', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", 'Invalid_Slug'))->toBe(0)
        ->and(preg_match("#{$pattern}#", 'slug with spaces'))->toBe(0)
        ->and(preg_match("#{$pattern}#", 'slug/with/slash'))->toBe(0);
});

test('slug regex rejects trailing dash', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", 'slug-'))->toBe(0);
});

test('slug regex rejects leading dash', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", '-slug'))->toBe(0);
});

test('slug regex allows dash in middle', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", 'my-slug-here'))->toBe(1);
});

test('slug regex rejects uppercase', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", 'MySlug'))->toBe(0)
        ->and(preg_match("#{$pattern}#", 'SLUG'))->toBe(0);
});

test('slug regex rejects empty string', function () {
    $pattern = ReservedPattern::slugRegex();

    expect(preg_match("#{$pattern}#", ''))->toBe(0);
});

test('slug regex may include negative lookahead for reserved paths', function () {
    $pattern = ReservedPattern::slugRegex();

    // Pattern should start with ^ anchor
    expect($pattern)->toStartWith('^');
    
    // May contain (?! for negative lookahead if reserved paths are configured
    // This is environment-dependent (config + DB state)
});

