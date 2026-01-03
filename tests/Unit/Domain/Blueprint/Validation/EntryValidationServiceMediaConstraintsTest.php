<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\DataTypeMapper;
use App\Domain\Blueprint\Validation\EntryValidationService;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\MediaMimeRule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\TypeRule;
use App\Models\Blueprint;
use App\Models\Path;
use App\Models\PathMediaConstraint;
use App\Services\Path\Constraints\MediaPathConstraintsBuilder;
use App\Services\Path\Constraints\PathConstraintsBuilderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->converter = Mockery::mock(PathValidationRulesConverterInterface::class);
    $this->fieldPathBuilder = new FieldPathBuilder();
    $this->dataTypeMapper = new DataTypeMapper();
    $this->ruleFactory = Mockery::mock(RuleFactory::class);
    $this->constraintsBuilderRegistry = Mockery::mock(PathConstraintsBuilderRegistry::class);
    
    // Настраиваем мок ruleFactory для автоматического создания TypeRule
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->byDefault()
        ->andReturnUsing(function ($type) {
            return new TypeRule($type);
        });
    
    $this->service = new EntryValidationService(
        $this->converter,
        $this->fieldPathBuilder,
        $this->dataTypeMapper,
        $this->ruleFactory,
        $this->constraintsBuilderRegistry
    );
    
    $this->mediaBuilder = new MediaPathConstraintsBuilder();
});

afterEach(function () {
    Mockery::close();
});

test('buildRulesFor adds MediaMimeRule when media path has constraints (cardinality one)', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'avatar',
        'full_path' => 'avatar',
        'cardinality' => 'one',
        'data_type' => 'media',
        'validation_rules' => null,
    ]);
    
    // Создать constraints
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/jpeg']);
    PathMediaConstraint::create(['path_id' => $path->id, 'allowed_mime' => 'image/png']);
    
    $this->converter->shouldReceive('convert')
        ->once()
        ->with(null)
        ->andReturn([]);
    
    $typeRule = new TypeRule('string');
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('string')
        ->andReturn($typeRule);
    
    $mediaMimeRule = new MediaMimeRule(['image/jpeg', 'image/png'], 'avatar');
    $this->ruleFactory->shouldReceive('createMediaMimeRule')
        ->once()
        ->with(['image/jpeg', 'image/png'], 'avatar')
        ->andReturn($mediaMimeRule);
    
    // Настроить registry для возврата builder
    $this->constraintsBuilderRegistry->shouldReceive('getBuilder')
        ->once()
        ->with('media')
        ->andReturn($this->mediaBuilder);
    $this->constraintsBuilderRegistry->shouldReceive('getAllBuilders')
        ->once()
        ->andReturn([$this->mediaBuilder]);
    
    $result = $this->service->buildRulesFor($blueprint);
    
    $fieldPath = 'data_json.avatar';
    $rules = $result->getRulesForField($fieldPath);
    
    // Должно быть TypeRule + MediaMimeRule
    $hasMediaMimeRule = false;
    foreach ($rules as $rule) {
        if ($rule instanceof MediaMimeRule) {
            $hasMediaMimeRule = true;
            expect($rule->getAllowedMimeTypes())->toContain('image/jpeg', 'image/png');
            break;
        }
    }
    
    expect($hasMediaMimeRule)->toBeTrue('MediaMimeRule should be added when constraints exist')
        ->and($result->getRulesForField($fieldPath))->toHaveCount(2);
});

test('buildRulesFor does not add MediaMimeRule when media path has no constraints', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'avatar',
        'full_path' => 'avatar',
        'cardinality' => 'one',
        'data_type' => 'media',
        'validation_rules' => null,
    ]);
    
    // Не создавать constraints
    
    $this->converter->shouldReceive('convert')
        ->once()
        ->with(null)
        ->andReturn([]);
    
    $typeRule = new TypeRule('string');
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('string')
        ->andReturn($typeRule);
    
    // Настроить registry для возврата builder
    $this->constraintsBuilderRegistry->shouldReceive('getBuilder')
        ->once()
        ->with('media')
        ->andReturn($this->mediaBuilder);
    $this->constraintsBuilderRegistry->shouldReceive('getAllBuilders')
        ->once()
        ->andReturn([$this->mediaBuilder]);
    
    // MediaMimeRule не должен создаваться, если нет constraints
    $this->ruleFactory->shouldNotReceive('createMediaMimeRule');
    
    $result = $this->service->buildRulesFor($blueprint);
    
    $fieldPath = 'data_json.avatar';
    $rules = $result->getRulesForField($fieldPath);
    
    // Должно быть только TypeRule
    $hasMediaMimeRule = false;
    foreach ($rules as $rule) {
        if ($rule instanceof MediaMimeRule) {
            $hasMediaMimeRule = true;
            break;
        }
    }
    
    expect($hasMediaMimeRule)->toBeFalse('MediaMimeRule should not be added when no constraints')
        ->and($result->getRulesForField($fieldPath))->toHaveCount(1);
});

