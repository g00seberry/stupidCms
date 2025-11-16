<?php

declare(strict_types=1);

namespace App\Domain\Media\Images;

/**
 * Контракт абстракции обработки изображений.
 *
 * Позволяет подменять драйвер (gd/imagick/glide/external),
 * поддерживая новые форматы (HEIC/AVIF) и управление качеством.
 */
interface ImageProcessor
{
    /**
     * Открыть изображение из бинарных данных.
     *
     * @param string $contents Бинарные данные изображения
     * @return ImageRef Оpaque-хэндл изображения
     * @throws \RuntimeException Если данные не поддерживаются драйвером
     */
    public function open(string $contents): ImageRef;

    /**
     * Получить ширину изображения.
     *
     * @param ImageRef $image Изображение
     * @return int Ширина в пикселях
     */
    public function width(ImageRef $image): int;

    /**
     * Получить высоту изображения.
     *
     * @param ImageRef $image Изображение
     * @return int Высота в пикселях
     */
    public function height(ImageRef $image): int;

    /**
     * Масштабировать изображение до указанных размеров.
     *
     * Должно сохранять альфа-канал, где применимо.
     *
     * @param ImageRef $image Исходное изображение
     * @param int $targetWidth Целевая ширина
     * @param int $targetHeight Целевая высота
     * @return ImageRef Новое изображение нужного размера
     */
    public function resize(ImageRef $image, int $targetWidth, int $targetHeight): ImageRef;

    /**
     * Кодировать изображение в требуемый формат.
     *
     * @param ImageRef $image Изображение
     * @param string $preferredExtension Желаемое расширение (например, jpg/png/webp/avif)
     * @param int $quality Качество (0-100), семантика определяется драйвером
     * @return array{data: string, extension: string, mime: string}
     */
    public function encode(ImageRef $image, string $preferredExtension, int $quality = 82): array;

    /**
     * Освободить ресурсы, связанные с изображением.
     *
     * @param ImageRef $image Изображение
     * @return void
     */
    public function destroy(ImageRef $image): void;

    /**
     * Поддерживается ли указанный формат (по расширению).
     *
     * @param string $extension Расширение файла без точки
     * @return bool
     */
    public function supports(string $extension): bool;
}

/**
 * Opaque-хэндл изображения для разных бэкендов.
 *
 * Нельзя полагаться на конкретный тип $native вне драйвера.
 *
 * @template TNative
 */
final class ImageRef
{
    /**
     * @param mixed $native Нативный объект/ресурс бэкенда
     */
    public function __construct(
        public readonly mixed $native
    ) {
    }
}


