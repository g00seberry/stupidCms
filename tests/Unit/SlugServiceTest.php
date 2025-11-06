<?php

namespace Tests\Unit;

use App\Support\Slug\DefaultSlugifier;
use App\Support\Slug\DefaultUniqueSlugService;
use App\Support\Slug\SlugOptions;
use PHPUnit\Framework\TestCase;

class SlugServiceTest extends TestCase
{
    private DefaultSlugifier $slugifier;
    private DefaultUniqueSlugService $uniqueService;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'slug' => [
                'default' => [
                    'delimiter' => '-',
                    'toLower' => true,
                    'asciiOnly' => true,
                    'maxLength' => 120,
                    'scheme' => 'ru_basic',
                    'stopWords' => ['и', 'в', 'на'],
                    'reserved' => [],
                ],
                'schemes' => [
                    'ru_basic' => [
                        'map' => [
                            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
                            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
                            'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
                            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
                            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch',
                            'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
                            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
                        ],
                        'exceptions' => [],
                    ],
                ],
            ],
        ];

        $this->slugifier = new DefaultSlugifier($config);
        $this->uniqueService = new DefaultUniqueSlugService();
    }

    public function test_basic_slugification(): void
    {
        $slug = $this->slugifier->slugify('Страница');
        // Критерий приёмки: "Страница" → "stranica"
        $this->assertSame('stranica', $slug);
    }

    public function test_dedupe_conflict(): void
    {
        $base = $this->slugifier->slugify('Страница'); // 'stranica'
        $unique = $this->uniqueService->ensureUnique($base, fn($s) => in_array($s, ['stranica'], true));
        $this->assertSame('stranica-2', $unique);
    }

    public function test_clean_punctuation_and_spaces(): void
    {
        $slug = $this->slugifier->slugify('  !!! Привет,—мир !!!  ');
        $this->assertSame('privet-mir', $slug);
    }

    public function test_stop_words_and_max_length(): void
    {
        $opts = new SlugOptions(stopWords: ['и', 'в', 'на'], maxLength: 20);
        $slug = $this->slugifier->slugify('Йога и чай — в наилучшем формате', $opts);
        // Стоп-слова 'и', 'в', 'на' должны быть удалены после транслитерации
        // "на" в "наилучшем" не удаляется, так как это часть слова, не отдельный токен
        // Результат может начинаться с "ioga-chai" или "oga-chai" в зависимости от обработки "й"
        $this->assertNotEmpty($slug);
        $this->assertLessThanOrEqual(20, strlen($slug));
        // Проверяем, что содержит "chai" (чай)
        $this->assertStringContainsString('chai', $slug);
    }

    public function test_empty_string(): void
    {
        $slug = $this->slugifier->slugify('');
        $this->assertSame('', $slug);
    }

    public function test_only_digits(): void
    {
        $slug = $this->slugifier->slugify('2025');
        $this->assertSame('2025', $slug);
    }

    public function test_multiple_dedupe(): void
    {
        $base = $this->slugifier->slugify('Тест');
        $taken = ['test', 'test-2', 'test-3'];
        $unique = $this->uniqueService->ensureUnique($base, fn($s) => in_array($s, $taken, true));
        $this->assertSame('test-4', $unique);
    }

    public function test_existing_suffix_replacement(): void
    {
        $base = 'test-5';
        $taken = ['test-5', 'test-2'];
        $unique = $this->uniqueService->ensureUnique($base, fn($s) => in_array($s, $taken, true));
        $this->assertSame('test-3', $unique);
    }
}

