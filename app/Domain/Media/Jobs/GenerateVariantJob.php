<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateVariantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $mediaId,
        private readonly string $variant
    ) {
    }

    public function handle(OnDemandVariantService $service): void
    {
        $media = Media::withTrashed()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $service->generateVariant($media, $this->variant);
    }
}


