<?php

namespace Tests\Unit\Rules;

use App\Rules\JsonValue;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class JsonValueTest extends TestCase
{
    public function test_it_accepts_valid_json_primitives(): void
    {
        $rule = new JsonValue();

        $validator = Validator::make(
            [
                'string' => 'hello',
                'number' => 123,
                'boolean' => true,
                'null' => null,
                'array' => ['foo' => 'bar'],
            ],
            [
                'string' => [$rule],
                'number' => [$rule],
                'boolean' => [$rule],
                'null' => [$rule],
                'array' => [$rule],
            ]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_it_rejects_invalid_types(): void
    {
        $stream = fopen('php://temp', 'rb');

        $validator = Validator::make(
            ['value' => $stream],
            ['value' => [new JsonValue()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('value', $validator->errors()->messages());

        fclose($stream);
    }

    public function test_it_enforces_max_bytes(): void
    {
        $value = str_repeat('A', 1024);

        $validator = Validator::make(
            ['value' => $value],
            ['value' => [new JsonValue(maxBytes: 512)]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_it_rejects_invalid_utf8_strings(): void
    {
        $invalid = "\xB1"; // invalid UTF-8 byte sequence

        $validator = Validator::make(
            ['value' => $invalid],
            ['value' => [new JsonValue()]]
        );

        $this->assertTrue($validator->fails());
    }
}

