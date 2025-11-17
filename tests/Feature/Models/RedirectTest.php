<?php

declare(strict_types=1);

use App\Models\Redirect;

/**
 * Feature-тесты для модели Redirect.
 */

test('redirect can be created', function () {
    $redirect = Redirect::create([
        'from_path' => '/old-page',
        'to_path' => '/new-page',
        'code' => 301,
    ]);

    expect($redirect)->toBeInstanceOf(Redirect::class)
        ->and($redirect->exists)->toBeTrue();

    $this->assertDatabaseHas('redirects', [
        'id' => $redirect->id,
        'from_path' => '/old-page',
        'to_path' => '/new-page',
        'code' => 301,
    ]);
});

test('redirect supports 301 permanent redirect', function () {
    $redirect = Redirect::create([
        'from_path' => '/old',
        'to_path' => '/new',
        'code' => 301,
    ]);

    expect($redirect->code)->toBe(301);
});

test('redirect supports 302 temporary redirect', function () {
    $redirect = Redirect::create([
        'from_path' => '/temp-old',
        'to_path' => '/temp-new',
        'code' => 302,
    ]);

    expect($redirect->code)->toBe(302);
});

