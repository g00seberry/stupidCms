<?php

declare(strict_types=1);

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Unit-тесты для модели Audit.
 */

uses(TestCase::class);

test('casts diff_json to array', function () {
    $audit = new Audit();

    $casts = $audit->getCasts();

    expect($casts)->toHaveKey('diff_json')
        ->and($casts['diff_json'])->toBe('array');
});

test('stores ip and user agent', function () {
    $audit = new Audit([
        'ip' => '127.0.0.1',
        'ua' => 'Mozilla/5.0',
    ]);

    expect($audit->ip)->toBe('127.0.0.1')
        ->and($audit->ua)->toBe('Mozilla/5.0');
});

test('belongs to user', function () {
    $audit = new Audit();

    $relation = $audit->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class);
});

test('tracks entity changes', function () {
    $audit = new Audit([
        'action' => 'updated',
        'subject_type' => 'App\Models\Entry',
        'subject_id' => 1,
    ]);

    expect($audit->action)->toBe('updated')
        ->and($audit->subject_type)->toBe('App\Models\Entry')
        ->and($audit->subject_id)->toBe(1);
});

test('has no guarded attributes', function () {
    $audit = new Audit();

    expect($audit->getGuarded())->toBe([]);
});

