<?php

namespace App\Domain\Media\Services;

use Illuminate\Http\UploadedFile;

class MediaMetadataExtractor
{
    /**
     * @return array{width: ?int, height: ?int, duration_ms: ?int, exif: ?array}
     */
    public function extract(UploadedFile $file, ?string $mime = null): array
    {
        $mime ??= $file->getMimeType() ?? $file->getClientMimeType();

        $width = null;
        $height = null;
        $duration = null;
        $exif = null;

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            $imageInfo = @getimagesize($file->getRealPath() ?: $file->getPathname() ?: '');

            if (is_array($imageInfo)) {
                $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : null;
                $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : null;
            }

            if ($this->canReadExif($mime)) {
                $exif = $this->readExif($file);
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration_ms' => $duration,
            'exif' => $exif,
        ];
    }

    private function canReadExif(string $mime): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        return in_array($mime, ['image/jpeg', 'image/tiff'], true);
    }

    private function readExif(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data)) {
            return null;
        }

        $normalized = [];
        foreach ($data as $section => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $normalized[$section][$key] = $value;
                }
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}


