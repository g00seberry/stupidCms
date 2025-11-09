<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Models\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class MediaPreviewController extends Controller
{
    use Problems;
    use AuthorizesRequests;

    public function __construct(
        private readonly OnDemandVariantService $variantService
    ) {
    }

    public function preview(Request $request, string $mediaId): RedirectResponse
    {
        $variant = $request->query('variant', 'thumbnail');

        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            throw new HttpResponseException(
                $this->problem(
                    404,
                    'Media not found',
                    "Media with ID {$mediaId} does not exist.",
                    ['type' => 'https://stupidcms.dev/problems/not-found']
                )
            );
        }

        $this->authorize('view', $media);

        try {
            $variantModel = $this->variantService->ensureVariant($media, $variant);
        } catch (InvalidArgumentException $exception) {
            throw new HttpResponseException(
                $this->problem(
                    422,
                    'Invalid variant',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/validation-error']
                )
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new HttpResponseException(
                $this->internalError('Failed to generate media variant.', [
                    'type' => 'https://stupidcms.dev/problems/media-variant-error',
                ])
            );
        }

        $url = $this->temporaryUrl($media->disk, $variantModel->path);

        return redirect()->away($url);
    }

    public function download(string $mediaId): RedirectResponse
    {
        $media = Media::withTrashed()->find($mediaId);

        if (! $media) {
            throw new HttpResponseException(
                $this->problem(
                    404,
                    'Media not found',
                    "Media with ID {$mediaId} does not exist.",
                    ['type' => 'https://stupidcms.dev/problems/not-found']
                )
            );
        }

        $this->authorize('view', $media);

        try {
            $url = $this->temporaryUrl($media->disk, $media->path);
        } catch (Throwable $exception) {
            report($exception);

            throw new HttpResponseException(
                $this->internalError('Failed to generate download URL.', [
                    'type' => 'https://stupidcms.dev/problems/media-download-error',
                ])
            );
        }

        return redirect()->away($url);
    }

    private function temporaryUrl(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);
        $expiry = now('UTC')->addSeconds((int) config('media.signed_ttl', 300));

        try {
            return $disk->temporaryUrl($path, $expiry);
        } catch (Throwable) {
            $url = $disk->url($path);

            if (! $url) {
                throw new InvalidArgumentException('Unable to generate media URL.');
            }

            return $url;
        }
    }
}


