<?php

declare(strict_types=1);

use App\Models\Outbox;

/**
 * Unit-тесты для модели Outbox.
 */

test('casts payload_json to array', function () {
    $outbox = new Outbox();

    $casts = $outbox->getCasts();

    expect($casts)->toHaveKey('payload_json')
        ->and($casts['payload_json'])->toBe('array');
});

test('casts attempts to integer', function () {
    $outbox = new Outbox();

    $casts = $outbox->getCasts();

    expect($casts)->toHaveKey('attempts')
        ->and($casts['attempts'])->toBe('integer');
});

test('casts available_at to datetime', function () {
    $outbox = new Outbox();

    $casts = $outbox->getCasts();

    expect($casts)->toHaveKey('available_at')
        ->and($casts['available_at'])->toBe('datetime');
});

test('supports retry attempts', function () {
    $outbox = new Outbox(['attempts' => 3]);

    expect($outbox->attempts)->toBe(3);
});

test('table name is outbox', function () {
    $outbox = new Outbox();

    expect($outbox->getTable())->toBe('outbox');
});

test('has no guarded attributes', function () {
    $outbox = new Outbox();

    expect($outbox->getGuarded())->toBe([]);
});

