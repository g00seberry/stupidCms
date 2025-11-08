<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\Concerns\ManagesEntryTerms;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\AttachTermsRequest;
use App\Http\Requests\Admin\SyncTermsRequest;
use App\Models\Entry;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntryTermsController extends Controller
{
    use Problems;
    use ManagesEntryTerms;

    public function index(int $entry): JsonResponse
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            return $this->entryNotFound($entry);
        }

        return $this->entryTermsResponse($entryModel);
    }

    public function attach(AttachTermsRequest $request, int $entry): JsonResponse
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            return $this->entryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        $terms = Term::query()
            ->with('taxonomy')
            ->whereIn('id', $termIds)
            ->get();

        $this->ensureTermsAllowedForEntry($entryModel, $terms);

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->syncWithoutDetaching($termIds);
        });

        Log::info('Admin entry terms attached', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResponse($entryModel->fresh());
    }

    public function detach(AttachTermsRequest $request, int $entry): JsonResponse
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            return $this->entryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->detach($termIds);
        });

        Log::info('Admin entry terms detached', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResponse($entryModel->fresh());
    }

    public function sync(SyncTermsRequest $request, int $entry): JsonResponse
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            return $this->entryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        $terms = Term::query()
            ->with('taxonomy')
            ->whereIn('id', $termIds)
            ->get();

        if ($terms->isNotEmpty()) {
            $this->ensureTermsAllowedForEntry($entryModel, $terms);
        }

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->sync($termIds);
        });

        Log::info('Admin entry terms synced', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResponse($entryModel->fresh());
    }

    private function entryTermsResponse(Entry $entry, int $status = 200): JsonResponse
    {
        $payload = $this->buildEntryTermsPayload($entry);

        return response()->json(['data' => $payload], $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
    }

    private function findEntry(int $entryId): ?Entry
    {
        return Entry::query()
            ->with(['terms.taxonomy', 'postType'])
            ->where('id', $entryId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function entryNotFound(int $entryId): JsonResponse
    {
        return $this->problem(
            status: 404,
            title: 'Entry not found',
            detail: "Entry with ID {$entryId} does not exist.",
            ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
            headers: [
                'Cache-Control' => 'no-store, private',
                'Vary' => 'Cookie',
            ]
        );
    }
}


