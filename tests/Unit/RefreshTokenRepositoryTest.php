<?php

namespace Tests\Unit;

use App\Domain\Auth\RefreshTokenDto;
use App\Domain\Auth\RefreshTokenRepository;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RefreshTokenRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(RefreshTokenRepository::class);
    }

    public function test_find_returns_refresh_token_dto_when_token_exists(): void
    {
        $user = User::factory()->create();

        $token = RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->addDays(7),
            'parent_jti' => null,
        ]);

        $dto = $this->repo->find('test-jti-123');

        $this->assertInstanceOf(RefreshTokenDto::class, $dto);
        $this->assertEquals($token->user_id, $dto->user_id);
        $this->assertEquals($token->jti, $dto->jti);
        $this->assertInstanceOf(Carbon::class, $dto->expires_at);
        $this->assertNull($dto->used_at);
        $this->assertNull($dto->revoked_at);
        $this->assertNull($dto->parent_jti);
    }

    public function test_find_returns_null_when_token_not_found(): void
    {
        $dto = $this->repo->find('non-existent-jti');

        $this->assertNull($dto);
    }

    public function test_mark_used_conditionally_returns_1_for_fresh_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->addDays(7),
            'parent_jti' => null,
        ]);

        $result = $this->repo->markUsedConditionally('test-jti-123');

        $this->assertEquals(1, $result);

        // Verify token is marked as used
        $token = RefreshToken::where('jti', 'test-jti-123')->first();
        $this->assertNotNull($token->used_at);
    }

    public function test_mark_used_conditionally_returns_0_for_already_used_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->addDays(7),
            'used_at' => now('UTC'),
            'parent_jti' => null,
        ]);

        $result = $this->repo->markUsedConditionally('test-jti-123');

        $this->assertEquals(0, $result);
    }

    public function test_mark_used_conditionally_returns_0_for_revoked_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->addDays(7),
            'revoked_at' => now('UTC'),
            'parent_jti' => null,
        ]);

        $result = $this->repo->markUsedConditionally('test-jti-123');

        $this->assertEquals(0, $result);
    }

    public function test_mark_used_conditionally_returns_0_for_expired_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->subDay(), // Expired yesterday
            'parent_jti' => null,
        ]);

        $result = $this->repo->markUsedConditionally('test-jti-123');

        $this->assertEquals(0, $result);
    }

    public function test_mark_used_conditionally_is_atomic_only_one_succeeds(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => 'test-jti-123',
            'expires_at' => now('UTC')->addDays(7),
            'parent_jti' => null,
        ]);

        // Simulate concurrent requests: both try to mark the same token as used
        $firstResult = $this->repo->markUsedConditionally('test-jti-123');
        $secondResult = $this->repo->markUsedConditionally('test-jti-123');

        // Exactly one should succeed (return 1), the other should fail (return 0)
        $this->assertEquals(1, $firstResult + $secondResult);
        $this->assertEquals(1, $firstResult);
        $this->assertEquals(0, $secondResult);

        // Verify token is marked as used exactly once
        $token = RefreshToken::where('jti', 'test-jti-123')->first();
        $this->assertNotNull($token->used_at);
    }

    public function test_refresh_token_dto_is_valid_returns_true_for_fresh_token(): void
    {
        $dto = new RefreshTokenDto(
            user_id: 1,
            jti: 'test-jti',
            expires_at: now('UTC')->addDays(7),
            used_at: null,
            revoked_at: null,
            parent_jti: null,
            created_at: now('UTC'),
            updated_at: now('UTC'),
        );

        $this->assertTrue($dto->isValid());
        $this->assertFalse($dto->isInvalid());
    }

    public function test_refresh_token_dto_is_valid_returns_false_for_used_token(): void
    {
        $dto = new RefreshTokenDto(
            user_id: 1,
            jti: 'test-jti',
            expires_at: now('UTC')->addDays(7),
            used_at: now('UTC'),
            revoked_at: null,
            parent_jti: null,
            created_at: now('UTC'),
            updated_at: now('UTC'),
        );

        $this->assertFalse($dto->isValid());
        $this->assertTrue($dto->isInvalid());
    }

    public function test_refresh_token_dto_is_valid_returns_false_for_revoked_token(): void
    {
        $dto = new RefreshTokenDto(
            user_id: 1,
            jti: 'test-jti',
            expires_at: now('UTC')->addDays(7),
            used_at: null,
            revoked_at: now('UTC'),
            parent_jti: null,
            created_at: now('UTC'),
            updated_at: now('UTC'),
        );

        $this->assertFalse($dto->isValid());
        $this->assertTrue($dto->isInvalid());
    }

    public function test_refresh_token_dto_is_valid_returns_false_for_expired_token(): void
    {
        $dto = new RefreshTokenDto(
            user_id: 1,
            jti: 'test-jti',
            expires_at: now('UTC')->subDay(),
            used_at: null,
            revoked_at: null,
            parent_jti: null,
            created_at: now('UTC'),
            updated_at: now('UTC'),
        );

        $this->assertFalse($dto->isValid());
        $this->assertTrue($dto->isInvalid());
    }
}

