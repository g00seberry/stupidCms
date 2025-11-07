# Код для ревью: Сервис резервирования путей

## Обзор изменений

Реализован сервис для динамического резервирования URL-путей с полным API для управления резервированиями. Все файлы, изменённые или созданные в рамках задачи 28.

---

## 1. Миграция: создание таблицы route_reservations

**Файл:** `database/migrations/2025_11_07_053847_create_route_reservations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique()->comment('Канонический путь в нижнем регистре');
            $table->string('source', 100)->comment('Источник резервирования (system:name, plugin:name, module:name)');
            $table->string('reason', 255)->nullable()->comment('Необязательное описание причины резервирования');
            $table->timestamps();

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_reservations');
    }
};
```

---

## 2. Модель RouteReservation

**Файл:** `app/Models/RouteReservation.php`

```php
<?php

namespace App\Models;

use App\Domain\Routing\PathNormalizer;
use App\Domain\Routing\Exceptions\InvalidPathException;
use Illuminate\Database\Eloquent\Model;

class RouteReservation extends Model
{
    protected $fillable = [
        'path',
        'source',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Мутатор для автоматической нормализации пути при установке.
     * Защищает от прямого создания модели без нормализации.
     *
     * @throws InvalidPathException
     */
    public function setPathAttribute(string $value): void
    {
        $this->attributes['path'] = PathNormalizer::normalize($value);
    }
}
```

---

## 3. PathNormalizer

**Файл:** `app/Domain/Routing/PathNormalizer.php`

```php
<?php

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\InvalidPathException;

final class PathNormalizer
{
    /**
     * Нормализует путь: trim, убирает query/fragment, гарантирует ведущий /, убирает trailing /, lowercase, NFC.
     *
     * @throws InvalidPathException если путь пустой или невалидный
     */
    public static function normalize(string $raw): string
    {
        // Убираем query и fragment
        $path = parse_url($raw, PHP_URL_PATH) ?? $raw;

        // Trim пробелов
        $path = trim($path);

        // Проверка на пустые/невалидные значения
        if ($path === '' || $path === '#' || $path === '?') {
            throw new InvalidPathException("Invalid path: '{$raw}'");
        }

        // Гарантируем ведущий /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Защита от относительных путей и дублирующих слэшей
        // Убираем ./ и ../ сегменты
        $path = str_replace(['./', '../'], '', $path);
        // Убираем дублирующие слэши (// → /)
        $path = preg_replace('#/+#', '/', $path);

        // Гарантируем ведущий /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Убираем trailing / (кроме корня)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Приводим к нижнему регистру
        $path = mb_strtolower($path, 'UTF-8');

        // Unicode NFC нормализация
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($path, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $path = $normalized;
            }
        }

        return $path;
    }
}
```

---

## 4. Исключения

### InvalidPathException

**Файл:** `app/Domain/Routing/Exceptions/InvalidPathException.php`

```php
<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class InvalidPathException extends Exception
{
    public function __construct(string $message = "Invalid path", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

### PathAlreadyReservedException

**Файл:** `app/Domain/Routing/Exceptions/PathAlreadyReservedException.php`

```php
<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class PathAlreadyReservedException extends Exception
{
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === "") {
            $message = "Path '{$path}' is already reserved by '{$owner}'";
        }
        parent::__construct($message, $code, $previous);
    }
}
```

### ForbiddenReservationRelease

**Файл:** `app/Domain/Routing/Exceptions/ForbiddenReservationRelease.php`

```php
<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class ForbiddenReservationRelease extends Exception
{
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        public readonly string $attemptedSource,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === "") {
            $message = "Cannot release path '{$path}' reserved by '{$owner}' (attempted by '{$attemptedSource}')";
        }
        parent::__construct($message, $code, $previous);
    }
}
```

---

## 5. Интерфейсы и реализации

### PathReservationStore

**Файл:** `app/Domain/Routing/PathReservationStore.php`

```php
<?php

namespace App\Domain\Routing;

use Throwable;

interface PathReservationStore
{
    public function insert(string $path, string $source, ?string $reason): void;
    public function delete(string $path): void;
    /**
     * Удаляет путь только если он принадлежит указанному источнику.
     * Возвращает количество удалённых записей (0 или 1).
     */
    public function deleteIfOwnedBy(string $path, string $source): int;
    public function deleteBySource(string $source): int;
    public function exists(string $path): bool;
    public function ownerOf(string $path): ?string;
    public function isUniqueViolation(Throwable $e): bool;
}
```

### PathReservationStoreImpl

**Файл:** `app/Domain/Routing/PathReservationStoreImpl.php`

```php
<?php

namespace App\Domain\Routing;

use App\Models\RouteReservation;
use Illuminate\Database\QueryException;
use Throwable;

final class PathReservationStoreImpl implements PathReservationStore
{
    public function insert(string $path, string $source, ?string $reason): void
    {
        RouteReservation::create([
            'path' => $path,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    public function delete(string $path): void
    {
        RouteReservation::where('path', $path)->delete();
    }

    public function deleteIfOwnedBy(string $path, string $source): int
    {
        return RouteReservation::where('path', $path)
            ->where('source', $source)
            ->delete();
    }

    public function deleteBySource(string $source): int
    {
        return RouteReservation::where('source', $source)->delete();
    }

    public function exists(string $path): bool
    {
        return RouteReservation::where('path', $path)->exists();
    }

    public function ownerOf(string $path): ?string
    {
        $reservation = RouteReservation::where('path', $path)->first();
        return $reservation?->source;
    }

    public function isUniqueViolation(Throwable $e): bool
    {
        if (!$e instanceof QueryException) {
            return false;
        }

        // SQLite
        if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // MySQL/MariaDB
        if (str_contains($e->getMessage(), 'Duplicate entry') || (string)$e->getCode() === '23000') {
            return true;
        }

        // PostgreSQL
        if (str_contains($e->getMessage(), 'duplicate key value') || (string)$e->getCode() === '23505') {
            return true;
        }

        return false;
    }
}
```

### PathReservationService

**Файл:** `app/Domain/Routing/PathReservationService.php`

```php
<?php

namespace App\Domain\Routing;

interface PathReservationService
{
    public function reservePath(string $path, string $source, ?string $reason = null): void;
    public function releasePath(string $path, string $source): void;
    public function releaseBySource(string $source): int;
    public function isReserved(string $path): bool;
    public function ownerOf(string $path): ?string;
}
```

### PathReservationServiceImpl

**Файл:** `app/Domain/Routing/PathReservationServiceImpl.php`

```php
<?php

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Database\QueryException;

final class PathReservationServiceImpl implements PathReservationService
{
    /**
     * @param array<string> $static Статические пути из конфига, которые нельзя резервировать
     */
    public function __construct(
        private PathReservationStore $store,
        private array $static = []
    ) {}

    public function reservePath(string $path, string $source, ?string $reason = null): void
    {
        $normalized = PathNormalizer::normalize($path);

        // Блок для статического списка — никогда нельзя резервировать
        if (in_array($normalized, $this->static, true)) {
            throw new PathAlreadyReservedException($normalized, 'static:config');
        }

        // Попытка вставки с уникальным индексом
        try {
            $this->store->insert($normalized, $source, $reason);
        } catch (QueryException $e) {
            if ($this->store->isUniqueViolation($e)) {
                $owner = $this->store->ownerOf($normalized) ?? 'unknown';
                throw new PathAlreadyReservedException($normalized, $owner);
            }
            throw $e;
        }
    }

    public function releasePath(string $path, string $source): void
    {
        $normalized = PathNormalizer::normalize($path);

        // Атомарная операция: удаляем только если владелец совпадает
        $deleted = $this->store->deleteIfOwnedBy($normalized, $source);

        if ($deleted === 0) {
            // Путь не найден или принадлежит другому источнику
            $owner = $this->store->ownerOf($normalized);
            if ($owner && $owner !== $source) {
                throw new ForbiddenReservationRelease($normalized, $owner, $source);
            }
            // Если owner === null, путь не существует - это идемпотентная операция, просто выходим
        }
    }

    public function releaseBySource(string $source): int
    {
        return $this->store->deleteBySource($source);
    }

    public function isReserved(string $path): bool
    {
        $normalized = PathNormalizer::normalize($path);
        return in_array($normalized, $this->static, true) || $this->store->exists($normalized);
    }

    public function ownerOf(string $path): ?string
    {
        $normalized = PathNormalizer::normalize($path);

        // Проверяем статические пути
        if (in_array($normalized, $this->static, true)) {
            return 'static:config';
        }

        return $this->store->ownerOf($normalized);
    }
}
```

---

## 6. Service Provider

**Файл:** `app/Providers/PathReservationServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Domain\Routing\PathNormalizer;
use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\PathReservationServiceImpl;
use App\Domain\Routing\PathReservationStore;
use App\Domain\Routing\PathReservationStoreImpl;
use Illuminate\Support\ServiceProvider;

class PathReservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PathReservationStore::class, PathReservationStoreImpl::class);

        $this->app->singleton(PathReservationService::class, function ($app) {
            $config = config('stupidcms.reserved_routes', []);
            $staticPaths = [];

            /**
             * Контракт: PathReservationService работает только с путями из конфига (kind='path').
             * Таблица reserved_routes (из задачи 23) используется для fallback-роутера и валидации слугов.
             * PathReservationService предназначен для динамических/временных резервирований плагинов.
             *
             * При проверке слугов (Entry) используется ReservedRouteRegistry (задача 23),
             * который объединяет конфиг и БД reserved_routes.
             */
            if (isset($config['paths'])) {
                foreach ($config['paths'] as $path) {
                    try {
                        $staticPaths[] = PathNormalizer::normalize($path);
                    } catch (\App\Domain\Routing\Exceptions\InvalidPathException $e) {
                        // Пропускаем невалидные пути из конфига (логируем, но не падаем)
                        \Log::warning("Invalid static path in config: {$path}", ['exception' => $e]);
                    }
                }
            }

            return new PathReservationServiceImpl(
                $app->make(PathReservationStore::class),
                $staticPaths
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
```

---

## 7. CLI команды

### RoutesReserveCommand

**Файл:** `app/Console/Commands/RoutesReserveCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Console\Command;

class RoutesReserveCommand extends Command
{
    protected $signature = 'routes:reserve {path : The path to reserve} {source : The source identifier (e.g., system:feeds, plugin:shop)} {reason? : Optional reason for reservation}';
    protected $description = 'Reserve a URL path to prevent content/routes from using it';

    public function handle(PathReservationService $service): int
    {
        $path = $this->argument('path');
        $source = $this->argument('source');
        $reason = $this->argument('reason');

        try {
            $service->reservePath($path, $source, $reason);
            $this->info("Path '{$path}' has been reserved by '{$source}'.");
            if ($reason) {
                $this->line("Reason: {$reason}");
            }
            return Command::SUCCESS;
        } catch (InvalidPathException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } catch (PathAlreadyReservedException $e) {
            $this->error("Path '{$e->path}' is already reserved by '{$e->owner}'.");
            return Command::FAILURE;
        }
    }
}
```

### RoutesReleaseCommand

**Файл:** `app/Console/Commands/RoutesReleaseCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use Illuminate\Console\Command;

class RoutesReleaseCommand extends Command
{
    protected $signature = 'routes:release {path : The path to release} {source : The source identifier that owns the reservation}';
    protected $description = 'Release a reserved URL path';

    public function handle(PathReservationService $service): int
    {
        $path = $this->argument('path');
        $source = $this->argument('source');

        try {
            $service->releasePath($path, $source);
            $this->info("Path '{$path}' has been released.");
            return Command::SUCCESS;
        } catch (ForbiddenReservationRelease $e) {
            $this->error("Cannot release path '{$e->path}': it is reserved by '{$e->owner}', not '{$e->attemptedSource}'.");
            return Command::FAILURE;
        }
    }
}
```

### RoutesListReservationsCommand

**Файл:** `app/Console/Commands/RoutesListReservationsCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\RouteReservation;
use Illuminate\Console\Command;

class RoutesListReservationsCommand extends Command
{
    protected $signature = 'routes:list-reservations';
    protected $description = 'List all reserved paths';

    public function handle(): int
    {
        $reservations = RouteReservation::orderBy('path')->get();

        if ($reservations->isEmpty()) {
            $this->info('No path reservations found.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Path', 'Source', 'Reason', 'Created At'],
            $reservations->map(fn($r) => [
                $r->path,
                $r->source,
                $r->reason ?? '-',
                $r->created_at->format('Y-m-d H:i:s'),
            ])->toArray()
        );

        $this->info("Total: {$reservations->count()} reservation(s).");

        return Command::SUCCESS;
    }
}
```

---

## 8. FormRequest классы

### StorePathReservationRequest

**Файл:** `app/Http/Requests/StorePathReservationRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePathReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string|max:255',
            'source' => 'required|string|max:100',
            'reason' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'The path field is required.',
            'path.max' => 'The path may not be greater than 255 characters.',
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}
```

### DestroyPathReservationRequest

**Файл:** `app/Http/Requests/DestroyPathReservationRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyPathReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => 'required|string|max:100',
            'path' => 'nullable|string|max:255', // Опционально из body, если не в URL
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'path.max' => 'The path may not be greater than 255 characters.',
        ];
    }

    /**
     * Get the path from either route parameter or request body.
     */
    public function getPath(): string
    {
        return $this->route('path') ?? $this->input('path', '');
    }
}
```

---

## 9. HTTP API контроллер

**Файл:** `app/Http/Controllers/Admin/PathReservationController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyPathReservationRequest;
use App\Http\Requests\StorePathReservationRequest;
use App\Models\Audit;
use App\Models\RouteReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PathReservationController extends Controller
{
    public function __construct(
        private PathReservationService $service
    ) {}

    /**
     * POST /api/v1/admin/reservations
     */
    public function store(StorePathReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->service->reservePath(
                $validated['path'],
                $validated['source'],
                $validated['reason'] ?? null
            );

            // Аудит: логируем резервирование
            $this->logAudit('reserve', $validated['path'], [
                'source' => $validated['source'],
                'reason' => $validated['reason'] ?? null,
            ]);

            return response()->json([
                'message' => 'Path reserved successfully',
            ], Response::HTTP_CREATED);
        } catch (InvalidPathException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PathAlreadyReservedException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_CONFLICT,
                [
                    'path' => $e->path,
                    'owner' => $e->owner,
                ]
            );
        }
    }

    /**
     * DELETE /api/v1/admin/reservations/{path}
     *
     * Поддерживает path как в URL параметре, так и в JSON body (для экзотических URL-encode кейсов).
     */
    public function destroy(string $path, DestroyPathReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Если path не в URL (пустой или невалидный), берём из body
        $actualPath = $request->getPath();
        if (empty($actualPath)) {
            return $this->errorResponse(
                'Path is required either in URL parameter or request body.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $this->service->releasePath($actualPath, $validated['source']);

            // Аудит: логируем освобождение
            $this->logAudit('release', $actualPath, [
                'source' => $validated['source'],
            ]);

            return response()->json([
                'message' => 'Path released successfully',
            ], Response::HTTP_OK);
        } catch (ForbiddenReservationRelease $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_FORBIDDEN,
                [
                    'path' => $e->path,
                    'owner' => $e->owner,
                    'attempted_source' => $e->attemptedSource,
                ]
            );
        }
    }

    /**
     * GET /api/v1/admin/reservations
     */
    public function index(): JsonResponse
    {
        $reservations = RouteReservation::orderBy('path')->get();

        return response()->json([
            'data' => $reservations->map(fn($r) => [
                'path' => $r->path,
                'source' => $r->source,
                'reason' => $r->reason,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Форматирует ошибку в формате RFC 7807
     */
    private function errorResponse(
        string $detail,
        int $status,
        array $extensions = []
    ): JsonResponse {
        $response = [
            'type' => 'about:blank',
            'title' => match($status) {
                409 => 'Conflict',
                422 => 'Unprocessable Entity',
                403 => 'Forbidden',
                default => 'Error',
            },
            'status' => $status,
            'detail' => $detail,
        ];

        if (!empty($extensions)) {
            $response = array_merge($response, $extensions);
        }

        return response()->json($response, $status);
    }

    /**
     * Логирует действие в таблицу audits.
     */
    private function logAudit(string $action, string $path, array $context = []): void
    {
        try {
            // Находим резервирование для получения ID
            $reservation = RouteReservation::where('path', $path)->first();

            Audit::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => RouteReservation::class,
                'subject_id' => $reservation?->id ?? 0, // 0 если не найдено (для release несуществующего)
                'diff_json' => [
                    'path' => $path,
                    ...$context,
                ],
                'ip' => request()->ip(),
                'ua' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Не падаем, если аудит не записался
            Log::warning('Failed to log path reservation audit', [
                'action' => $action,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

---

## 10. Политика авторизации

**Файл:** `app/Policies/RouteReservationPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\RouteReservation;
use App\Models\User;

class RouteReservationPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, RouteReservation $routeReservation): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, RouteReservation $routeReservation): bool { return false; }
    public function delete(User $user, RouteReservation $routeReservation): bool { return false; }

    /**
     * Determine whether the user can delete any model (for collection operations).
     */
    public function deleteAny(User $user): bool { return false; }
}
```

---

## 11. Маршруты

**Файл:** `routes/api_admin.php` (см. задачу 29)

Админские API маршруты вынесены в отдельный файл `routes/api_admin.php` с middleware('api') для stateless работы. Подробности см. в документации задачи 29.

---

## 12. Тесты

**Файл:** `tests/Feature/PathReservationServiceTest.php`

Основные тесты:

-   ✅ Успешное резервирование пути
-   ✅ Повторное резервирование → `PathAlreadyReservedException` (критерий приёмки)
-   ✅ Нормализация путей (case-insensitive)
-   ✅ Блокировка статических путей из конфига
-   ✅ Освобождение пути
-   ✅ Освобождение чужого пути → `ForbiddenReservationRelease`
-   ✅ Освобождение по источнику
-   ✅ Проверка `isReserved()` и `ownerOf()`
-   ✅ Обработка невалидных путей

**Результаты:**

-   PathReservationServiceTest: 22 passed, 30 assertions
-   PathReservationApiTest: 13 passed, 44 assertions
-   Итого: 35 passed, 73 assertions

---

## Резюме изменений

### Новые файлы:

1. `database/migrations/2025_11_07_053847_create_route_reservations_table.php` - миграция
2. `app/Models/RouteReservation.php` - модель
3. `app/Domain/Routing/PathNormalizer.php` - нормализация путей
4. `app/Domain/Routing/Exceptions/` - 3 исключения
5. `app/Domain/Routing/PathReservationStore.php` и `PathReservationStoreImpl.php` - хранилище
6. `app/Domain/Routing/PathReservationService.php` и `PathReservationServiceImpl.php` - сервис
7. `app/Providers/PathReservationServiceProvider.php` - service provider
8. `app/Console/Commands/` - 3 CLI команды
9. `app/Http/Controllers/Admin/PathReservationController.php` - HTTP API
10. `app/Http/Requests/StorePathReservationRequest.php` - FormRequest для создания
11. `app/Http/Requests/DestroyPathReservationRequest.php` - FormRequest для удаления
12. `app/Policies/RouteReservationPolicy.php` - политика
13. `tests/Feature/PathReservationServiceTest.php` - тесты
14. `tests/Feature/PathReservationApiTest.php` - HTTP API тесты
15. `docs/implemented/path_reservation_service.md` - документация
16. `docs/review/path_reservation_service_code_review.md` - файл для ревью

### Изменённые файлы:

1. `bootstrap/providers.php` - добавлен `PathReservationServiceProvider`
2. `routes/web.php` - добавлены маршруты для API
3. `app/Providers/AuthServiceProvider.php` - добавлена политика для `RouteReservation`

### Критерии приёмки:

✅ Таблица `route_reservations` и репозиторий/сервис реализованы  
✅ API `reservePath($path,$source)` бросает ошибку при повторном резерве  
✅ Интеграция со статическим конфигом  
✅ Освобождение срабатывает при выключении плагина (через `releaseBySource`)  
✅ Тесты зелёные (35 passed, 73 assertions)

### Исправления после ревью:

1. **DELETE-роут**: добавлен wildcard `->where('path', '.*')` для многосегментных путей
2. **Политика**: добавлен метод `deleteAny()` и изменён middleware
3. **isUniqueViolation**: код ошибки приводится к строке `(string)$e->getCode()`
4. **Мутатор**: добавлен `setPathAttribute()` в модель для защиты от прямого создания
5. **Нормализация**: защита от `./`, `../` и дублирующих слэшей
6. **Атомарность**: `releasePath()` использует `deleteIfOwnedBy()` для атомарной операции
7. **RFC 7807**: изменён `type` на `about:blank`
8. **Док-комментарии**: исправлены пути в контроллере
9. **Контракт**: добавлен комментарий о разделении с `ReservedRouteRegistry`
10. **HTTP тесты**: добавлен `PathReservationApiTest` с полным покрытием API

### Дополнительные улучшения (nice-to-have):

11. **Переиспользование нормализатора в провайдере**:

    -   Статические пути из конфига нормализуются через `PathNormalizer::normalize()`
    -   Исключает расхождения при изменении правил нормализации
    -   Невалидные пути логируются, но не прерывают загрузку

12. **FormRequest классы**:

    -   Созданы `StorePathReservationRequest` и `DestroyPathReservationRequest`
    -   Валидация вынесена из контроллера в отдельные классы
    -   Улучшена читаемость и расширяемость кода
    -   Кастомные сообщения об ошибках валидации

13. **DELETE с path в body**:

    -   Поддержка передачи `path` как в URL параметре, так и в JSON body
    -   Полезно для экзотических URL-encode кейсов
    -   Метод `getPath()` в `DestroyPathReservationRequest` выбирает источник автоматически

14. **Аудит логирование**:

    -   Все операции `reserve` и `release` логируются в таблицу `audits`
    -   Сохраняется: пользователь, действие, путь, источник, IP, User-Agent
    -   Ошибки логирования не прерывают выполнение операции
    -   Помогает разбирать конфликты источников

15. **Аутентификация**:
    -   Оставлен `auth` middleware (sanctum не установлен в проекте)
    -   При необходимости можно легко переключить на `auth:sanctum` или `auth:passport`
