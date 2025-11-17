<?php

declare(strict_types=1);

use App\Models\Audit;
use App\Models\User;

/**
 * Feature-тесты для модели Audit.
 */

test('audit records are created on entity changes', function () {
    $user = User::factory()->create();

    $audit = Audit::create([
        'user_id' => $user->id,
        'action' => 'created',
        'subject_type' => 'App\Models\Entry',
        'subject_id' => 1,
        'diff_json' => ['title' => 'New Title'],
        'ip' => '127.0.0.1',
    ]);

    expect($audit)->toBeInstanceOf(Audit::class)
        ->and($audit->exists)->toBeTrue();

    $this->assertDatabaseHas('audits', [
        'id' => $audit->id,
        'user_id' => $user->id,
        'action' => 'created',
    ]);
});

test('audit stores diff of changes', function () {
    $diff = [
        'old' => ['title' => 'Old Title'],
        'new' => ['title' => 'New Title'],
    ];

    $audit = Audit::create([
        'action' => 'updated',
        'subject_type' => 'App\Models\Entry',
        'subject_id' => 1,
        'diff_json' => $diff,
    ]);

    $audit->refresh();

    expect($audit->diff_json)->toBe($diff);
});

test('audit belongs to user who made change', function () {
    $user = User::factory()->create();
    
    $audit = Audit::create([
        'user_id' => $user->id,
        'action' => 'updated',
        'subject_type' => 'App\Models\Entry',
        'subject_id' => 1,
    ]);

    $audit->load('user');

    expect($audit->user)->toBeInstanceOf(User::class)
        ->and($audit->user->id)->toBe($user->id);
});

