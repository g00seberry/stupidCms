<?php

declare(strict_types=1);

use App\Rules\PublishedDateNotInFuture;
use Illuminate\Support\Facades\Validator;

test('passes for past date', function () {
    $rule = new PublishedDateNotInFuture();
    
    $pastDate = now()->subDay()->toIso8601String();
    
    $validator = Validator::make(
        ['published_at' => $pastDate, 'status' => 'published'],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for current date', function () {
    $rule = new PublishedDateNotInFuture();
    
    $currentDate = now()->toIso8601String();
    
    $validator = Validator::make(
        ['published_at' => $currentDate, 'status' => 'published'],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for future date', function () {
    $rule = new PublishedDateNotInFuture();
    
    $futureDate = now()->addDay()->toIso8601String();
    
    $validator = Validator::make(
        ['published_at' => $futureDate, 'status' => 'published'],
        ['published_at' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes for future date when status is not published', function () {
    $rule = new PublishedDateNotInFuture();
    
    $futureDate = now()->addDay()->toIso8601String();
    
    $validator = Validator::make(
        ['published_at' => $futureDate, 'status' => 'draft'],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when published_at is empty', function () {
    $rule = new PublishedDateNotInFuture();
    
    $validator = Validator::make(
        ['published_at' => null, 'status' => 'published'],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when status is missing', function () {
    $rule = new PublishedDateNotInFuture();
    
    $futureDate = now()->addDay()->toIso8601String();
    
    $validator = Validator::make(
        ['published_at' => $futureDate],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('handles different date formats', function () {
    $rule = new PublishedDateNotInFuture();
    
    $pastDate = '2020-01-15 10:30:00';
    
    $validator = Validator::make(
        ['published_at' => $pastDate, 'status' => 'published'],
        ['published_at' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

