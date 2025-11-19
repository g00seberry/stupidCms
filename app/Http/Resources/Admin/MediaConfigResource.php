<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

/**
 * API Resource для конфигурации системы медиа-файлов.
 *
 * Возвращает информацию о разрешенных типах файлов и вариантах изображений.
 *
 * @package App\Http\Resources\Admin
 */
class MediaConfigResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с конфигурацией медиа
     */
    public function toArray($request): array
    {
        $allowedMimes = config('media.allowed_mimes', []);
        $variants = config('media.variants', []);
        $maxUploadMb = config('media.max_upload_mb', 1024);

        // Формируем список вариантов изображений с их параметрами
        $imageVariants = [];
        foreach ($variants as $name => $config) {
            $imageVariants[$name] = [
                'max' => $config['max'] ?? null,
                'format' => $config['format'] ?? null,
                'quality' => $config['quality'] ?? null,
            ];
        }

        return [
            'allowed_mimes' => $allowedMimes,
            'max_upload_mb' => $maxUploadMb,
            'image_variants' => $imageVariants,
        ];
    }
}

