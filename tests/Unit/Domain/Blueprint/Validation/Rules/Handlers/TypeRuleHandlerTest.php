<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Handlers\TypeRuleHandler;
use App\Domain\Blueprint\Validation\Rules\TypeRule;
use Tests\TestCase;

final class TypeRuleHandlerTest extends TestCase
{
    private TypeRuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new TypeRuleHandler();
    }

    public function test_supports_type_rule_type(): void
    {
        $this->assertTrue($this->handler->supports('type'));
        $this->assertFalse($this->handler->supports('other'));
    }

    public function test_handles_string_type(): void
    {
        $rule = new TypeRule('string');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['string'], $result);
    }

    public function test_handles_integer_type(): void
    {
        $rule = new TypeRule('integer');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['integer'], $result);
    }

    public function test_handles_numeric_type(): void
    {
        $rule = new TypeRule('numeric');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['numeric'], $result);
    }

    public function test_handles_boolean_type(): void
    {
        $rule = new TypeRule('boolean');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['boolean'], $result);
    }

    public function test_handles_date_type(): void
    {
        $rule = new TypeRule('date');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['date'], $result);
    }

    public function test_handles_array_type(): void
    {
        $rule = new TypeRule('array');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['array'], $result);
    }

    public function test_handles_unknown_type_returns_empty(): void
    {
        $rule = new TypeRule('unknown');
        $result = $this->handler->handle($rule);

        $this->assertEquals([], $result);
    }

    public function test_throws_exception_for_wrong_rule_type(): void
    {
        $wrongRule = new \App\Domain\Blueprint\Validation\Rules\RequiredRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected TypeRule instance');

        $this->handler->handle($wrongRule);
    }
}

