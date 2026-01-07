<?php

declare(strict_types=1);

use App\Services\DynamicRoutes\RoutePatternNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->normalizer = new RoutePatternNormalizer();
});

test('normalize() преобразует параметры в {param}', function () {
    expect($this->normalizer->normalize('/products/{id}'))->toBe('/products/{param}')
        ->and($this->normalizer->normalize('/{slug}'))->toBe('/{param}')
        ->and($this->normalizer->normalize('/pages/{parent}/{slug}'))->toBe('/pages/{param}/{param}')
        ->and($this->normalizer->normalize('/products/{id}/reviews'))->toBe('/products/{param}/reviews');
});

test('normalize() обрабатывает URI без ведущего слэша', function () {
    expect($this->normalizer->normalize('products/{id}'))->toBe('/products/{param}')
        ->and($this->normalizer->normalize('{slug}'))->toBe('/{param}');
});

test('patternsConflict() определяет конфликт для одинаковых паттернов', function () {
    expect($this->normalizer->patternsConflict('/{slug}', '/{id}'))->toBeTrue()
        ->and($this->normalizer->patternsConflict('/products/{id}', '/products/{slug}'))->toBeTrue()
        ->and($this->normalizer->patternsConflict('/pages/{parent}/{slug}', '/pages/{category}/{id}'))->toBeTrue();
});

test('patternsConflict() не определяет конфликт для разных префиксов', function () {
    expect($this->normalizer->patternsConflict('/products/{id}', '/pages/{id}'))->toBeFalse()
        ->and($this->normalizer->patternsConflict('/blog/{slug}', '/shop/{slug}'))->toBeFalse();
});

test('patternsConflict() не определяет конфликт для разного количества сегментов', function () {
    expect($this->normalizer->patternsConflict('/{slug}', '/products/{id}'))->toBeFalse()
        ->and($this->normalizer->patternsConflict('/products/{id}', '/products/{id}/reviews'))->toBeFalse();
});

test('patternsConflict() определяет конфликт для статических путей', function () {
    expect($this->normalizer->patternsConflict('/about', '/about'))->toBeTrue()
        ->and($this->normalizer->patternsConflict('/contact', '/contact'))->toBeTrue();
});

test('patternsConflict() не определяет конфликт для разных статических путей', function () {
    expect($this->normalizer->patternsConflict('/about', '/contact'))->toBeFalse()
        ->and($this->normalizer->patternsConflict('/products', '/pages'))->toBeFalse();
});

test('extractSegments() разбивает URI на сегменты', function () {
    expect($this->normalizer->extractSegments('/products/{id}'))->toBe(['products', '{param}'])
        ->and($this->normalizer->extractSegments('/{slug}'))->toBe(['{param}'])
        ->and($this->normalizer->extractSegments('/pages/{parent}/{slug}'))->toBe(['pages', '{param}', '{param}'])
        ->and($this->normalizer->extractSegments(''))->toBe([]);
});

