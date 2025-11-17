<?php

declare(strict_types=1);

use App\Models\ReservedRoute;
use App\Rules\ReservedSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('passes for non-reserved slug', function () {
    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'regular-slug'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for reserved path', function () {
    ReservedRoute::create([
        'path' => 'admin',
        'kind' => 'path',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'admin'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('slug'))->toContain('conflicts with a reserved route');
});

test('fails for reserved prefix', function () {
    ReservedRoute::create([
        'path' => 'api',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'api/v1'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for prefix if slug does not start with it', function () {
    ReservedRoute::create([
        'path' => 'api',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'apex-legends'],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles slug with leading slash', function () {
    ReservedRoute::create([
        'path' => '/admin',
        'kind' => 'path',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => '/admin'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('case insensitive comparison', function () {
    ReservedRoute::create([
        'path' => 'admin',
        'kind' => 'path',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'ADMIN'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for empty slug', function () {
    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => ''],
        ['slug' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for prefix exact match', function () {
    ReservedRoute::create([
        'path' => 'api',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    $rule = new ReservedSlug();
    
    $validator = Validator::make(
        ['slug' => 'api'],
        ['slug' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('handles multiple reserved routes', function () {
    ReservedRoute::create(['path' => 'admin', 'kind' => 'path', 'source' => 'system']);
    ReservedRoute::create(['path' => 'api', 'kind' => 'prefix', 'source' => 'system']);
    ReservedRoute::create(['path' => 'wp-admin', 'kind' => 'path', 'source' => 'legacy']);

    $rule = new ReservedSlug();
    
    // Check each reserved route
    $adminValidator = Validator::make(['slug' => 'admin'], ['slug' => [$rule]]);
    $apiValidator = Validator::make(['slug' => 'api/test'], ['slug' => [$rule]]);
    $wpValidator = Validator::make(['slug' => 'wp-admin'], ['slug' => [$rule]]);
    $regularValidator = Validator::make(['slug' => 'blog'], ['slug' => [$rule]]);

    expect($adminValidator->fails())->toBeTrue()
        ->and($apiValidator->fails())->toBeTrue()
        ->and($wpValidator->fails())->toBeTrue()
        ->and($regularValidator->passes())->toBeTrue();
});

