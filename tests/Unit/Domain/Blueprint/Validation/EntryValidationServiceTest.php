<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\DataTypeMapper;
use App\Domain\Blueprint\Validation\EntryValidationService;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use App\Domain\Blueprint\Validation\Rules\TypeRule;
use App\Models\Blueprint;
use App\Models\Path;
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
    // По умолчанию возвращаем TypeRule для любого типа
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->byDefault()
        ->andReturnUsing(function ($type) {
            return new TypeRule($type);
        });
    
    // Настраиваем мок регистра constraints: по умолчанию не возвращает билдеров
    $this->constraintsBuilderRegistry->shouldReceive('getBuilder')
        ->byDefault()
        ->andReturn(null);
    $this->constraintsBuilderRegistry->shouldReceive('getAllBuilders')
        ->byDefault()
        ->andReturn([]);
    
    $this->service = new EntryValidationService(
        $this->converter,
        $this->fieldPathBuilder,
        $this->dataTypeMapper,
        $this->ruleFactory,
        $this->constraintsBuilderRegistry
    );
});

afterEach(function () {
    Mockery::close();
});

// 1.1. Базовые сценарии

test('buildRulesFor returns empty RuleSet for blueprint without paths', function () {
    $blueprint = Blueprint::factory()->create();

    $result = $this->service->buildRulesFor($blueprint);

    expect($result)->toBeInstanceOf(RuleSet::class)
        ->and($result->isEmpty())->toBeTrue();
});

test('buildRulesFor processes blueprint with single simple path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $typeRule = new TypeRule('string');
    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['required' => true])
        ->andReturn([$requiredRule]);

    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('string')
        ->andReturn($typeRule);

    $result = $this->service->buildRulesFor($blueprint);

    expect($result)->toBeInstanceOf(RuleSet::class)
        ->and($result->isEmpty())->toBeFalse()
        ->and($result->hasRulesForField('data_json.title'))->toBeTrue()
        ->and($result->getRulesForField('data_json.title'))->toHaveCount(2);
});

test('buildRulesFor processes blueprint with multiple paths', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'description',
        'full_path' => 'description',
        'cardinality' => 'one',
        'data_type' => 'text',
        'validation_rules' => ['required' => false],
    ]);

    $requiredRule = new RequiredRule();
    $nullableRule = new NullableRule();
    $typeRule1 = new TypeRule('string');
    $typeRule2 = new TypeRule('string');

    $this->converter->shouldReceive('convert')
        ->with(['required' => true])
        ->once()
        ->andReturn([$requiredRule]);
    $this->converter->shouldReceive('convert')
        ->with(['required' => false])
        ->once()
        ->andReturn([$nullableRule]);

    $this->ruleFactory->shouldReceive('createTypeRule')
        ->with('string')
        ->twice()
        ->andReturn($typeRule1, $typeRule2);

    $result = $this->service->buildRulesFor($blueprint);

    expect($result)->toBeInstanceOf(RuleSet::class)
        ->and($result->isEmpty())->toBeFalse()
        ->and($result->hasRulesForField('data_json.title'))->toBeTrue()
        ->and($result->hasRulesForField('data_json.description'))->toBeTrue();
});

test('buildRulesFor loads paths in correct order by length', function () {
    $blueprint = Blueprint::factory()->create();
    
    // Создаём paths в неправильном порядке
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'level3',
        'full_path' => 'level1.level2.level3',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'level1',
        'full_path' => 'level1',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'level2',
        'full_path' => 'level1.level2',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->times(3)
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // Проверяем, что все paths обработаны
    expect($result->getFieldPaths())->toHaveCount(3)
        ->and($result->hasRulesForField('data_json.level1'))->toBeTrue()
        ->and($result->hasRulesForField('data_json.level1.level2'))->toBeTrue()
        ->and($result->hasRulesForField('data_json.level1.level2.level3'))->toBeTrue();
});

// 1.2. Преобразование путей

test('buildRulesFor correctly transforms simple path title to data_json.title', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    expect($result->hasRulesForField('data_json.title'))->toBeTrue();
});

test('buildRulesFor correctly transforms nested path author.name to data_json.author.name', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    expect($result->hasRulesForField('data_json.author.name'))->toBeTrue();
});

test('buildRulesFor correctly handles cardinality many for parent path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'contacts',
        'full_path' => 'author.contacts',
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'phone',
        'full_path' => 'author.contacts.phone',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->with(null)
        ->twice()
        ->andReturn([]);
    $this->converter->shouldReceive('convert')
        ->with(['required' => true])
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // author имеет cardinality='many', поэтому contacts заменяется на *.contacts
    expect($result->hasRulesForField('data_json.author.*.contacts.phone'))->toBeTrue();
});

test('buildRulesFor correctly handles multiple array levels', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'items',
        'full_path' => 'items',
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'items.tags',
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'items.tags.name',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->with(null)
        ->twice()
        ->andReturn([]);
    $this->converter->shouldReceive('convert')
        ->with(['required' => true])
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // items имеет cardinality='many', поэтому tags заменяется на *.tags
    // items.tags имеет cardinality='many', поэтому name заменяется на *.name
    expect($result->hasRulesForField('data_json.items.*.tags.*.name'))->toBeTrue();
});

test('buildRulesFor correctly handles mixed structures', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'events',
        'full_path' => 'events',
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'venue',
        'full_path' => 'events.venue',
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'location',
        'full_path' => 'events.venue.location',
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'coordinates',
        'full_path' => 'events.venue.location.coordinates',
        'cardinality' => 'one',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'lat',
        'full_path' => 'events.venue.location.coordinates.lat',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->with(null)
        ->times(4)
        ->andReturn([]);
    $this->converter->shouldReceive('convert')
        ->with(['required' => true])
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // events имеет cardinality='many', поэтому venue заменяется на *.venue
    expect($result->hasRulesForField('data_json.events.*.venue.location.coordinates.lat'))->toBeTrue();
});

// 1.3. Обработка validation_rules

test('buildRulesFor correctly converts validation_rules to Rule objects', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => ['required' => true, 'min' => 3, 'max' => 255],
    ]);

    $requiredRule = new RequiredRule();
    $minRule = new MinRule(3);
    $maxRule = new MaxRule(255);

    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['required' => true, 'min' => 3, 'max' => 255])
        ->andReturn([$requiredRule, $minRule, $maxRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // 3 правила из validation_rules + 1 автоматическое TypeRule = 4 правила
    expect($result->getRulesForField('data_json.title'))->toHaveCount(4);
});

test('buildRulesFor correctly handles null validation_rules', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => null,
    ]);

    $this->converter->shouldReceive('convert')
        ->once()
        ->with(null)
        ->andReturn([]);

    $result = $this->service->buildRulesFor($blueprint);

    // Даже при null validation_rules автоматически создаётся TypeRule
    expect($result->hasRulesForField('data_json.title'))->toBeTrue()
        ->and($result->getRulesForField('data_json.title'))->toHaveCount(1);
});

test('buildRulesFor correctly handles empty validation_rules array', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => [],
    ]);

    $this->converter->shouldReceive('convert')
        ->once()
        ->with([])
        ->andReturn([]);

    $result = $this->service->buildRulesFor($blueprint);

    // Даже при пустом validation_rules автоматически создаётся TypeRule
    expect($result->hasRulesForField('data_json.title'))->toBeTrue()
        ->and($result->getRulesForField('data_json.title'))->toHaveCount(1);
});

test('buildRulesFor correctly handles multiple rules for one path', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => [
            'required' => true,
            'min' => 3,
            'max' => 255,
            'pattern' => '/^[a-z]+$/',
        ],
    ]);

    $requiredRule = new RequiredRule();
    $minRule = new MinRule(3);
    $maxRule = new MaxRule(255);
    $patternRule = new PatternRule('/^[a-z]+$/');

    $this->converter->shouldReceive('convert')
        ->once()
        ->andReturn([$requiredRule, $minRule, $maxRule, $patternRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // 4 правила из validation_rules + 1 автоматическое TypeRule = 5 правил
    expect($result->getRulesForField('data_json.title'))->toHaveCount(5);
});

// 1.4. Интеграция с зависимостями

test('buildRulesFor uses FieldPathBuilder for building paths', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // FieldPathBuilder должен добавить префикс data_json.
    expect($result->hasRulesForField('data_json.title'))->toBeTrue();
});

test('buildRulesFor uses PathValidationRulesConverter for converting rules', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['required' => true])
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    expect($result->getRulesForField('data_json.title')[0])->toBeInstanceOf(RequiredRule::class);
});

test('buildRulesFor correctly passes pathCardinalities to FieldPathBuilder', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'author',
        'full_path' => 'author',
        'cardinality' => 'many',
        'validation_rules' => null,
    ]);
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'name',
        'full_path' => 'author.name',
        'cardinality' => 'one',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $this->converter->shouldReceive('convert')
        ->with(null)
        ->once()
        ->andReturn([]);
    $this->converter->shouldReceive('convert')
        ->with(['required' => true])
        ->once()
        ->andReturn([$requiredRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // FieldPathBuilder должен использовать cardinality для замены на wildcard
    // author имеет cardinality='many', поэтому name заменяется на *.name
    expect($result->hasRulesForField('data_json.author.*.name'))->toBeTrue();
});

test('buildRulesFor applies distinct rule to array itself for fields with cardinality many', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'reading_time_minutes',
        'full_path' => 'reading_time_minutes',
        'cardinality' => 'many',
        'data_type' => 'int',
        'validation_rules' => ['distinct' => true],
    ]);

    $distinctRule = new DistinctRule();
    $nullableRule = new NullableRule();
    $arrayRule = new TypeRule('array');
    $integerRule = new TypeRule('integer');

    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['distinct' => true])
        ->andReturn([$distinctRule, $nullableRule]); // converter добавляет nullable по умолчанию
    
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('array')
        ->andReturn($arrayRule);
    
    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('integer')
        ->andReturn($integerRule);
    
    $result = $this->service->buildRulesFor($blueprint);

    // Для полей с cardinality "many":
    // - правило array для самого массива (без .*)
    // - правило distinct применяется к самому массиву (без .*)
    // - правило nullable добавляется по умолчанию (без .*)
    // - правило integer для элементов массива (с .*)
    expect($result->hasRulesForField('data_json.reading_time_minutes'))->toBeTrue()
        ->and($result->getRulesForField('data_json.reading_time_minutes'))->toHaveCount(3); // array + distinct + nullable
});

test('buildRulesFor applies distinct rule to field itself for fields with cardinality one', function () {
    $blueprint = Blueprint::factory()->create();
    Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'tags',
        'full_path' => 'tags',
        'cardinality' => 'one',
        'data_type' => 'json',
        'validation_rules' => ['distinct' => true],
    ]);

    $distinctRule = new DistinctRule();
    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['distinct' => true])
        ->andReturn([$distinctRule]);

    $result = $this->service->buildRulesFor($blueprint);

    // Для полей с cardinality "one" правило distinct применяется к самому полю
    // + автоматически создаётся TypeRule
    expect($result->hasRulesForField('data_json.tags'))->toBeTrue()
        ->and($result->getRulesForField('data_json.tags'))->toHaveCount(2);
});

// 1.5. Автоматическое создание правил типов данных

test('buildRulesFor automatically creates type rule when not explicit', function () {
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'name' => 'title',
        'full_path' => 'title',
        'cardinality' => 'one',
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
    ]);

    $requiredRule = new RequiredRule();
    $typeRule = new TypeRule('string');

    $this->converter->shouldReceive('convert')
        ->once()
        ->with(['required' => true])
        ->andReturn([$requiredRule]);

    $this->ruleFactory->shouldReceive('createTypeRule')
        ->once()
        ->with('string')
        ->andReturn($typeRule);

    $result = $this->service->buildRulesFor($blueprint);

    $fieldPath = 'data_json.title';
    $rules = $result->getRulesForField($fieldPath);

    // Проверяем, что есть правило типа string
    $hasStringRule = false;
    foreach ($rules as $rule) {
        if ($rule instanceof TypeRule && $rule->getDataType() === 'string') {
            $hasStringRule = true;
            break;
        }
    }

    expect($hasStringRule)->toBeTrue('Type rule should be automatically created')
        ->and($result->getRulesForField($fieldPath))->toHaveCount(2);
});

test('buildRulesFor creates type rule for all data types', function () {
    $dataTypes = [
        'string' => 'string',
        'text' => 'string',
        'int' => 'integer',
        'float' => 'numeric',
        'bool' => 'boolean',
        'datetime' => 'date',
        'json' => 'array',
        'ref' => 'integer',
    ];

    foreach ($dataTypes as $dataType => $expectedValidationType) {
        $blueprint = Blueprint::factory()->create();
        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'field',
            'full_path' => 'field',
            'cardinality' => 'one',
            'data_type' => $dataType,
            'validation_rules' => null,
        ]);

        $typeRule = new TypeRule($expectedValidationType);

        $this->converter->shouldReceive('convert')
            ->once()
            ->with(null)
            ->andReturn([]);

        $this->ruleFactory->shouldReceive('createTypeRule')
            ->once()
            ->with($expectedValidationType)
            ->andReturn($typeRule);

        $result = $this->service->buildRulesFor($blueprint);

        $fieldPath = 'data_json.field';
        $rules = $result->getRulesForField($fieldPath);

        $hasTypeRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof TypeRule && $rule->getDataType() === $expectedValidationType) {
                $hasTypeRule = true;
                break;
            }
        }

        expect($hasTypeRule)->toBeTrue("Type rule should be created for data_type: {$dataType}");
    }
});


