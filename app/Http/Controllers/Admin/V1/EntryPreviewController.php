<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Контроллер для предпросмотра Entry в админ-панели.
 *
 * Позволяет просматривать Entry, включая черновики (draft) и неопубликованные записи.
 * Требует авторизации и права view на Entry.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class EntryPreviewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Предпросмотр Entry.
     *
     * Возвращает данные Entry в формате, аналогичном EntryPageController,
     * но без проверки статуса публикации. Позволяет просматривать черновики.
     *
     * @group Admin ▸ Entries
     * @name Preview entry
     * @authenticated
     * @urlParam entry int required ID записи. Example: 42
     * @response status=200 {
     *   "entry": {
     *     "id": 42,
     *     "title": "Draft article",
     *     "status": "draft",
     *     "published_at": null,
     *     "data_json": {},
     *     "template_override": null,
     *     "created_at": "2025-02-10T08:00:00+00:00",
     *     "updated_at": "2025-02-10T08:05:00+00:00"
     *   },
     *   "post_type": {
     *     "id": 1,
     *     "name": "article",
     *     "template": "article"
     *   },
     *   "blueprint": {
     *     "id": 1,
     *     "name": "article-blueprint"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function show(int $entry): JsonResponse
    {
        // Загружаем Entry с связанными данными (включая удалённые)
        $entryModel = Entry::query()
            ->with(['postType.blueprint', 'author'])
            ->withTrashed()
            ->find($entry);

        if (! $entryModel) {
            abort(404, 'Entry not found');
        }

        // Проверяем авторизацию
        $this->authorize('view', $entryModel);

        Log::info('Admin entry preview accessed', [
            'entry_id' => $entryModel->id,
            'status' => $entryModel->status,
        ]);

        // Формируем ответ (аналогично EntryPageController, но без проверки published)
        return response()->json([
            'entry' => [
                'id' => $entryModel->id,
                'title' => $entryModel->title,
                'status' => $entryModel->status,
                'published_at' => $entryModel->published_at?->toIso8601String(),
                'data_json' => $entryModel->data_json,
                'template_override' => $entryModel->template_override,
                'created_at' => $entryModel->created_at->toIso8601String(),
                'updated_at' => $entryModel->updated_at->toIso8601String(),
            ],
            'post_type' => $entryModel->postType ? [
                'id' => $entryModel->postType->id,
                'name' => $entryModel->postType->name,
                'template' => $entryModel->postType->template,
            ] : null,
            'blueprint' => $entryModel->postType?->blueprint ? [
                'id' => $entryModel->postType->blueprint->id,
                'name' => $entryModel->postType->blueprint->name,
            ] : null,
        ]);
    }
}

