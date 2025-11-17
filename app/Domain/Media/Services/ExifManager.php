<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Images\ImageProcessor;

/**
 * Менеджер для управления EXIF данными изображений.
 *
 * Поддерживает операции:
 * - Автоматический поворот изображения на основе EXIF Orientation
 * - Удаление (strip) EXIF данных
 * - Фильтрация EXIF полей по whitelist
 * - Сохранение цветового профиля (ICC)
 *
 * @package App\Domain\Media\Services
 */
class ExifManager
{
    /**
     * @param \App\Domain\Media\Images\ImageProcessor $imageProcessor Процессор изображений
     */
    public function __construct(
        private readonly ImageProcessor $imageProcessor
    ) {
    }

    /**
     * Автоматически повернуть изображение на основе EXIF Orientation.
     *
     * Примечание: Автоматический поворот должен быть реализован
     * в ImageProcessor при генерации вариантов. Этот метод оставлен
     * для будущей реализации или может быть использован через расширение
     * интерфейса ImageProcessor методами rotate/flip.
     *
     * @param string $imageBytes Байты изображения
     * @param array<string, array<string, mixed>>|null $exif EXIF данные
     * @return string Байты повёрнутого изображения
     */
    public function autoRotate(string $imageBytes, ?array $exif): string
    {
        // Автоматический поворот должен быть реализован в ImageProcessor
        // при генерации вариантов изображений. Здесь просто возвращаем оригинал.
        // TODO: Добавить методы rotate/flip в ImageProcessor интерфейс
        return $imageBytes;
    }

    /**
     * Удалить EXIF данные из изображения.
     *
     * Примечание: Удаление EXIF должно быть реализовано в ImageProcessor
     * при кодировании. Этот метод оставлен для будущей реализации.
     *
     * @param string $imageBytes Байты изображения
     * @param string $mime MIME-тип изображения
     * @return string Байты изображения без EXIF
     */
    public function stripExif(string $imageBytes, string $mime): string
    {
        // Удаление EXIF должно быть реализовано в ImageProcessor при encode
        // TODO: Добавить поддержку опции strip в ImageProcessor::encode
        return $imageBytes;
    }

    /**
     * Отфильтровать EXIF данные по whitelist полей.
     *
     * @param array<string, array<string, mixed>>|null $exif EXIF данные
     * @param array<string> $whitelist Список разрешённых полей (например, ['IFD0.Make', 'IFD0.Model'])
     * @return array<string, array<string, mixed>>|null Отфильтрованные EXIF данные
     */
    public function filterExif(?array $exif, array $whitelist): ?array
    {
        if ($exif === null || empty($whitelist)) {
            return $exif;
        }

        $filtered = [];

        foreach ($whitelist as $field) {
            [$section, $key] = explode('.', $field, 2) + [null, null];

            if ($section === null || $key === null) {
                continue;
            }

            if (isset($exif[$section][$key])) {
                if (! isset($filtered[$section])) {
                    $filtered[$section] = [];
                }
                $filtered[$section][$key] = $exif[$section][$key];
            }
        }

        return $filtered === [] ? null : $filtered;
    }

    /**
     * Сохранить цветовой профиль (ICC) из EXIF данных.
     *
     * @param array<string, array<string, mixed>>|null $exif EXIF данные
     * @return string|null Байты ICC профиля или null, если профиль отсутствует
     */
    public function extractColorProfile(?array $exif): ?string
    {
        if ($exif === null) {
            return null;
        }

        // ICC профиль может быть в разных секциях
        $iccSections = ['ICC_Profile', 'IFD0', 'EXIF'];

        foreach ($iccSections as $section) {
            if (isset($exif[$section])) {
                // Ищем ICC данные
                foreach ($exif[$section] as $key => $value) {
                    if (stripos($key, 'icc') !== false || stripos($key, 'profile') !== false) {
                        if (is_string($value)) {
                            // Может быть base64 или hex
                            if (base64_decode($value, true) !== false) {
                                return base64_decode($value, true) ?: null;
                            }

                            if (ctype_xdigit($value)) {
                                return hex2bin($value) ?: null;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Получить значение Orientation из EXIF данных.
     *
     * @param array<string, array<string, mixed>> $exif EXIF данные
     * @return int|null Значение Orientation (1-8) или null
     */
    private function getOrientation(array $exif): ?int
    {
        // Orientation может быть в разных секциях
        $orientationSections = ['IFD0', 'EXIF', 'Orientation'];

        foreach ($orientationSections as $section) {
            if (isset($exif[$section]['Orientation'])) {
                $value = $exif[$section]['Orientation'];
                if (is_numeric($value)) {
                    $orientation = (int) $value;
                    if ($orientation >= 1 && $orientation <= 8) {
                        return $orientation;
                    }
                }
            }
        }

        return null;
    }

}

