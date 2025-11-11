<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder для создания административного пользователя
 *
 * Создает пользователя с правами администратора для доступа к CMS.
 *
 * Входные данные:
 * - Email: admin@example.com
 * - Password: admin123
 * - Name: Administrator
 *
 * Пароль хешируется с использованием bcrypt/argon2 (зависит от конфигурации Laravel).
 * По умолчанию Laravel использует bcrypt, но можно настроить argon2 в config/hashing.php
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Проверяем, не существует ли уже admin пользователь
        if (User::where('email', 'admin@example.com')->exists()) {
            $this->command->warn('Admin user already exists. Skipping...');
            return;
        }

        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'), // Пароль будет автоматически хеширован (bcrypt/argon2)
            'admin_permissions' => ['manage.posttypes', 'manage.entries', 'manage.taxonomies', 'manage.terms'],
            'is_admin' => true,
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@example.com');
        $this->command->info('Password: admin123');
    }
}

