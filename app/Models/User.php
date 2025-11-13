<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Eloquent модель для пользователей (User).
 *
 * Представляет пользователей системы с поддержкой аутентификации и авторизации.
 * Поддерживает административные права и разрешения.
 *
 * @property int $id
 * @property string $name Имя пользователя
 * @property string $email Email пользователя (уникальный)
 * @property \Illuminate\Support\Carbon|null $email_verified_at Дата подтверждения email
 * @property string $password Хеш пароля
 * @property string|null $remember_token Токен для "запомнить меня"
 * @property bool $is_admin Флаг администратора (всегда имеет все права)
 * @property array $admin_permissions Список административных разрешений
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [
        'is_admin', // Защита от массового присвоения администраторских прав
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'admin_permissions' => 'array',
        ];
    }

    /**
     * Get normalized list of admin permissions for the user.
     *
     * Возвращает очищенный и нормализованный список разрешений:
     * удаляет пустые строки, дубликаты и нестроковые значения.
     *
     * @return array<int, string> Массив уникальных разрешений
     */
    public function adminPermissions(): array
    {
        $permissions = $this->getAttribute('admin_permissions');

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_filter($permissions, static fn ($permission) => is_string($permission) && $permission !== '')));
    }

    /**
     * Check whether user has specific admin permission (admins always pass).
     *
     * Администраторы (is_admin = true) всегда имеют все права.
     * Для обычных пользователей проверяется наличие разрешения в списке.
     *
     * @param string $permission Название разрешения (например, 'manage.entries')
     * @return bool true, если пользователь имеет разрешение
     */
    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return in_array($permission, $this->adminPermissions(), true);
    }

    /**
     * Assign admin permissions without persisting (useful in tests).
     *
     * Назначает разрешения без сохранения в БД. Полезно для тестов.
     * Разрешения добавляются к существующим (без дубликатов).
     *
     * @param string ...$permissions Разрешения для назначения
     * @return void
     */
    public function grantAdminPermissions(string ...$permissions): void
    {
        $current = $this->adminPermissions();

        foreach ($permissions as $permission) {
            if (! in_array($permission, $current, true)) {
                $current[] = $permission;
            }
        }

        $this->setAttribute('admin_permissions', $current);
    }
}
