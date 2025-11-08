<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
     * @return array<int, string>
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
