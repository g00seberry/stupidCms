<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Options\OptionsRepository;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\Options\IndexOptionsRequest;
use App\Http\Requests\Admin\Options\PutOptionRequest;
use App\Http\Resources\Admin\OptionCollection;
use App\Http\Resources\Admin\OptionResource;
use App\Models\Option;
use App\Support\Http\AdminResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class OptionsController extends Controller
{
    use AuthorizesRequests;
    use Problems;

    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    public function __construct(private readonly OptionsRepository $repository)
    {
    }

    /**
     * Список опций по namespace.
     *
     * @group Admin ▸ Options
     * @name List options
     * @authenticated
     * @urlParam namespace string required Пространство опций (a-z0-9_.-). Example: site
     * @queryParam q string Поиск по ключу/описанию (<=255). Example: hero
     * @queryParam deleted string Управление soft-deleted. Values: with,only.
     * @queryParam per_page int Размер страницы (1-100). Default: 20.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 10,
     *       "namespace": "site",
     *       "key": "hero.title",
     *       "value": "Launch the future",
     *       "description": "Hero headline",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null
     *     }
     *   ],
     *   "links": {
     *     "first": "…",
     *     "last": "…",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 20,
     *     "total": 1
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/invalid-option-identifier",
     *   "title": "Validation error",
     *   "status": 422,
     *   "detail": "The provided option namespace/key is invalid.",
     *   "errors": {
     *     "namespace": [
     *       "The namespace format is invalid."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function index(IndexOptionsRequest $request, string $namespace): OptionCollection
    {
        $this->assertValidRouteParameters($namespace);

        $validated = $request->validated();

        $query = Option::query()->where('namespace', $namespace);

        $deleted = $validated['deleted'] ?? null;
        if ($deleted === 'with') {
            $query->withTrashed();
        } elseif ($deleted === 'only') {
            $query->onlyTrashed();
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($inner) use ($search) {
                $inner->where('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $options = $query
            ->orderBy('key')
            ->paginate($perPage);

        return new OptionCollection($options);
    }

    /**
     * Получение значения опции.
     *
     * @group Admin ▸ Options
     * @name Show option
     * @authenticated
     * @urlParam namespace string required Пространство опций. Example: site
     * @urlParam key string required Ключ опции. Example: hero.title
     * @response status=200 {
     *   "data": {
     *     "id": 10,
     *     "namespace": "site",
     *     "key": "hero.title",
     *     "value": "Launch the future",
     *     "description": "Hero headline",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "detail": "Option \"site/hero.title\" was not found."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(string $namespace, string $key): OptionResource
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('view', $option);

        return new OptionResource($option);
    }

    /**
     * Создание/обновление опции.
     *
     * @group Admin ▸ Options
     * @name Upsert option
     * @authenticated
     * @urlParam namespace string required Пространство. Example: site
     * @urlParam key string required Ключ (a-z0-9_.-). Example: hero.title
     * @bodyParam value mixed required JSON-значение (до 64KB). Example: {"title":"Launch"}
     * @bodyParam description string Описание (<=255). Example: Hero headline
     * @response status=200 {
     *   "data": {
     *     "namespace": "site",
     *     "key": "hero.title",
     *     "value": {
     *       "title": "Launch"
     *     },
     *     "description": "Hero headline"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation error",
     *   "status": 422,
     *   "detail": "Invalid option payload.",
     *   "errors": {
     *     "value": [
     *       "The value must be valid JSON."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function put(PutOptionRequest $request, string $namespace, string $key): OptionResource
    {
        $validated = $request->validated();

        $option = $request->option();
        $this->authorize('write', $option);

        $description = array_key_exists('description', $validated)
            ? $validated['description']
            : $option->description;

        $saved = $this->repository->set(
            $namespace,
            $key,
            $validated['value'],
            $description
        );

        return new OptionResource($saved);
    }

    /**
     * Удаление опции (soft delete).
     *
     * @group Admin ▸ Options
     * @name Delete option
     * @authenticated
     * @urlParam namespace string required Пространство. Example: site
     * @urlParam key string required Ключ. Example: hero.title
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "detail": "Option \"site/hero.title\" was not found."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function destroy(string $namespace, string $key): Response
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('delete', $option);

        $deleted = $this->repository->delete($namespace, $key);

        if (! $deleted) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        return AdminResponse::noContent();
    }

    /**
     * Восстановление удалённой опции.
     *
     * @group Admin ▸ Options
     * @name Restore option
     * @authenticated
     * @urlParam namespace string required Пространство. Example: site
     * @urlParam key string required Ключ. Example: hero.title
     * @response status=200 {
     *   "data": {
     *     "namespace": "site",
     *     "key": "hero.title",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "detail": "Option \"site/hero.title\" was not found."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function restore(string $namespace, string $key): OptionResource
    {
        $this->assertValidRouteParameters($namespace, $key);

        $option = Option::withTrashed()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (! $option) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        $this->authorize('restore', $option);

        $restored = $option->trashed()
            ? $this->repository->restore($namespace, $key)
            : $option;

        if (! $restored) {
            throw new HttpResponseException($this->optionNotFoundProblem($namespace, $key));
        }

        return new OptionResource($restored);
    }

    private function assertValidRouteParameters(string $namespace, ?string $key = null): void
    {
        $data = ['namespace' => $namespace];
        $rules = ['namespace' => ['required', 'string', 'regex:' . self::KEY_PATTERN]];

        if ($key !== null) {
            $data['key'] = $key;
            $rules['key'] = ['required', 'string', 'regex:' . self::KEY_PATTERN];
        }

        $validator = Validator::make($data, $rules);

        if (! $validator->fails()) {
            return;
        }

        throw new HttpResponseException($this->problem(
            422,
            'Validation error',
            'The provided option namespace/key is invalid.',
            [
                'type' => 'https://stupidcms.dev/problems/invalid-option-identifier',
                'code' => 'INVALID_OPTION_IDENTIFIER',
                'errors' => $validator->errors()->toArray(),
            ]
        ));
    }

    private function optionNotFoundProblem(string $namespace, string $key): JsonResponse
    {
        return $this->problem(
            404,
            'Option not found',
            sprintf('Option "%s/%s" was not found.', $namespace, $key),
            [
                'type' => 'https://stupidcms.dev/problems/not-found',
                'code' => 'NOT_FOUND',
            ]
        );
    }
}

