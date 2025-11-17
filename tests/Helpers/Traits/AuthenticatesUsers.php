<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\Models\User;

/**
 * Трейт для упрощения аутентификации пользователей в тестах.
 */
trait AuthenticatesUsers
{
    /**
     * Аутентифицировать пользователя как администратора.
     *
     * @param User|null $admin Пользователь-администратор (если null, будет создан новый)
     * @return $this
     */
    protected function asAdmin(?User $admin = null): self
    {
        $admin = $admin ?? User::factory()->admin()->create();
        
        return $this->actingAs($admin, 'admin');
    }

    /**
     * Аутентифицировать обычного пользователя.
     *
     * @param User|null $user Пользователь (если null, будет создан новый)
     * @return $this
     */
    protected function asUser(?User $user = null): self
    {
        $user = $user ?? User::factory()->create();
        
        return $this->actingAs($user);
    }

    /**
     * Аутентифицировать пользователя с помощью токена JWT.
     *
     * @param string $token JWT токен
     * @return $this
     */
    protected function withJwtToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}

