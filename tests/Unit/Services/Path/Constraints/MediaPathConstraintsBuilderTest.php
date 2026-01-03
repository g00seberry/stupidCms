<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Path\Constraints;

use App\Models\Path;
use App\Models\PathMediaConstraint;
use App\Services\Path\Constraints\MediaPathConstraintsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->builder = new MediaPathConstraintsBuilder();
    $this->path = Path::factory()->create(['data_type' => 'media']);
});

test('getSupportedDataType returns media', function () {
    expect($this->builder->getSupportedDataType())->toBe('media');
});

test('getRelationName returns mediaConstraints', function () {
    expect($this->builder->getRelationName())->toBe('mediaConstraints');
});

test('buildForResource returns empty array when no constraints', function () {
    $result = $this->builder->buildForResource($this->path);
    
    expect($result)->toBe([]);
});

test('buildForResource returns allowed_mimes when constraints exist', function () {
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/png']);
    
    $result = $this->builder->buildForResource($this->path);
    
    expect($result)->toHaveKey('allowed_mimes')
        ->and($result['allowed_mimes'])->toContain('image/jpeg', 'image/png');
});

test('buildForResource uses loaded relations when available', function () {
    $this->path->load('mediaConstraints');
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    $this->path->refresh();
    $this->path->load('mediaConstraints');
    
    $result = $this->builder->buildForResource($this->path);
    
    expect($result)->toHaveKey('allowed_mimes')
        ->and($result['allowed_mimes'])->toContain('image/jpeg');
});

test('hasConstraints returns false when no constraints', function () {
    expect($this->builder->hasConstraints($this->path))->toBeFalse();
});

test('hasConstraints returns true when constraints exist', function () {
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    
    expect($this->builder->hasConstraints($this->path))->toBeTrue();
});

test('sync deletes old constraints and creates new ones', function () {
    // Создать старые constraints
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/png']);
    
    // Синхронизировать с новыми constraints
    $this->builder->sync($this->path, [
        'allowed_mimes' => ['image/gif', 'image/webp'],
    ]);
    
    $constraints = PathMediaConstraint::where('path_id', $this->path->id)->get();
    expect($constraints)->toHaveCount(2)
        ->and($constraints->pluck('allowed_mime')->toArray())
        ->toContain('image/gif', 'image/webp')
        ->not->toContain('image/jpeg', 'image/png');
});

test('sync creates constraints from array', function () {
    $this->builder->sync($this->path, [
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif'],
    ]);
    
    $constraints = PathMediaConstraint::where('path_id', $this->path->id)->get();
    expect($constraints)->toHaveCount(3)
        ->and($constraints->pluck('allowed_mime')->toArray())
        ->toContain('image/jpeg', 'image/png', 'image/gif');
});

test('sync handles empty array by deleting all constraints', function () {
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    
    $this->builder->sync($this->path, [
        'allowed_mimes' => [],
    ]);
    
    $constraints = PathMediaConstraint::where('path_id', $this->path->id)->get();
    expect($constraints)->toHaveCount(0);
});

test('loadRelations loads mediaConstraints relation', function () {
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    
    $this->builder->loadRelations($this->path);
    
    expect($this->path->relationLoaded('mediaConstraints'))->toBeTrue()
        ->and($this->path->mediaConstraints)->toHaveCount(1);
});

test('buildForSchema returns same format as buildForResource', function () {
    PathMediaConstraint::create(['path_id' => $this->path->id, 'allowed_mime' => 'image/jpeg']);
    
    $resourceResult = $this->builder->buildForResource($this->path);
    $schemaResult = $this->builder->buildForSchema($this->path);
    
    expect($schemaResult)->toEqual($resourceResult);
});

