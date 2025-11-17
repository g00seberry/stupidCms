<?php

declare(strict_types=1);

use App\Models\Outbox;

/**
 * Feature-тесты для модели Outbox.
 */

test('outbox message can be created', function () {
    $outbox = Outbox::create([
        'topic' => 'email.send',
        'payload_json' => ['to' => 'test@example.com', 'subject' => 'Test'],
        'attempts' => 0,
        'available_at' => now(),
    ]);

    expect($outbox)->toBeInstanceOf(Outbox::class)
        ->and($outbox->exists)->toBeTrue();

    $this->assertDatabaseHas('outbox', [
        'id' => $outbox->id,
        'topic' => 'email.send',
    ]);
});

test('outbox stores payload data', function () {
    $payload = [
        'action' => 'notify',
        'user_id' => 1,
        'message' => 'Test notification',
    ];

    $outbox = Outbox::create([
        'topic' => 'notification',
        'payload_json' => $payload,
        'attempts' => 0,
        'available_at' => now(),
    ]);

    $outbox->refresh();

    expect($outbox->payload_json)->toBe($payload);
});

test('outbox tracks retry attempts', function () {
    $outbox = Outbox::create([
        'topic' => 'task',
        'payload_json' => ['data' => 'test'],
        'attempts' => 0,
        'available_at' => now(),
    ]);

    $outbox->update(['attempts' => $outbox->attempts + 1]);

    expect($outbox->attempts)->toBe(1);
});

test('outbox available_at controls when task is available', function () {
    $futureTime = now()->addMinutes(30);
    
    $outbox = Outbox::create([
        'topic' => 'delayed.task',
        'payload_json' => ['data' => 'test'],
        'attempts' => 0,
        'available_at' => $futureTime,
    ]);

    expect($outbox->available_at->gt(now()))->toBeTrue();
});

