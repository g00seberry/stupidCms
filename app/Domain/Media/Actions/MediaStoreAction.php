<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStoreAction
{
    public function __construct(
        private readonly MediaMetadataExtractor $metadataExtractor
    ) {
    }

    public function execute(UploadedFile $file, array $payload = []): Media
    {
        $diskName = config('media.disk', 'media');
        $disk = Storage::disk($diskName);

        $mime = $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';
        $sizeBytes = (int) ($file->getSize() ?? 0);
        $originalName = $file->getClientOriginalName() ?: $file->getFilename();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: $file->extension() ?: 'bin');
        $checksum = $this->checksum($file);

        $path = $this->storeFile($disk, $file, $extension, $checksum);

        $metadata = $this->metadataExtractor->extract($file, $mime);

        return Media::create([
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $originalName,
            'ext' => $extension,
            'mime' => $mime,
            'size_bytes' => $sizeBytes > 0 ? $sizeBytes : $disk->size($path),
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'duration_ms' => $metadata['duration_ms'],
            'checksum_sha256' => $checksum,
            'exif_json' => $metadata['exif'],
            'title' => $payload['title'] ?? null,
            'alt' => $payload['alt'] ?? null,
            'collection' => $payload['collection'] ?? null,
        ]);
    }

    private function storeFile(Filesystem $disk, UploadedFile $file, string $extension, ?string $checksum): string
    {
        $strategy = config('media.path_strategy', 'by-date');
        $baseName = strtolower((string) Str::ulid());
        $directory = match ($strategy) {
            'hash-shard' => $this->hashShardDirectory($checksum),
            default => now('UTC')->format('Y/m/d'),
        };

        $filename = $extension !== '' ? "{$baseName}.{$extension}" : $baseName;
        $path = trim($directory, '/');
        $fullPath = $path === '' ? $filename : "{$path}/{$filename}";

        $targetDirectory = $path === '' ? '' : $path;

        $storedPath = $disk->putFileAs($targetDirectory, $file, $filename);

        if (! $storedPath) {
            throw new RuntimeException('Failed to store uploaded media file.');
        }

        return str_replace('\\', '/', ltrim($storedPath, '/'));
    }

    private function checksum(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (! $realPath || ! is_file($realPath)) {
            return null;
        }

        return hash_file('sha256', $realPath);
    }

    private function hashShardDirectory(?string $checksum): string
    {
        if ($checksum === null || strlen($checksum) < 4) {
            return now('UTC')->format('Y/m/d');
        }

        return substr($checksum, 0, 2).'/'.substr($checksum, 2, 2);
    }
}


