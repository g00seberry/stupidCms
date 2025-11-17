<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use App\Models\User;

/**
 * Трейт для создания административных пользователей в тестах.
 *
 * Предоставляет методы для быстрого создания пользователей с административными правами.
 */
trait HasAdminUser
{
    /**
     * Создать администратора с указанными разрешениями.
     *
     * Если разрешения не указаны, создаётся полноправный администратор (is_admin = true).
     * Если разрешения указаны, создаётся пользователь с конкретными правами.
     *
     * @param array<string> $permissions Список разрешений для установки через admin_permissions
     * @return User Созданный пользователь
     */
    protected function admin(array $permissions = []): User
    {
        // Если разрешения не указаны, создаём полноправного админа
        if (empty($permissions)) {
            return User::factory()->create([
                'is_admin' => true,
            ]);
        }
        
        // Если указаны конкретные разрешения, создаём пользователя с этими правами
        return User::factory()->create([
            'admin_permissions' => $permissions,
        ]);
    }

    /**
     * Создать администратора и выдать ему указанные разрешения через grantAdminPermissions.
     *
     * @param array<string> $permissions Список разрешений для выдачи
     * @return User Созданный пользователь с выданными разрешениями
     */
    protected function adminWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->grantAdminPermissions(...$permissions);
        $user->save();

        return $user;
    }

    /**
     * Создать обычного пользователя без административных прав.
     *
     * Используется для тестирования проверки прав доступа.
     *
     * @return User Созданный пользователь без административных прав
     */
    protected function regularUser(): User
    {
        return User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => [],
        ]);
    }
}

