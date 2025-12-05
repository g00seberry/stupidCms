<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\PostType;
use App\Models\User;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Database\Seeder;

/**
 * Seeder для создания примеров Blueprint с различной сложностью.
 *
 * Создает:
 * - Простые blueprint'ы с базовыми полями
 * - Сложные blueprint'ы с вложенными структурами
 * - Встраивания (embed) между blueprint'ами
 * - Привязку к PostType
 */
class BlueprintsSeeder extends Seeder
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {
    }

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

        $this->command->info('🔷 Creating simple blueprints...');
        $simpleProduct = $this->createSimpleProduct();
        $simpleAuthor = $this->createSimpleAuthor();

        $this->command->info('🔷 Creating blueprints with nested fields...');
        $address = $this->createAddress();
        $contacts = $this->createContacts();
        $seo = $this->createSeo();

        $this->command->info('🔷 Creating complex blueprints...');
        $person = $this->createPerson();
        $company = $this->createCompany();
        $complexArticle = $this->createComplexArticle();

        $this->command->info('🔷 Creating embeds (simple)...');
        $this->embedAddressIntoPerson($person, $address);

        $this->command->info('🔷 Creating embeds (multiple)...');
        $this->embedAddressIntoCompany($company, $address);

        $this->command->info('🔷 Creating embeds (transitive)...');
        $this->embedContactsIntoPerson($person, $contacts);
        $this->embedSeoIntoArticle($complexArticle, $seo);

        $this->command->info('🔷 Attaching blueprints to PostTypes...');
        $this->attachToPostTypes($simpleProduct, $complexArticle);

        $this->command->info('✅ Blueprints seeding completed!');
        $this->printSummary();
    }

    // ===========================================
    // ПРОСТЫЕ BLUEPRINT'Ы (без вложенности)
    // ===========================================

    /**
     * Простой blueprint для продукта с базовыми полями.
     */
    private function createSimpleProduct(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Simple Product',
            'code' => 'simple_product',
            'description' => 'Простая структура продукта с базовыми полями',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'sku',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'price',
            'data_type' => 'float',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'in_stock',
            'data_type' => 'bool',
            'is_indexed' => true,
            'sort_order' => 40,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'description',
            'data_type' => 'text',
            'sort_order' => 50,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 5 fields");
        return $blueprint;
    }

    /**
     * Простой blueprint для автора.
     */
    private function createSimpleAuthor(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Author',
            'code' => 'author',
            'description' => 'Автор статьи/поста',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'name',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'email',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'bio',
            'data_type' => 'text',
            'sort_order' => 30,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 3 fields");
        return $blueprint;
    }

    // ===========================================
    // BLUEPRINT'Ы С ВЛОЖЕННЫМИ ПОЛЯМИ (json)
    // ===========================================

    /**
     * Blueprint для адреса с вложенными полями.
     */
    private function createAddress(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Address',
            'code' => 'address',
            'description' => 'Адрес (переиспользуемый компонент)',
        ]);

        // Корневое поле группы (не обязательно, но показывает группировку)
        $addressGroup = $this->structureService->createPath($blueprint, [
            'name' => 'location',
            'data_type' => 'json',
            'sort_order' => 10,
        ]);

        // Вложенные поля адреса
        $this->structureService->createPath($blueprint, [
            'name' => 'street',
            'parent_id' => $addressGroup->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'city',
            'parent_id' => $addressGroup->id,
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'postal_code',
            'parent_id' => $addressGroup->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'country',
            'parent_id' => $addressGroup->id,
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 40,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with nested fields (1 group + 4 fields)");
        return $blueprint;
    }

    /**
     * Blueprint для контактов с вложенными полями.
     */
    private function createContacts(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Contacts',
            'code' => 'contacts',
            'description' => 'Контактная информация',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'phone',
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'mobile',
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'email',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'website',
            'data_type' => 'string',
            'sort_order' => 40,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 4 fields");
        return $blueprint;
    }

    /**
     * Blueprint для SEO метаданных.
     */
    private function createSeo(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'SEO Metadata',
            'code' => 'seo',
            'description' => 'SEO метаданные для страниц и статей',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'meta_title',
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'meta_description',
            'data_type' => 'text',
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'meta_keywords',
            'data_type' => 'string',
            'sort_order' => 30,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'og_image',
            'data_type' => 'string',
            'sort_order' => 40,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'canonical_url',
            'data_type' => 'string',
            'sort_order' => 50,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 5 fields");
        return $blueprint;
    }

    // ===========================================
    // СЛОЖНЫЕ BLUEPRINT'Ы (со встраиваниями)
    // ===========================================

    /**
     * Blueprint для персоны (будет встраивать Address и Contacts).
     */
    private function createPerson(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Person',
            'code' => 'person',
            'description' => 'Персона с адресом и контактами (демонстрирует embed)',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'first_name',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'last_name',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'birth_date',
            'data_type' => 'datetime',
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        // Группы для встраивания
        $homeAddressGroup = $this->structureService->createPath($blueprint, [
            'name' => 'home_address',
            'data_type' => 'json',
            'sort_order' => 100,
        ]);

        $contactsGroup = $this->structureService->createPath($blueprint, [
            'name' => 'contacts',
            'data_type' => 'json',
            'sort_order' => 200,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 3 fields + 2 groups for embeds");
        return $blueprint;
    }

    /**
     * Blueprint для компании (будет встраивать Address дважды: офис и юр. адрес).
     */
    private function createCompany(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Company',
            'code' => 'company',
            'description' => 'Компания с множественным встраиванием адресов',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'name',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'registration_number',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'founded_at',
            'data_type' => 'datetime',
            'is_indexed' => true,
            'sort_order' => 30,
        ]);

        // Две группы для разных адресов
        $officeGroup = $this->structureService->createPath($blueprint, [
            'name' => 'office_address',
            'data_type' => 'json',
            'sort_order' => 100,
        ]);

        $legalGroup = $this->structureService->createPath($blueprint, [
            'name' => 'legal_address',
            'data_type' => 'json',
            'sort_order' => 200,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 3 fields + 2 groups for multiple embeds");
        return $blueprint;
    }

    /**
     * Blueprint для сложной статьи (будет встраивать SEO).
     */
    private function createComplexArticle(): Blueprint
    {
        $blueprint = $this->structureService->createBlueprint([
            'name' => 'Complex Article',
            'code' => 'complex_article',
            'description' => 'Сложная структура статьи с SEO и метаданными',
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'title',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'slug',
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'content',
            'data_type' => 'text',
            'validation_rules' => ['required' => true],
            'sort_order' => 30,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'excerpt',
            'data_type' => 'text',
            'sort_order' => 40,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'published_at',
            'data_type' => 'datetime',
            'is_indexed' => true,
            'sort_order' => 50,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'reading_time_minutes',
            'data_type' => 'int',
            'is_indexed' => true,
            'sort_order' => 60,
        ]);

        // Группа автора
        $authorGroup = $this->structureService->createPath($blueprint, [
            'name' => 'author',
            'data_type' => 'json',
            'sort_order' => 100,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'name',
            'parent_id' => $authorGroup->id,
            'data_type' => 'string',
            'validation_rules' => ['required' => true],
            'is_indexed' => true,
            'sort_order' => 10,
        ]);

        $this->structureService->createPath($blueprint, [
            'name' => 'email',
            'parent_id' => $authorGroup->id,
            'data_type' => 'string',
            'is_indexed' => true,
            'sort_order' => 20,
        ]);

        // Группа для SEO (будет встроен blueprint)
        $seoGroup = $this->structureService->createPath($blueprint, [
            'name' => 'seo',
            'data_type' => 'json',
            'sort_order' => 200,
        ]);

        // Массив связанных статей (ref)
        $this->structureService->createPath($blueprint, [
            'name' => 'related_articles',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
            'sort_order' => 300,
        ]);

        $this->command->info("  ✓ Created '{$blueprint->code}' with 6 fields + author group + SEO group + refs");
        return $blueprint;
    }

    // ===========================================
    // ВСТРАИВАНИЯ (EMBEDS)
    // ===========================================

    /**
     * Встроить Address в Person.
     */
    private function embedAddressIntoPerson(Blueprint $person, Blueprint $address): void
    {
        $homeAddressPath = $person->paths()->where('name', 'home_address')->first();

        $this->structureService->createEmbed($person, $address, $homeAddressPath);

        $this->command->info("  ✓ Embedded '{$address->code}' → '{$person->code}.home_address'");
    }

    /**
     * Встроить Contacts в Person.
     */
    private function embedContactsIntoPerson(Blueprint $person, Blueprint $contacts): void
    {
        $contactsPath = $person->paths()
            ->where('name', 'contacts')
            ->where('source_blueprint_id', null) // только собственные
            ->first();

        $this->structureService->createEmbed($person, $contacts, $contactsPath);

        $this->command->info("  ✓ Embedded '{$contacts->code}' → '{$person->code}.contacts'");
    }

    /**
     * Встроить Address в Company (множественное встраивание).
     */
    private function embedAddressIntoCompany(Blueprint $company, Blueprint $address): void
    {
        $officeAddressPath = $company->paths()->where('name', 'office_address')->first();
        $legalAddressPath = $company->paths()->where('name', 'legal_address')->first();

        $this->structureService->createEmbed($company, $address, $officeAddressPath);
        $this->structureService->createEmbed($company, $address, $legalAddressPath);

        $this->command->info("  ✓ Embedded '{$address->code}' → '{$company->code}.office_address'");
        $this->command->info("  ✓ Embedded '{$address->code}' → '{$company->code}.legal_address'");
    }

    /**
     * Встроить SEO в ComplexArticle.
     */
    private function embedSeoIntoArticle(Blueprint $article, Blueprint $seo): void
    {
        $seoPath = $article->paths()
            ->where('name', 'seo')
            ->where('source_blueprint_id', null) // только собственные
            ->first();

        $this->structureService->createEmbed($article, $seo, $seoPath);

        $this->command->info("  ✓ Embedded '{$seo->code}' → '{$article->code}.seo'");
    }

    // ===========================================
    // ПРИВЯЗКА К POSTTYPE
    // ===========================================

    /**
     * Привязать blueprint'ы к существующим PostType.
     */
    private function attachToPostTypes(Blueprint $simpleProduct, Blueprint $complexArticle): void
    {
        // Привязать Simple Product к product
        $productPostType = PostType::where('name', 'Product')->first();
        if ($productPostType) {
            $productPostType->update(['blueprint_id' => $simpleProduct->id]);
            $this->command->info("  ✓ Attached '{$simpleProduct->code}' to PostType 'Product'");
        } else {
            $this->command->warn("  ⚠ PostType 'Product' not found, skipping attachment");
        }

        // Привязать Complex Article к article
        $articlePostType = PostType::where('name', 'Article')->first();
        if ($articlePostType) {
            $articlePostType->update(['blueprint_id' => $complexArticle->id]);
            $this->command->info("  ✓ Attached '{$complexArticle->code}' to PostType 'Article'");
        } else {
            $this->command->warn("  ⚠ PostType 'Article' not found, skipping attachment");
        }
    }

    // ===========================================
    // ВЫВОД СТАТИСТИКИ
    // ===========================================

    /**
     * Вывести сводную статистику.
     */
    private function printSummary(): void
    {
        $blueprintsCount = Blueprint::count();
        $pathsCount = \App\Models\Path::count();
        $embedsCount = \App\Models\BlueprintEmbed::count();
        $ownPathsCount = \App\Models\Path::whereNull('source_blueprint_id')->count();
        $copiedPathsCount = \App\Models\Path::whereNotNull('source_blueprint_id')->count();

        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info("  • Blueprints: {$blueprintsCount}");
        $this->command->info("  • Total Paths: {$pathsCount}");
        $this->command->info("    - Own paths: {$ownPathsCount}");
        $this->command->info("    - Copied paths (materialized): {$copiedPathsCount}");
        $this->command->info("  • Embeds: {$embedsCount}");
    }
}

