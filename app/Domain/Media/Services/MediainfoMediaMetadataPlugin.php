<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use RuntimeException;

/**
 * Плагин метаданных, основанный на утилите mediainfo.
 *
 * Использует mediainfo для извлечения метаданных видео/аудио файлов
 * с более детальной информацией, чем ffprobe (например, для некоторых форматов).
 *
 * @package App\Domain\Media\Services
 */
class MediainfoMediaMetadataPlugin implements MediaMetadataPlugin
{
    /**
     * @var string
     */
    private string $binary;

    /**
     * @param string|null $binary Путь к бинарнику mediainfo (по умолчанию 'mediainfo')
     */
    public function __construct(?string $binary = null)
    {
        $this->binary = $binary !== null && $binary !== '' ? $binary : 'mediainfo';
    }

    /**
     * Проверить, поддерживает ли плагин указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если плагин может обработать файл
     */
    public function supports(string $mime): bool
    {
        return str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/');
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
            '%s --Output=JSON %s',
            escapeshellcmd($this->binary),
            escapeshellarg($path)
        );

        $output = $this->runCommand($command);

        if ($output === null) {
            return [];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($output, true);
        if (! is_array($data)) {
            return [];
        }

        return $this->normalize($data);
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
     * Нормализовать структуру mediainfo JSON в плоский массив значений.
     *
     * @param array<string, mixed> $data Данные mediainfo
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

        if (! isset($data['media']) || ! is_array($data['media'])) {
            return [];
        }

        foreach ($data['media'] as $track) {
            if (! is_array($track) || ! isset($track['@type'])) {
                continue;
            }

            $type = $track['@type'] ?? null;

            if ($type === 'General') {
                $durationMs = $this->parseDuration($track['Duration'] ?? null);
                $bitrateKbps = $this->parseBitrate($track['OverallBitRate'] ?? null);
            } elseif ($type === 'Video') {
                $videoCodec = $this->parseString($track['Format'] ?? null);
                $frameRate = $this->parseFrameRate($track['FrameRate'] ?? null);
                $frameCount = $this->parseInt($track['FrameCount'] ?? null);
            } elseif ($type === 'Audio') {
                $audioCodec = $this->parseString($track['Format'] ?? null);
            }
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
     * Распарсить длительность из строки вида "123.456" или "2mn 3s".
     *
     * @param mixed $value Значение длительности
     * @return int|null Длительность в миллисекундах
     */
    private function parseDuration(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Пытаемся распарсить как число (секунды)
        if (is_numeric($value)) {
            return (int) round(((float) $value) * 1000);
        }

        // Пытаемся распарсить формат "Xmn Ys" или "Xh Ymn Zs"
        if (preg_match('/(?:(\d+)h\s*)?(?:(\d+)mn\s*)?(?:(\d+)s)?/', $value, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            return ($hours * 3600 + $minutes * 60 + $seconds) * 1000;
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

        // Пытаемся извлечь число из строки вида "1234 kbps" или "1234567 bps"
        if (preg_match('/([\d.]+)\s*(?:kbps|Kbps|KBPS)?/i', $value, $matches)) {
            $rate = (float) $matches[1];
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

