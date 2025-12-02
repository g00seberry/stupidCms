<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\DataTypeMapper;
use Tests\TestCase;

final class DataTypeMapperTest extends TestCase
{
    private DataTypeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DataTypeMapper();
    }

    public function test_maps_string_to_string(): void
    {
        $this->assertEquals('string', $this->mapper->mapToValidationType('string'));
    }

    public function test_maps_text_to_string(): void
    {
        $this->assertEquals('string', $this->mapper->mapToValidationType('text'));
    }

    public function test_maps_int_to_integer(): void
    {
        $this->assertEquals('integer', $this->mapper->mapToValidationType('int'));
    }

    public function test_maps_float_to_numeric(): void
    {
        $this->assertEquals('numeric', $this->mapper->mapToValidationType('float'));
    }

    public function test_maps_bool_to_boolean(): void
    {
        $this->assertEquals('boolean', $this->mapper->mapToValidationType('bool'));
    }

    public function test_maps_datetime_to_date(): void
    {
        $this->assertEquals('date', $this->mapper->mapToValidationType('datetime'));
    }

    public function test_maps_json_to_array(): void
    {
        $this->assertEquals('array', $this->mapper->mapToValidationType('json'));
    }

    public function test_maps_ref_to_integer(): void
    {
        $this->assertEquals('integer', $this->mapper->mapToValidationType('ref'));
    }

    public function test_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->mapper->mapToValidationType('unknown'));
    }

    public function test_is_supported_returns_true_for_known_types(): void
    {
        $types = ['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref'];
        
        foreach ($types as $type) {
            $this->assertTrue($this->mapper->isSupported($type), "Type {$type} should be supported");
        }
    }

    public function test_is_supported_returns_false_for_unknown_type(): void
    {
        $this->assertFalse($this->mapper->isSupported('unknown'));
    }
}

