# Система обработки ошибок

## ErrorKernel

-   Глобальный обработчик зарегистрирован в `bootstrap/app.php` и всегда делегирует JSON-ответы API в `ErrorKernel`.```73:90:bootstrap/app.php
    $kernel = app(ErrorKernel::class);
$payload = $kernel->resolve($e);
    return ErrorResponseFactory::make($payload);

````
- `AppServiceProvider` собирает `ErrorKernel` из `config/errors.php`, а также публикует `ErrorFactory` в контейнер.```48:59:app/Providers/AppServiceProvider.php
$this->app->singleton(ErrorKernel::class, function ($app) {
    $config = config('errors');
    return ErrorKernel::fromConfig($config, $app);
});
````

-   Контроллеры и middleware используют трейт `App\Support\Errors\ThrowsErrors`, который строит payload через `ErrorFactory` и выбрасывает `HttpErrorException`.

## Контракт ответа

-   Все ошибки сериализуются через `App\Support\Errors\ErrorPayload` (см. [docs/30-reference/errors.md](../30-reference/errors.md)).
-   `ErrorResponseFactory` создаёт `application/problem+json` и применяет административные заголовки.```1:24:app/Support/Errors/ErrorResponseFactory.php
    return response()->json($payload->toArray(), $payload->status);

````
- Единый перечень кодов хранится в `App\Support\Errors\ErrorCode`.

## Конфигурация (`config/errors.php`)

- `types` — каталог `ErrorCode → {uri,title,status,detail}`. Эти значения попадают в payload по умолчанию.
- `mappings` — соответствие `Throwable → ErrorPayload`. Билдеры получают `ErrorFactory`, могут менять detail/status/meta и определять логику отчётности.```214:290:config/errors.php
ValidationException::class => [
    'builder' => static function (ValidationException $exception, ErrorFactory $factory): ErrorPayload {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($detail)
            ->meta(['errors' => $errors])
            ->build();
    },
],
````

-   `fallback` строит INTERNAL_SERVER_ERROR и задаёт параметры логирования по умолчанию.

## Логирование

-   `ErrorKernel` перед каждой выдачей payload вызывает `App\Support\Errors\ErrorReporter`.```104:120:app/Support/Errors/ErrorKernel.php
    ErrorReporter::report($throwable, $payload, $reportDefinition);

````
- `ErrorReporter` автоматически добавляет `trace_id`, `request_id`, `user_id` и объединяет кастомный контекст (SQL, Retry-After и т. д.), после чего пишет в выбранный уровень.```1:90:app/Support/Errors/ErrorReporter.php
Log::log($level, $message, $context);
````

## Использование

-   Для ручного формирования ошибок в контроллерах/middleware применяйте `ThrowsErrors` (`throwError`, `unauthorized`, `tooManyRequests`, …).
-   Доменные исключения реализуют `App\Contracts\ErrorConvertible` и строят payload через `ErrorFactory`.
-   Для нештатных ситуаций вне HTTP (например, сервисы) выбрасывайте `HttpErrorException` либо `ErrorConvertible`, чтобы ErrorKernel корректно сериализовал ответ.

## Документация

-   Структура ответа и таблица кодов — в `docs/30-reference/errors.md`. После изменения `ErrorCode` или `config/errors.php` требуется `composer docs:gen` (включая `php artisan docs:errors`).
-   PR, затрагивающие ошибки, помечайте `requires: docs:gen`.
