<?php

declare(strict_types=1);

namespace App\Domain\Search\Jobs;

use App\Domain\Search\IndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class ReindexSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public readonly string $trackingId;

    public function __construct(?string $trackingId = null)
    {
        $this->trackingId = $trackingId ?? (string) Str::ulid();
    }

    public function handle(IndexManager $indexManager): void
    {
        $indexManager->reindex();
    }
}


