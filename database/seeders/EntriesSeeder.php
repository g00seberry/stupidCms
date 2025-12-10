<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания примеров записей контента.
 *
 * Создает записи для разных типов постов:
 * - Pages: About, Contact, Privacy Policy
 * - Articles: несколько статей с привязкой к категориям и тегам
 * - Products: примеры товаров с категориями и регионами
 */
class EntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем необходимые данные
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $this->command->warn('Admin user not found. Please run AdminUserSeeder first.');
            return;
        }

        $pagePostType = PostType::where('name', 'Page')->first();
        $articlePostType = PostType::where('name', 'Article')->first();
        $productPostType = PostType::where('name', 'Product')->first();

        if (!$pagePostType || !$articlePostType || !$productPostType) {
            $this->command->warn('PostTypes not found. Please run PostTypesTaxonomiesSeeder first.');
            return;
        }

        $categoriesTaxonomy = Taxonomy::where('name', 'Categories')->first();
        $tagsTaxonomy = Taxonomy::where('name', 'Tags')->first();
        $regionsTaxonomy = Taxonomy::where('name', 'Regions')->first();
        $topicsTaxonomy = Taxonomy::where('name', 'Topics')->first();

        if (!$categoriesTaxonomy || !$tagsTaxonomy || !$regionsTaxonomy || !$topicsTaxonomy) {
            $this->command->warn('Taxonomies not found. Please run PostTypesTaxonomiesSeeder first.');
            return;
        }

        // Создаем Pages
        $aboutPage = Entry::firstOrCreate(
            [
                'post_type_id' => $pagePostType->id,
                'title' => 'About Us',
            ],
            [
                'title' => 'About Us',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now(),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>About Our Company</h1><p>We are a leading technology company...</p>',
                ],
            ]
        );

        $contactPage = Entry::firstOrCreate(
            [
                'post_type_id' => $pagePostType->id,
                'title' => 'Contact Us',
            ],
            [
                'title' => 'Contact Us',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now(),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Get in Touch</h1><p>Email: contact@example.com</p><p>Phone: +1 234 567 8900</p>',
                ],
            ]
        );

        $privacyPage = Entry::firstOrCreate(
            [
                'post_type_id' => $pagePostType->id,
                'title' => 'Privacy Policy',
            ],
            [
                'title' => 'Privacy Policy',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now(),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Privacy Policy</h1><p>Your privacy is important to us...</p>',
                ],
            ]
        );

        // Создаем Articles
        $techCategory = Term::where('taxonomy_id', $categoriesTaxonomy->id)
            ->where('name', 'Technology')
            ->first();
        $webDevCategory = Term::where('taxonomy_id', $categoriesTaxonomy->id)
            ->where('name', 'Web Development')
            ->first();
        $newsTag = Term::where('taxonomy_id', $tagsTaxonomy->id)
            ->where('name', 'News')
            ->first();
        $tutorialTag = Term::where('taxonomy_id', $tagsTaxonomy->id)
            ->where('name', 'Tutorial')
            ->first();
        $reviewTag = Term::where('taxonomy_id', $tagsTaxonomy->id)
            ->where('name', 'Review')
            ->first();

        $article1 = Entry::firstOrCreate(
            [
                'post_type_id' => $articlePostType->id,
                'title' => 'Introduction to Laravel 12',
            ],
            [
                'title' => 'Introduction to Laravel 12',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(5),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Laravel 12: What\'s New?</h1><p>Laravel 12 introduces many exciting features...</p>',
                    'excerpt' => 'Discover the latest features in Laravel 12 framework.',
                ],
            ]
        );
        if ($webDevCategory && $tutorialTag) {
            $article1->terms()->sync([$webDevCategory->id, $tutorialTag->id]);
        }

        $article2 = Entry::firstOrCreate(
            [
                'post_type_id' => $articlePostType->id,
                'title' => 'AI Trends in 2025',
            ],
            [
                'title' => 'AI Trends in 2025',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(3),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Top AI Trends for 2025</h1><p>Artificial Intelligence continues to evolve...</p>',
                    'excerpt' => 'Explore the most important AI trends shaping 2025.',
                ],
            ]
        );
        $aiCategory = Term::where('taxonomy_id', $categoriesTaxonomy->id)
            ->where('name', 'Artificial Intelligence')
            ->first();
        if ($aiCategory && $newsTag) {
            $article2->terms()->sync([$aiCategory->id, $newsTag->id]);
        }

        $article3 = Entry::firstOrCreate(
            [
                'post_type_id' => $articlePostType->id,
                'title' => 'Complete Guide to Mobile App Development',
            ],
            [
                'title' => 'Complete Guide to Mobile App Development',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDay(),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Mobile App Development Guide</h1><p>Building mobile apps requires careful planning...</p>',
                    'excerpt' => 'A comprehensive guide to mobile app development.',
                ],
            ]
        );
        $mobileDevCategory = Term::where('taxonomy_id', $categoriesTaxonomy->id)
            ->where('name', 'Mobile Development')
            ->first();
        if ($mobileDevCategory && $tutorialTag) {
            $article3->terms()->sync([$mobileDevCategory->id, $tutorialTag->id]);
        }

        // Создаем Products
        $usaRegion = Term::where('taxonomy_id', $regionsTaxonomy->id)
            ->where('name', 'United States')
            ->first();
        $europeRegion = Term::where('taxonomy_id', $regionsTaxonomy->id)
            ->where('name', 'Europe')
            ->first();

        $product1 = Entry::firstOrCreate(
            [
                'post_type_id' => $productPostType->id,
                'title' => 'Premium Software License',
            ],
            [
                'title' => 'Premium Software License',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(10),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Premium Software License</h1><p>Get access to all premium features...</p>',
                    'price' => 99.99,
                    'currency' => 'USD',
                ],
            ]
        );
        if ($techCategory && $usaRegion) {
            $product1->terms()->sync([$techCategory->id, $usaRegion->id]);
        }

        $product2 = Entry::firstOrCreate(
            [
                'post_type_id' => $productPostType->id,
                'title' => 'Enterprise Solution Package',
            ],
            [
                'title' => 'Enterprise Solution Package',
                'status' => Entry::STATUS_PUBLISHED,
                'published_at' => now()->subDays(7),
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Enterprise Solution</h1><p>Complete enterprise package for large organizations...</p>',
                    'price' => 999.99,
                    'currency' => 'USD',
                ],
            ]
        );
        if ($techCategory && $europeRegion) {
            $product2->terms()->sync([$techCategory->id, $europeRegion->id]);
        }

        // Создаем несколько черновиков
        Entry::firstOrCreate(
            [
                'post_type_id' => $articlePostType->id,
                'title' => 'Upcoming Features (Draft)',
            ],
            [
                'title' => 'Upcoming Features (Draft)',
                'status' => Entry::STATUS_DRAFT,
                'published_at' => null,
                'author_id' => $adminUser->id,
                'data_json' => [
                    'content' => '<h1>Upcoming Features</h1><p>This is a draft article...</p>',
                ],
            ]
        );

        if ($this->command) {
            $this->command->info('Entries created successfully!');
            $this->command->info('Pages: ' . Entry::where('post_type_id', $pagePostType->id)->count());
            $this->command->info('Articles: ' . Entry::where('post_type_id', $articlePostType->id)->count());
            $this->command->info('Products: ' . Entry::where('post_type_id', $productPostType->id)->count());
            $this->command->info('Published: ' . Entry::where('status', Entry::STATUS_PUBLISHED)->count());
            $this->command->info('Drafts: ' . Entry::where('status', Entry::STATUS_DRAFT)->count());
        }
    }
}
