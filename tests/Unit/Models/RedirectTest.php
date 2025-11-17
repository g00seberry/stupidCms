<?php

declare(strict_types=1);

use App\Models\Redirect;

/**
 * Unit-тесты для модели Redirect.
 */

test('stores redirect rules', function () {
    $redirect = new Redirect([
        'from_path' => '/old-path',
        'to_path' => '/new-path',
        'code' => 301,
    ]);

    expect($redirect->from_path)->toBe('/old-path')
        ->and($redirect->to_path)->toBe('/new-path')
        ->and($redirect->code)->toBe(301);
});

test('supports different http status codes', function () {
    $redirect301 = new Redirect(['code' => 301]);
    $redirect302 = new Redirect(['code' => 302]);

    expect($redirect301->code)->toBe(301)
        ->and($redirect302->code)->toBe(302);
});

test('has no guarded attributes', function () {
    $redirect = new Redirect();

    expect($redirect->getGuarded())->toBe([]);
});

