<?php

namespace Tests\Unit;

use App\Domain\Pages\Validation\NotReservedRoute;
use App\Domain\Routing\ReservedRouteRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NotReservedRouteRuleTest extends TestCase
{
    private ReservedRouteRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'reserved_routes' => [
                'paths' => ['admin'],
                'prefixes' => ['admin', 'api'],
            ],
        ];
        
        $this->registry = new ReservedRouteRegistry(Cache::store(), $config);
        $this->registry->clearCache();
    }

    public function test_passes_returns_false_for_reserved_slug(): void
    {
        $rule = new NotReservedRoute($this->registry);
        
        $this->assertFalse($rule->passes('slug', 'admin'));
        $this->assertFalse($rule->passes('slug', 'api'));
    }

    public function test_passes_returns_true_for_normal_slug(): void
    {
        $rule = new NotReservedRoute($this->registry);
        
        $this->assertTrue($rule->passes('slug', 'about'));
        $this->assertTrue($rule->passes('slug', 'contact'));
    }

    public function test_passes_case_insensitive(): void
    {
        $rule = new NotReservedRoute($this->registry);
        
        $this->assertFalse($rule->passes('slug', 'Admin'));
        $this->assertFalse($rule->passes('slug', 'ADMIN'));
        $this->assertFalse($rule->passes('slug', 'API'));
    }

    public function test_passes_normalizes_slug(): void
    {
        $rule = new NotReservedRoute($this->registry);
        
        // Проверяем нормализацию (trim слэшей, пробелов)
        $this->assertFalse($rule->passes('slug', '/admin'));
        $this->assertFalse($rule->passes('slug', 'admin/'));
        $this->assertFalse($rule->passes('slug', ' admin '));
    }

    public function test_message_returns_localized_string(): void
    {
        $rule = new NotReservedRoute($this->registry);
        $message = $rule->message();
        
        $this->assertStringContainsString('конфликтует с зарезервированными', $message);
    }

    public function test_validator_integration(): void
    {
        $validator = Validator::make(
            ['slug' => 'admin'],
            ['slug' => [new NotReservedRoute($this->registry)]]
        );
        
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_validator_passes_for_normal_slug(): void
    {
        $validator = Validator::make(
            ['slug' => 'about'],
            ['slug' => [new NotReservedRoute($this->registry)]]
        );
        
        $this->assertTrue($validator->passes());
    }
}

