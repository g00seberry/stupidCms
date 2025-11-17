<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Services\Plugins;

use App\Domain\Media\Services\FfprobeMediaMetadataPlugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FfprobeMediaMetadataPluginTest extends TestCase
{
    /**
     * Тест: плагин ffprobe извлекает метаданные видео.
     */
    public function test_ffprobe_plugin_extracts_video_metadata(): void
    {
        $plugin = new FfprobeMediaMetadataPlugin();

        // Проверяем поддержку видео
        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertTrue($plugin->supports('video/webm'));
        $this->assertFalse($plugin->supports('image/jpeg'));

        // Для реального теста нужен файл и установленный ffprobe
        // В unit-тестах проверяем только логику
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('normalize');
        $method->setAccessible(true);

        $ffprobeData = [
            'format' => [
                'duration' => '125.5',
                'bit_rate' => '5000000',
            ],
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'nb_frames' => '3000',
                    'avg_frame_rate' => '25/1',
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                ],
            ],
        ];

        $result = $method->invoke($plugin, $ffprobeData);

        $this->assertSame(125500, $result['duration_ms']);
        $this->assertSame(5000, $result['bitrate_kbps']);
        $this->assertSame(25.0, $result['frame_rate']);
        $this->assertSame(3000, $result['frame_count']);
        $this->assertSame('h264', $result['video_codec']);
        $this->assertSame('aac', $result['audio_codec']);
    }

    /**
     * Тест: плагины обрабатывают отсутствие исполняемых файлов.
     */
    public function test_plugins_handle_missing_executables(): void
    {
        // Создаём плагин с несуществующим бинарником
        $plugin = new FfprobeMediaMetadataPlugin('/nonexistent/ffprobe');

        // При отсутствии бинарника extract должен вернуть пустой массив
        $result = $plugin->extract('/nonexistent/file.mp4');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины обрабатывают ошибки выполнения.
     */
    public function test_plugins_handle_execution_failure(): void
    {
        $plugin = new FfprobeMediaMetadataPlugin();

        // При ошибке выполнения extract должен вернуть пустой массив
        $result = $plugin->extract('');

        $this->assertSame([], $result);

        // Несуществующий файл
        $result = $plugin->extract('/nonexistent/file.mp4');

        $this->assertSame([], $result);
    }

    /**
     * Тест: плагины поддерживают корректные MIME-типы.
     */
    public function test_plugins_support_correct_mime_types(): void
    {
        $plugin = new FfprobeMediaMetadataPlugin();

        // Видео
        $this->assertTrue($plugin->supports('video/mp4'));
        $this->assertTrue($plugin->supports('video/webm'));
        $this->assertTrue($plugin->supports('video/quicktime'));

        // Аудио
        $this->assertTrue($plugin->supports('audio/mpeg'));
        $this->assertTrue($plugin->supports('audio/mp4'));
        $this->assertTrue($plugin->supports('audio/aiff'));

        // Не поддерживает изображения
        $this->assertFalse($plugin->supports('image/jpeg'));
        $this->assertFalse($plugin->supports('image/png'));
    }
}

