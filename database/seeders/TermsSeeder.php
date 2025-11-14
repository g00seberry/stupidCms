<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\TermHierarchy\TermHierarchyService;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания термов для всех таксономий.
 *
 * Создает термы для:
 * - Categories (иерархические): Technology, Science, Arts (с подкатегориями)
 * - Tags (плоские): news, tutorial, review, guide
 * - Regions (иерархические): Europe, Asia, Americas (с подрегионами)
 * - Topics (плоские): programming, design, marketing, business
 */
class TermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hierarchyService = app(TermHierarchyService::class);

        // Получаем таксономии из PostTypesTaxonomiesSeeder
        $categoriesTaxonomy = Taxonomy::where('name', 'Categories')->first();
        $tagsTaxonomy = Taxonomy::where('name', 'Tags')->first();
        $regionsTaxonomy = Taxonomy::where('name', 'Regions')->first();
        $topicsTaxonomy = Taxonomy::where('name', 'Topics')->first();

        if (!$categoriesTaxonomy || !$tagsTaxonomy || !$regionsTaxonomy || !$topicsTaxonomy) {
            $this->command->warn('Taxonomies not found. Please run PostTypesTaxonomiesSeeder first.');
            return;
        }

        // Создаем иерархические термы для Categories
        $techCategory = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Technology',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($techCategory, null);

        $scienceCategory = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Science',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($scienceCategory, null);

        $artsCategory = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Arts',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($artsCategory, null);

        // Подкатегории для Technology
        $webDev = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Web Development',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($webDev, $techCategory->id);

        $mobileDev = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Mobile Development',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($mobileDev, $techCategory->id);

        $ai = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Artificial Intelligence',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($ai, $techCategory->id);

        // Подкатегории для Science
        $physics = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Physics',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($physics, $scienceCategory->id);

        $biology = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Biology',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($biology, $scienceCategory->id);

        // Подкатегории для Arts
        $music = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Music',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($music, $artsCategory->id);

        $painting = Term::firstOrCreate(
            [
                'taxonomy_id' => $categoriesTaxonomy->id,
                'name' => 'Painting',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($painting, $artsCategory->id);

        // Плоские термы для Tags
        $tags = ['News', 'Tutorial', 'Review', 'Guide', 'Announcement', 'Update'];
        foreach ($tags as $tagName) {
            Term::firstOrCreate(
                [
                    'taxonomy_id' => $tagsTaxonomy->id,
                    'name' => $tagName,
                ],
                ['meta_json' => []]
            );
        }

        // Иерархические термы для Regions
        $europe = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Europe',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($europe, null);

        $asia = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Asia',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($asia, null);

        $americas = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Americas',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($americas, null);

        // Подрегионы для Europe
        $uk = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'United Kingdom',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($uk, $europe->id);

        $germany = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Germany',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($germany, $europe->id);

        $france = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'France',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($france, $europe->id);

        // Подрегионы для Asia
        $china = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'China',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($china, $asia->id);

        $japan = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Japan',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($japan, $asia->id);

        $india = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'India',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($india, $asia->id);

        // Подрегионы для Americas
        $usa = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'United States',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($usa, $americas->id);

        $canada = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Canada',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($canada, $americas->id);

        $brazil = Term::firstOrCreate(
            [
                'taxonomy_id' => $regionsTaxonomy->id,
                'name' => 'Brazil',
            ],
            ['meta_json' => []]
        );
        $hierarchyService->setParent($brazil, $americas->id);

        // Плоские термы для Topics
        $topics = ['Programming', 'Design', 'Marketing', 'Business', 'Education', 'Health'];
        foreach ($topics as $topicName) {
            Term::firstOrCreate(
                [
                    'taxonomy_id' => $topicsTaxonomy->id,
                    'name' => $topicName,
                ],
                ['meta_json' => []]
            );
        }

        if ($this->command) {
            $this->command->info('Terms created successfully!');
            $this->command->info('Categories: ' . Term::where('taxonomy_id', $categoriesTaxonomy->id)->count());
            $this->command->info('Tags: ' . Term::where('taxonomy_id', $tagsTaxonomy->id)->count());
            $this->command->info('Regions: ' . Term::where('taxonomy_id', $regionsTaxonomy->id)->count());
            $this->command->info('Topics: ' . Term::where('taxonomy_id', $topicsTaxonomy->id)->count());
        }
    }
}
