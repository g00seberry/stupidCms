<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use RuntimeException;

/**
 * Плагин метаданных, основанный на утилите exiftool.
 *
 * Использует exiftool для извлечения детальных метаданных из изображений,
 * видео и аудио файлов. Особенно полезен для EXIF данных и метаданных,
 * недоступных через другие инструменты.
 *
 * @package App\Domain\Media\Services
 */
class ExiftoolMediaMetadataPlugin implements MediaMetadataPlugin
{
    /**
     * @var string
     */
    private string $binary;

    /**
     * @param string|null $binary Путь к бинарнику exiftool (по умолчанию 'exiftool')
     */
    public function __construct(?string $binary = null)
    {
        $this->binary = $binary !== null && $binary !== '' ? $binary : 'exiftool';
    }

    /**
     * Проверить, поддерживает ли плагин указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если плагин может обработать файл
     */
    public function supports(string $mime): bool
    {
        // exiftool поддерживает изображения, видео и аудио
        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/');
    }

    /**
     * Извлечь нормализованные метаданные из файла.
     *
     * @param string $path Абсолютный путь к файлу
     * @return array{
     *     duration_ms?: int|null,
     *     bitrate_kbps?: int|null,
     *     frame_rate?: float|null,
     *     frame_count?: int|null,
     *     video_codec?: string|null,
     *     audio_codec?: string|null
     * }
     */
    public function extract(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        $command = sprintf(
            '%s -j -n %s',
            escapeshellcmd($this->binary),
            escapeshellarg($path)
        );

        $output = $this->runCommand($command);

        if ($output === null) {
            return [];
        }

        /** @var array<array<string, mixed>>|null $data */
        $data = json_decode($output, true);
        if (! is_array($data) || empty($data)) {
            return [];
        }

        $metadata = $data[0] ?? [];

        return $this->normalize($metadata);
    }

    /**
     * Выполнить команду оболочки и вернуть stdout или null при ошибке.
     *
     * @param string $command Команда для выполнения
     * @return string|null
     */
    protected function runCommand(string $command): ?string
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $stdout === false || $stdout === '') {
            return null;
        }

        return $stdout;
    }

    /**
     * Нормализовать структуру exiftool JSON в плоский массив значений.
     *
     * @param array<string, mixed> $data Данные exiftool
     * @return array{
     *     duration_ms?: int|null,
     *     bitrate_kbps?: int|null,
     *     frame_rate?: float|null,
     *     frame_count?: int|null,
     *     video_codec?: string|null,
     *     audio_codec?: string|null
     * }
     */
    private function normalize(array $data): array
    {
        $durationMs = null;
        $bitrateKbps = null;
        $frameRate = null;
        $frameCount = null;
        $videoCodec = null;
        $audioCodec = null;

        // Длительность
        $duration = $data['Duration'] ?? $data['MediaDuration'] ?? null;
        if ($duration !== null) {
            $durationMs = $this->parseDuration($duration);
        }

        // Битрейт
        $bitrate = $data['VideoBitrate'] ?? $data['AudioBitrate'] ?? $data['Bitrate'] ?? null;
        if ($bitrate !== null) {
            $bitrateKbps = $this->parseBitrate($bitrate);
        }

        // Частота кадров
        $fps = $data['VideoFrameRate'] ?? $data['FrameRate'] ?? null;
        if ($fps !== null) {
            $frameRate = $this->parseFrameRate($fps);
        }

        // Количество кадров
        $frames = $data['FrameCount'] ?? $data['ImageCount'] ?? null;
        if ($frames !== null) {
            $frameCount = $this->parseInt($frames);
        }

        // Видео кодек
        $video = $data['VideoCodec'] ?? $data['Compression'] ?? null;
        if ($video !== null) {
            $videoCodec = $this->parseString($video);
        }

        // Аудио кодек
        $audio = $data['AudioCodec'] ?? $data['AudioCompression'] ?? null;
        if ($audio !== null) {
            $audioCodec = $this->parseString($audio);
        }

        return [
            'duration_ms' => $durationMs,
            'bitrate_kbps' => $bitrateKbps,
            'frame_rate' => $frameRate,
            'frame_count' => $frameCount,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
        ];
    }

    /**
     * Распарсить длительность из строки вида "123.456" или "0:02:03".
     *
     * @param mixed $value Значение длительности
     * @return int|null Длительность в миллисекундах
     */
    private function parseDuration(mixed $value): ?int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round(((float) $value) * 1000);
        }

        // Формат "H:MM:SS" или "M:SS"
        if (preg_match('/^(\d+):(\d+):(\d+)$/', $value, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            return ($hours * 3600 + $minutes * 60 + $seconds) * 1000;
        }

        if (preg_match('/^(\d+):(\d+)$/', $value, $matches)) {
            $minutes = (int) $matches[1];
            $seconds = (int) $matches[2];

            return ($minutes * 60 + $seconds) * 1000;
        }

        return null;
    }

    /**
     * Распарсить битрейт из строки вида "1234567" или "1234 kbps".
     *
     * @param mixed $value Значение битрейта
     * @return int|null Битрейт в килобитах в секунду
     */
    private function parseBitrate(mixed $value): ?int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round(((float) $value) / 1000);
        }

        if (preg_match('/([\d.]+)\s*(?:kbps|Kbps|KBPS|Mbps)?/i', $value, $matches)) {
            $rate = (float) $matches[1];
            if (stripos($value, 'mbps') !== false || stripos($value, 'M') !== false) {
                return (int) round($rate * 1000);
            }

            if (stripos($value, 'kbps') !== false || stripos($value, 'k') !== false) {
                return (int) round($rate);
            }

            return (int) round($rate / 1000);
        }

        return null;
    }

    /**
     * Распарсить частоту кадров из строки вида "25.000" или "30000/1001".
     *
     * @param mixed $value Значение частоты кадров
     * @return float|null
     */
    private function parseFrameRate(mixed $value): ?float
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (str_contains($value, '/')) {
            [$num, $den] = explode('/', $value, 2);

            if (is_numeric($num) && is_numeric($den) && (float) $den !== 0.0) {
                return (float) $num / (float) $den;
            }
        }

        return null;
    }

    /**
     * Распарсить строковое значение.
     *
     * @param mixed $value Значение
     * @return string|null
     */
    private function parseString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Распарсить целочисленное значение.
     *
     * @param mixed $value Значение
     * @return int|null
     */
    private function parseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}

