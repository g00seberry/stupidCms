<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Services;

use App\Domain\Media\Services\CollectionRulesResolver;
use Tests\TestCase;

/**
 * Тесты для CollectionRulesResolver.
 *
 * Проверяет резолвинг правил валидации для коллекций медиа.
 */
final class CollectionRulesResolverTest extends TestCase
{
    private CollectionRulesResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CollectionRulesResolver();
    }

    public function test_returns_collection_specific_rules(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg', 'image/png']);
        config()->set('media.max_upload_mb', 25);
        config()->set('media.collections', [
            'thumbnails' => [
                'allowed_mimes' => ['image/jpeg', 'image/webp'],
                'max_size_bytes' => 5 * 1024 * 1024,
                'max_width' => 1920,
                'max_height' => 1080,
            ],
        ]);

        $rules = $this->resolver->getRules('thumbnails');

        $this->assertSame(['image/jpeg', 'image/webp'], $rules['allowed_mimes']);
        $this->assertSame(5 * 1024 * 1024, $rules['max_size_bytes']);
        $this->assertSame(1920, $rules['max_width']);
        $this->assertSame(1080, $rules['max_height']);
    }

    public function test_returns_global_rules_for_null_collection(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg', 'image/png']);
        config()->set('media.max_upload_mb', 25);
        config()->set('media.collections', []);

        $rules = $this->resolver->getRules(null);

        $this->assertSame(['image/jpeg', 'image/png'], $rules['allowed_mimes']);
        $this->assertSame(25 * 1024 * 1024, $rules['max_size_bytes']);
        $this->assertNull($rules['max_width']);
        $this->assertNull($rules['max_height']);
    }

    public function test_returns_global_rules_for_empty_collection(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg']);
        config()->set('media.max_upload_mb', 10);
        config()->set('media.collections', []);

        $rules = $this->resolver->getRules('');

        $this->assertSame(['image/jpeg'], $rules['allowed_mimes']);
        $this->assertSame(10 * 1024 * 1024, $rules['max_size_bytes']);
    }

    public function test_merges_collection_rules_with_global(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg', 'image/png']);
        config()->set('media.max_upload_mb', 25);
        config()->set('media.collections', [
            'videos' => [
                'max_size_bytes' => 100 * 1024 * 1024,
                'max_duration_ms' => 300000,
            ],
        ]);

        $rules = $this->resolver->getRules('videos');

        // Глобальные значения сохраняются
        $this->assertSame(['image/jpeg', 'image/png'], $rules['allowed_mimes']);
        // Значения коллекции переопределяют глобальные
        $this->assertSame(100 * 1024 * 1024, $rules['max_size_bytes']);
        $this->assertSame(300000, $rules['max_duration_ms']);
    }

    public function test_returns_allowed_mimes_for_collection(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg']);
        config()->set('media.collections', [
            'gallery' => [
                'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ]);

        $mimes = $this->resolver->getAllowedMimes('gallery');

        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], $mimes);
    }

    public function test_returns_max_size_bytes_for_collection(): void
    {
        config()->set('media.max_upload_mb', 25);
        config()->set('media.collections', [
            'documents' => [
                'max_size_bytes' => 50 * 1024 * 1024,
            ],
        ]);

        $maxSize = $this->resolver->getMaxSizeBytes('documents');

        $this->assertSame(50 * 1024 * 1024, $maxSize);
    }

    public function test_handles_missing_collection_config(): void
    {
        config()->set('media.allowed_mimes', ['image/jpeg']);
        config()->set('media.max_upload_mb', 25);
        config()->set('media.collections', []);

        $rules = $this->resolver->getRules('nonexistent');

        $this->assertSame(['image/jpeg'], $rules['allowed_mimes']);
        $this->assertSame(25 * 1024 * 1024, $rules['max_size_bytes']);
    }
}

