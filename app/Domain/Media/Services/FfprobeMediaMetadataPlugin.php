<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use RuntimeException;

/**
 * Плагин метаданных, основанный на утилите ffprobe.
 *
 * Использует ffprobe для извлечения длительности, битрейта и фреймов
 * для аудио/видео файлов и возвращает нормализованный набор полей.
 *
 * @package App\Domain\Media\Services
 */
class FfprobeMediaMetadataPlugin implements MediaMetadataPlugin
{
    /**
     * @var string
     */
    private string $binary;

    /**
     * @param string|null $binary Путь к бинарнику ffprobe (по умолчанию 'ffprobe')
     */
    public function __construct(?string $binary = null)
    {
        $this->binary = $binary !== null && $binary !== '' ? $binary : 'ffprobe';
    }

    public function supports(string $mime): bool
    {
        return str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/');
    }

    public function extract(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        $command = sprintf(
            '%s -v quiet -print_format json -show_streams -show_format %s',
            escapeshellcmd($this->binary),
            escapeshellarg($path),
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
     * Вынесено в отдельный метод для удобства мокирования в тестах.
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
            // Логирование stderr может быть добавлено позже при необходимости.
            return null;
        }

        return $stdout;
    }

    /**
     * Нормализовать структуру ffprobe JSON в плоский массив значений.
     *
     * @param array<string, mixed> $data Данные ffprobe (format + streams)
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

        if (isset($data['format']) && is_array($data['format'])) {
            $format = $data['format'];

            if (isset($format['duration']) && is_numeric($format['duration'])) {
                $durationMs = (int) round(((float) $format['duration']) * 1000);
            }

            if (isset($format['bit_rate']) && is_numeric($format['bit_rate'])) {
                $bitrateKbps = (int) round(((float) $format['bit_rate']) / 1000);
            }
        }

        if (isset($data['streams']) && is_array($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if (! is_array($stream)) {
                    continue;
                }

                $codecType = isset($stream['codec_type']) && is_string($stream['codec_type'])
                    ? $stream['codec_type']
                    : null;

                if ($codecType === 'video') {
                    if (isset($stream['codec_name']) && is_string($stream['codec_name'])) {
                        $videoCodec = $stream['codec_name'];
                    }

                    if (isset($stream['nb_frames']) && is_numeric($stream['nb_frames'])) {
                        $frameCount = (int) $stream['nb_frames'];
                    }

                    $frameRate = $this->parseFrameRate($stream['avg_frame_rate'] ?? null, $frameRate);
                } elseif ($codecType === 'audio') {
                    if (isset($stream['codec_name']) && is_string($stream['codec_name'])) {
                        $audioCodec = $stream['codec_name'];
                    }
                }
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
     * Распарсить строку частоты кадров вида "30000/1001" или "25".
     *
     * @param mixed $value Значение avg_frame_rate
     * @param float|null $fallback Текущее значение (для выбора первого валидного)
     * @return float|null
     */
    private function parseFrameRate(mixed $value, ?float $fallback): ?float
    {
        if (! is_string($value) || $value === '' || $value === '0/0') {
            return $fallback;
        }

        if (str_contains($value, '/')) {
            [$num, $den] = explode('/', $value, 2);

            if (is_numeric($num) && is_numeric($den) && (float) $den !== 0.0) {
                return (float) $num / (float) $den;
            }

            return $fallback;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $fallback;
    }
}


