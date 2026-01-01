<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Services\Entry\EntryRelatedDataLoader;
use App\Services\Entry\RelatedDataProviderInterface;
use App\Services\Entry\RelatedDataProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

beforeEach(function () {
    $this->registry = new RelatedDataProviderRegistry();
    $this->loader = new EntryRelatedDataLoader($this->registry);
});

test('loadRelatedData returns empty array for empty ids', function () {
    $result = $this->loader->loadRelatedData([]);

    expect($result)->toBe([]);
});

test('loadRelatedData uses all registered providers', function () {
    // Создаём мок провайдера
    $mockProvider = Mockery::mock(RelatedDataProviderInterface::class);
    $mockProvider->shouldReceive('getKey')->andReturn('testData');
    $mockProvider->shouldReceive('loadData')
        ->with([1, 2])
        ->andReturn([
            1 => ['test' => 'value1'],
            2 => ['test' => 'value2'],
        ]);

    $this->registry->register($mockProvider);

    $result = $this->loader->loadRelatedData([1, 2]);

    expect($result)->toHaveKey('testData')
        ->and($result['testData'])->toHaveCount(2)
        ->and($result['testData'][1])->toBe(['test' => 'value1'])
        ->and($result['testData'][2])->toBe(['test' => 'value2']);
});

test('loadRelatedData excludes empty provider results', function () {
    // Провайдер с данными
    $providerWithData = Mockery::mock(RelatedDataProviderInterface::class);
    $providerWithData->shouldReceive('getKey')->andReturn('withData');
    $providerWithData->shouldReceive('loadData')
        ->andReturn([1 => ['data' => 'value']]);

    // Провайдер без данных
    $providerWithoutData = Mockery::mock(RelatedDataProviderInterface::class);
    $providerWithoutData->shouldReceive('getKey')->andReturn('withoutData');
    $providerWithoutData->shouldReceive('loadData')
        ->andReturn([]);

    $this->registry->register($providerWithData);
    $this->registry->register($providerWithoutData);

    $result = $this->loader->loadRelatedData([1]);

    expect($result)->toHaveKey('withData')
        ->and($result)->not->toHaveKey('withoutData');
});

test('loadRelatedData handles multiple providers', function () {
    $provider1 = Mockery::mock(RelatedDataProviderInterface::class);
    $provider1->shouldReceive('getKey')->andReturn('data1');
    $provider1->shouldReceive('loadData')->andReturn([1 => ['value' => 1]]);

    $provider2 = Mockery::mock(RelatedDataProviderInterface::class);
    $provider2->shouldReceive('getKey')->andReturn('data2');
    $provider2->shouldReceive('loadData')->andReturn([1 => ['value' => 2]]);

    $this->registry->register($provider1);
    $this->registry->register($provider2);

    $result = $this->loader->loadRelatedData([1]);

    expect($result)->toHaveKeys(['data1', 'data2'])
        ->and($result['data1'])->toBe([1 => ['value' => 1]])
        ->and($result['data2'])->toBe([1 => ['value' => 2]]);
});

