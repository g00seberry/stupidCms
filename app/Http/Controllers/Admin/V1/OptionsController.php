<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Options\OptionsRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Options\IndexOptionsRequest;
use App\Http\Requests\Admin\Options\PutOptionRequest;
use App\Http\Resources\Admin\OptionCollection;
use App\Http\Resources\Admin\OptionResource;
use App\Models\Option;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер для управления системными опциями в админ-панели.
 *
 * Предоставляет CRUD операции для опций: создание, чтение, обновление, удаление, восстановление.
 * Опции организованы по namespace и используются для хранения настроек системы.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class OptionsController extends Controller
{
    use AuthorizesRequests;
    use ThrowsErrors;

    /**
     * Паттерн для валидации ключей опций.
     *
     * @var string
     */
    private const KEY_PATTERN = '/^[a-z0-9_][a-z0-9_.-]{1,63}$/';

    /**
     * @param \App\Domain\Options\OptionsRepository $repository Репозиторий опций
     */
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
     *       "created_at": "2025-01-10T12:00:00+00:00",
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a01",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a01-eed543b8b7cb6c30-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/invalid-option-identifier",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "INVALID_OPTION_IDENTIFIER",
     *   "detail": "The provided option namespace/key is invalid.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a02",
     *     "errors": {
     *       "namespace": [
     *         "The namespace format is invalid."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a02-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a03",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a03-eed543b8b7cb6c30-01"
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
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a04",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a04-eed543b8b7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Option \"site/hero.title\" was not found.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a05",
     *     "namespace": "site",
     *     "key": "hero.title"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a05-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a06",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a06-eed543b8b7cb6c30-01"
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
            $this->throwOptionNotFound($namespace, $key);
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
     *     "id": 10,
     *     "namespace": "site",
     *     "key": "hero.title",
     *     "value": {
     *       "title": "Launch"
     *     },
     *     "description": "Hero headline",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a07",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a07-eed543b8b7cb6c30-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/invalid-option-payload",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "INVALID_OPTION_PAYLOAD",
     *   "detail": "Invalid option payload.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a08",
     *     "errors": {
     *       "value": [
     *         "The value must be valid JSON."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a08-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a09",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a09-eed543b8b7cb6c30-01"
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0a",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0a-eed543b8b7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Option \"site/hero.title\" was not found.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0b",
     *     "namespace": "site",
     *     "key": "hero.title"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0b-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0c",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0c-eed543b8b7cb6c30-01"
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
            $this->throwOptionNotFound($namespace, $key);
        }

        $this->authorize('delete', $option);

        $deleted = $this->repository->delete($namespace, $key);

        if (! $deleted) {
            $this->throwOptionNotFound($namespace, $key);
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
     *     "id": 10,
     *     "namespace": "site",
     *     "key": "hero.title",
     *     "value": "Launch the future",
     *     "description": "Hero headline",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0d",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0d-eed543b8b7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Option not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Option \"site/hero.title\" was not found.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0e",
     *     "namespace": "site",
     *     "key": "hero.title"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0e-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e0a0a0a0f",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e0a0a0a0f-eed543b8b7cb6c30-01"
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
            $this->throwOptionNotFound($namespace, $key);
        }

        $this->authorize('restore', $option);

        $restored = $option->trashed()
            ? $this->repository->restore($namespace, $key)
            : $option;

        if (! $restored) {
            $this->throwOptionNotFound($namespace, $key);
        }

        return new OptionResource($restored);
    }

    /**
     * Выбросить ошибку "опция не найдена".
     *
     * @param string $namespace Namespace опции
     * @param string $key Ключ опции
     * @return never
     */
    private function throwOptionNotFound(string $namespace, string $key): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Option "%s/%s" was not found.', $namespace, $key),
            [
                'namespace' => $namespace,
                'key' => $key,
            ],
        );
    }

    /**
     * Проверить валидность параметров маршрута (namespace и key).
     *
     * @param string $namespace Namespace опции
     * @param string|null $key Ключ опции (опционально)
     * @return void
     * @throws \App\Support\Errors\HttpErrorException Если параметры невалидны
     */
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

        $this->throwError(
            ErrorCode::INVALID_OPTION_IDENTIFIER,
            'The provided option namespace/key is invalid.',
            ['errors' => $validator->errors()->toArray()],
        );
    }
}

