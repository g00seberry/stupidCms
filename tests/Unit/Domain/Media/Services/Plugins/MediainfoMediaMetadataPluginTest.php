<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Services\Plugins;

use App\Domain\Media\Services\MediainfoMediaMetadataPlugin;
use PHPUnit\Framework\TestCase;

final class MediainfoMediaMetadataPluginTest extends TestCase
{
    /**
     * Тест: плагин mediainfo извлекает метаданные аудио.
     */
    public function test_mediainfo_plugin_extracts_audio_metadata(): void
    {
        $plugin = new MediainfoMediaMetadataPlugin();

        // Проверяем поддержку аудио
        $this->assertTrue($plugin->supports('audio/mpeg'));
        $this->assertTrue($plugin->supports('audio/aiff'));
        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertFalse($plugin->supports('image/jpeg'));

        // При отсутствии бинарника extract должен вернуть пустой массив
        $result = $plugin->extract('/nonexistent/file.mp3');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины обрабатывают отсутствие исполняемых файлов.
     */
    public function test_plugins_handle_missing_executables(): void
    {
        $plugin = new MediainfoMediaMetadataPlugin('/nonexistent/mediainfo');

        $result = $plugin->extract('/nonexistent/file.mp3');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины обрабатывают ошибки выполнения.
     */
    public function test_plugins_handle_execution_failure(): void
    {
        $plugin = new MediainfoMediaMetadataPlugin();

        $result = $plugin->extract('');

        $this->assertSame([], $result);

        $result = $plugin->extract('/nonexistent/file.mp3');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины поддерживают корректные MIME-типы.
     */
    public function test_plugins_support_correct_mime_types(): void
    {
        $plugin = new MediainfoMediaMetadataPlugin();

        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertTrue($plugin->supports('audio/mpeg'));
        $this->assertTrue($plugin->supports('audio/aiff'));
        $this->assertFalse($plugin->supports('image/jpeg'));
    }
}

