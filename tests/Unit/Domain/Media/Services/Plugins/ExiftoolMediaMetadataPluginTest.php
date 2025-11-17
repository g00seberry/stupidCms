<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Services\Plugins;

use App\Domain\Media\Services\ExiftoolMediaMetadataPlugin;
use PHPUnit\Framework\TestCase;

final class ExiftoolMediaMetadataPluginTest extends TestCase
{
    /**
     * Тест: плагин exiftool извлекает метаданные изображения.
     */
    public function test_exiftool_plugin_extracts_image_metadata(): void
    {
        $plugin = new ExiftoolMediaMetadataPlugin();

        // Проверяем поддержку изображений
        $this->assertTrue($plugin->supports('image/jpeg'));
        $this->assertTrue($plugin->supports('image/png'));
        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertTrue($plugin->supports('audio/mpeg'));

        // При отсутствии бинарника extract должен вернуть пустой массив
        $result = $plugin->extract('/nonexistent/file.jpg');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины обрабатывают отсутствие исполняемых файлов.
     */
    public function test_plugins_handle_missing_executables(): void
    {
        $plugin = new ExiftoolMediaMetadataPlugin('/nonexistent/exiftool');

        $result = $plugin->extract('/nonexistent/file.jpg');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины обрабатывают ошибки выполнения.
     */
    public function test_plugins_handle_execution_failure(): void
    {
        $plugin = new ExiftoolMediaMetadataPlugin();

        $result = $plugin->extract('');

        $this->assertSame([], $result);

        $result = $plugin->extract('/nonexistent/file.jpg');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины поддерживают корректные MIME-типы.
     */
    public function test_plugins_support_correct_mime_types(): void
    {
        $plugin = new ExiftoolMediaMetadataPlugin();

        // Изображения
        $this->assertTrue($plugin->supports('image/jpeg'));
        $this->assertTrue($plugin->supports('image/png'));
        $this->assertTrue($plugin->supports('image/webp'));

        // Видео
        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertTrue($plugin->supports('video/webm'));

        // Аудио
        $this->assertTrue($plugin->supports('audio/mpeg'));
        $this->assertTrue($plugin->supports('audio/aiff'));
    }
}

