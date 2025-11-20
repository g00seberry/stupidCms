<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания примеров Entry с использованием Blueprint.
 *
 * Создает записи с индексированными данными по blueprint'ам:
 * - Простые продукты (simple_product)
 * - Сложные статьи (complex_article)
 * - Демонстрирует работу с вложенными структурами и индексацией
 */
class BlueprintEntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Проверка, что есть admin user
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $this->command->warn('Admin user not found. Please run AdminUserSeeder first.');
            return;
        }

        // Проверка, что есть blueprint'ы
        $simpleProductBlueprint = Blueprint::where('code', 'simple_product')->first();
        $complexArticleBlueprint = Blueprint::where('code', 'complex_article')->first();

        if (!$simpleProductBlueprint || !$complexArticleBlueprint) {
            $this->command->warn('Blueprints not found. Please run BlueprintsSeeder first.');
            return;
        }

        // Получить PostType
        $productPostType = PostType::where('slug', 'product')->first();
        $articlePostType = PostType::where('slug', 'article')->first();

        if (!$productPostType || !$articlePostType) {
            $this->command->warn('PostTypes not found. Please run PostTypesTaxonomiesSeeder first.');
            return;
        }

        $this->command->info('🔷 Creating simple product entries...');
        $this->createSimpleProducts($productPostType, $adminUser);

        $this->command->info('🔷 Creating complex article entries...');
        $article1 = $this->createComplexArticle1($articlePostType, $adminUser);
        $article2 = $this->createComplexArticle2($articlePostType, $adminUser);
        $article3 = $this->createComplexArticle3($articlePostType, $adminUser);

        $this->command->info('🔷 Setting up article references...');
        $this->setupArticleReferences($article1, $article2, $article3);

        $this->command->info('✅ Blueprint entries seeding completed!');
        $this->printSummary();
    }

    // ===========================================
    // ПРОСТЫЕ ПРОДУКТЫ (simple_product)
    // ===========================================

    /**
     * Создать простые продукты.
     */
    private function createSimpleProducts(PostType $postType, User $admin): void
    {
        Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'laptop-pro-15',
            ],
            [
                'title' => 'Laptop Pro 15"',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(10),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Laptop Pro 15"',
                    'sku' => 'LAP-PRO-15-001',
                    'price' => 1499.99,
                    'in_stock' => true,
                    'description' => 'Powerful laptop with 15-inch display, 16GB RAM, 512GB SSD',
                ],
            ]
        );

        Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'wireless-mouse',
            ],
            [
                'title' => 'Wireless Mouse',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(7),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Wireless Mouse',
                    'sku' => 'MOUSE-WL-001',
                    'price' => 29.99,
                    'in_stock' => true,
                    'description' => 'Ergonomic wireless mouse with 3 buttons and USB receiver',
                ],
            ]
        );

        Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'mechanical-keyboard',
            ],
            [
                'title' => 'Mechanical Keyboard RGB',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(5),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Mechanical Keyboard RGB',
                    'sku' => 'KB-MECH-RGB-001',
                    'price' => 149.99,
                    'in_stock' => true,
                    'description' => 'Mechanical gaming keyboard with RGB backlight and Cherry MX switches',
                ],
            ]
        );

        Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'usb-c-cable',
            ],
            [
                'title' => 'USB-C Cable 2m',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(3),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'USB-C Cable 2m',
                    'sku' => 'CABLE-USB-C-2M',
                    'price' => 15.99,
                    'in_stock' => false,
                    'description' => 'High-speed USB-C to USB-C cable, 2 meters, supports 100W charging',
                ],
            ]
        );

        $this->command->info("  ✓ Created 4 simple product entries");
    }

    // ===========================================
    // СЛОЖНЫЕ СТАТЬИ (complex_article)
    // ===========================================

    /**
     * Создать статью 1.
     */
    private function createComplexArticle1(PostType $postType, User $admin): Entry
    {
        $entry = Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'getting-started-with-laravel-12',
            ],
            [
                'title' => 'Getting Started with Laravel 12',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(15),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Getting Started with Laravel 12',
                    'slug' => 'getting-started-with-laravel-12',
                    'content' => '<h1>Getting Started with Laravel 12</h1><p>Laravel 12 introduces many exciting features and improvements. In this comprehensive guide, we will explore the fundamentals of Laravel 12 and build your first application.</p><h2>Installation</h2><p>First, ensure you have PHP 8.3 or higher installed...</p>',
                    'excerpt' => 'Learn the basics of Laravel 12 and build your first application with this comprehensive tutorial.',
                    'published_at' => now()->subDays(15)->toIso8601String(),
                    'reading_time_minutes' => 15,
                    'author' => [
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                    ],
                    'seo' => [
                        'meta_title' => 'Getting Started with Laravel 12 - Complete Guide',
                        'meta_description' => 'Learn Laravel 12 from scratch with this step-by-step tutorial. Perfect for beginners and intermediate developers.',
                        'meta_keywords' => 'laravel, php, framework, tutorial, laravel 12',
                        'og_image' => 'https://example.com/images/laravel-12-tutorial.jpg',
                        'canonical_url' => 'https://example.com/articles/getting-started-with-laravel-12',
                    ],
                    'related_articles' => [], // Will be set later
                ],
            ]
        );

        $this->command->info("  ✓ Created article: {$entry->title}");
        return $entry;
    }

    /**
     * Создать статью 2.
     */
    private function createComplexArticle2(PostType $postType, User $admin): Entry
    {
        $entry = Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'advanced-eloquent-techniques',
            ],
            [
                'title' => 'Advanced Eloquent Techniques',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(12),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Advanced Eloquent Techniques',
                    'slug' => 'advanced-eloquent-techniques',
                    'content' => '<h1>Advanced Eloquent Techniques</h1><p>Master advanced Eloquent ORM patterns and techniques to build more efficient Laravel applications.</p><h2>Eager Loading</h2><p>Learn how to optimize database queries with eager loading...</p><h2>Query Scopes</h2><p>Create reusable query logic with local and global scopes...</p>',
                    'excerpt' => 'Master advanced Eloquent ORM patterns and optimize your Laravel database queries.',
                    'published_at' => now()->subDays(12)->toIso8601String(),
                    'reading_time_minutes' => 25,
                    'author' => [
                        'name' => 'Jane Smith',
                        'email' => 'jane.smith@example.com',
                    ],
                    'seo' => [
                        'meta_title' => 'Advanced Eloquent Techniques for Laravel Developers',
                        'meta_description' => 'Discover advanced Eloquent ORM techniques including eager loading, query scopes, and performance optimization.',
                        'meta_keywords' => 'eloquent, orm, laravel, database, query optimization',
                        'og_image' => 'https://example.com/images/eloquent-advanced.jpg',
                        'canonical_url' => 'https://example.com/articles/advanced-eloquent-techniques',
                    ],
                    'related_articles' => [], // Will be set later
                ],
            ]
        );

        $this->command->info("  ✓ Created article: {$entry->title}");
        return $entry;
    }

    /**
     * Создать статью 3.
     */
    private function createComplexArticle3(PostType $postType, User $admin): Entry
    {
        $entry = Entry::firstOrCreate(
            [
                'post_type_id' => $postType->id,
                'slug' => 'building-restful-apis-with-laravel',
            ],
            [
                'title' => 'Building RESTful APIs with Laravel',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(8),
                'author_id' => $admin->id,
                'data_json' => [
                    'title' => 'Building RESTful APIs with Laravel',
                    'slug' => 'building-restful-apis-with-laravel',
                    'content' => '<h1>Building RESTful APIs with Laravel</h1><p>Learn how to design and implement RESTful APIs using Laravel best practices.</p><h2>API Resources</h2><p>Transform your Eloquent models into JSON responses...</p><h2>Authentication</h2><p>Secure your API with Laravel Sanctum...</p>',
                    'excerpt' => 'Design and implement production-ready RESTful APIs using Laravel framework.',
                    'published_at' => now()->subDays(8)->toIso8601String(),
                    'reading_time_minutes' => 30,
                    'author' => [
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                    ],
                    'seo' => [
                        'meta_title' => 'Building RESTful APIs with Laravel - Best Practices',
                        'meta_description' => 'Complete guide to building RESTful APIs with Laravel. Learn about resources, authentication, and best practices.',
                        'meta_keywords' => 'rest api, laravel, api resources, authentication, sanctum',
                        'og_image' => 'https://example.com/images/laravel-rest-api.jpg',
                        'canonical_url' => 'https://example.com/articles/building-restful-apis-with-laravel',
                    ],
                    'related_articles' => [], // Will be set later
                ],
            ]
        );

        $this->command->info("  ✓ Created article: {$entry->title}");
        return $entry;
    }

    /**
     * Настроить связи между статьями (related_articles ref).
     */
    private function setupArticleReferences(Entry $article1, Entry $article2, Entry $article3): void
    {
        // Article 1 связан с Article 2 и 3
        $data1 = $article1->data_json;
        $data1['related_articles'] = [$article2->id, $article3->id];
        $article1->update(['data_json' => $data1]);

        // Article 2 связан с Article 1 и 3
        $data2 = $article2->data_json;
        $data2['related_articles'] = [$article1->id, $article3->id];
        $article2->update(['data_json' => $data2]);

        // Article 3 связан с Article 1
        $data3 = $article3->data_json;
        $data3['related_articles'] = [$article1->id];
        $article3->update(['data_json' => $data3]);

        $this->command->info("  ✓ Set up article references (related_articles)");
    }

    // ===========================================
    // ВЫВОД СТАТИСТИКИ
    // ===========================================

    /**
     * Вывести сводную статистику.
     */
    private function printSummary(): void
    {
        $productPostType = PostType::where('slug', 'product')->first();
        $articlePostType = PostType::where('slug', 'article')->first();

        $productsCount = 0;
        $articlesCount = 0;

        if ($productPostType) {
            $productsCount = Entry::where('post_type_id', $productPostType->id)
                ->where('status', Entry::STATUS_PUBLISHED)
                ->count();
        }

        if ($articlePostType) {
            $articlesCount = Entry::where('post_type_id', $articlePostType->id)
                ->where('status', Entry::STATUS_PUBLISHED)
                ->count();
        }

        $totalDocValues = \App\Models\DocValue::count();
        $totalDocRefs = \App\Models\DocRef::count();

        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info("  • Products (with blueprint): {$productsCount}");
        $this->command->info("  • Articles (with blueprint): {$articlesCount}");
        $this->command->info("  • Total DocValues (indexed scalars): {$totalDocValues}");
        $this->command->info("  • Total DocRefs (indexed references): {$totalDocRefs}");
    }
}

