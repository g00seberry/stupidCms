# Domain Services

## BladeTemplateResolver
**ID:** `domain_service:View/BladeTemplateResolver`
**Path:** `app/Domain/View/BladeTemplateResolver.php`

Резолвер для выбора Blade-шаблона на основе полей template.

### Details
Приоритет выбора шаблона:
1. Entry.template_override (если задано — используется как полное имя вью)
2. PostType.template (если задано)
3. templates.index (дефолтный шаблон)
Все шаблоны должны находиться в папке templates или дочерних папках.

### Meta
- **Methods:** `forEntry`
- **Dependencies:** `App\Domain\View\TemplatePathValidator`
- **Interface:** `App\Domain\View\TemplateResolver`

### Tags
`view`


---

## ConditionalRule
**ID:** `domain_service:Blueprint/Validation/Rules/ConditionalRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/ConditionalRule.php`

Доменное правило валидации: условное правило.

### Details
Применяется в зависимости от значения другого поля.
Типы: 'required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'

### Meta
- **Methods:** `getType`, `getParams`, `getField`, `getValue`, `getOperator`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## ConditionalRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/ConditionalRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/ConditionalRuleHandler.php`

Обработчик правила ConditionalRule.

### Details
Преобразует ConditionalRule в строку Laravel правила валидации
(например, 'required_if:is_published,true').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## CorruptionValidator
**ID:** `domain_service:Media/Validation/CorruptionValidator`
**Path:** `app/Domain/Media/Validation/CorruptionValidator.php`

Валидатор проверки целостности (corruption) медиа-файлов.

### Details
Проверяет, что файл не повреждён и может быть корректно обработан.
Для изображений пытается открыть файл через ImageProcessor.
Для видео/аудио проверка выполняется через плагины метаданных.

### Meta
- **Methods:** `supports`, `validate`
- **Dependencies:** `App\Domain\Media\Images\ImageProcessor`
- **Interface:** `App\Domain\Media\Validation\MediaValidatorInterface`

### Tags
`media`, `validation`


---

## DataTypeMapper
**ID:** `domain_service:Blueprint/Validation/DataTypeMapper`
**Path:** `app/Domain/Blueprint/Validation/DataTypeMapper.php`

Маппер типов данных Path в типы для валидации.

### Details
Преобразует data_type из Path (string, text, int, float, bool, datetime, json, ref)
в типы для валидации (string, integer, numeric, boolean, date, array).

### Meta
- **Methods:** `mapToValidationType`, `isSupported`

### Tags
`blueprint`, `validation`


---

## DistinctRule
**ID:** `domain_service:Blueprint/Validation/Rules/DistinctRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/DistinctRule.php`

Правило валидации: уникальность элементов массива.

### Details
Преобразуется в кастомное правило DistinctObjects, которое сравнивает
элементы массива по их JSON-сериализации. Это обеспечивает корректную
работу с массивами объектов - объекты сравниваются как строки (по их
JSON-представлению).
Может применяться к любым типам данных (простые значения и объекты).
Пользователь сам отвечает за корректность применения правила.

### Meta
- **Methods:** `getType`, `getParams`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## DistinctRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/DistinctRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/DistinctRuleHandler.php`

Обработчик правила DistinctRule.

### Details
Преобразует DistinctRule в кастомное правило DistinctObjects,
которое сравнивает элементы массива по их JSON-сериализации.
Это обеспечивает корректную работу с массивами объектов.

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## EloquentMediaRepository
**ID:** `domain_service:Media/EloquentMediaRepository`
**Path:** `app/Domain/Media/EloquentMediaRepository.php`

Реализация MediaRepository на базе Eloquent.

### Meta
- **Methods:** `buildQuery`, `paginate`, `get`
- **Interface:** `App\Domain\Media\MediaRepository`

### Tags
`media`


---

## EntryValidationService
**ID:** `domain_service:Blueprint/Validation/EntryValidationService`
**Path:** `app/Domain/Blueprint/Validation/EntryValidationService.php`

Доменный сервис валидации контента Entry на основе Blueprint.

### Details
Строит RuleSet для поля content_json на основе структуры Path в Blueprint.
Преобразует full_path в точечную нотацию и применяет validation_rules из каждого Path.
Автоматически создаёт правила типов данных на основе data_type, если они не указаны явно.

### Meta
- **Methods:** `buildRulesFor`
- **Dependencies:** `App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface`, `App\Domain\Blueprint\Validation\FieldPathBuilder`, `App\Domain\Blueprint\Validation\DataTypeMapper`, `App\Domain\Blueprint\Validation\Rules\RuleFactory`
- **Interface:** `App\Domain\Blueprint\Validation\EntryValidationServiceInterface`

### Tags
`blueprint`, `validation`


---

## ExifManager
**ID:** `domain_service:Media/Services/ExifManager`
**Path:** `app/Domain/Media/Services/ExifManager.php`

Менеджер для управления EXIF данными изображений.

### Details
Поддерживает операции:
- Автоматический поворот изображения на основе EXIF Orientation
- Удаление (strip) EXIF данных
- Фильтрация EXIF полей по whitelist
- Сохранение цветового профиля (ICC)

### Meta
- **Methods:** `autoRotate`, `stripExif`, `filterExif`, `extractColorProfile`
- **Dependencies:** `App\Domain\Media\Images\ImageProcessor`

### Tags
`media`, `service`


---

## ExiftoolMediaMetadataPlugin
**ID:** `domain_service:Media/Services/ExiftoolMediaMetadataPlugin`
**Path:** `app/Domain/Media/Services/ExiftoolMediaMetadataPlugin.php`

Плагин метаданных, основанный на утилите exiftool.

### Details
Использует exiftool для извлечения детальных метаданных из изображений,
видео и аудио файлов. Особенно полезен для EXIF данных и метаданных,
недоступных через другие инструменты.

### Meta
- **Methods:** `supports`, `extract`
- **Interface:** `App\Domain\Media\Services\MediaMetadataPlugin`

### Tags
`media`, `service`


---

## FfprobeMediaMetadataPlugin
**ID:** `domain_service:Media/Services/FfprobeMediaMetadataPlugin`
**Path:** `app/Domain/Media/Services/FfprobeMediaMetadataPlugin.php`

Плагин метаданных, основанный на утилите ffprobe.

### Details
Использует ffprobe для извлечения длительности, битрейта и фреймов
для аудио/видео файлов и возвращает нормализованный набор полей.

### Meta
- **Methods:** `supports`, `extract`
- **Interface:** `App\Domain\Media\Services\MediaMetadataPlugin`

### Tags
`media`, `service`


---

## FieldComparisonRule
**ID:** `domain_service:Blueprint/Validation/Rules/FieldComparisonRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/FieldComparisonRule.php`

Доменное правило валидации: сравнение значения поля с другим полем или константой.

### Details
Поддерживает операторы: '>=', '<=', '>', '<', '==', '!='
Может сравнивать с другим полем (otherField) или с константным значением (constantValue).

### Meta
- **Methods:** `getType`, `getParams`, `getOperator`, `getOtherField`, `getConstantValue`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## FieldComparisonRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/FieldComparisonRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/FieldComparisonRuleHandler.php`

Обработчик правила FieldComparisonRule.

### Details
Преобразует FieldComparisonRule в Laravel custom rule FieldComparison.

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## FieldDefinition
**ID:** `domain_service:Blueprint/Validation/Rules/FieldDefinition`
**Path:** `app/Domain/Blueprint/Validation/Rules/FieldDefinition.php`

Определение поля для валидации.

### Details
Содержит метаданные поля из Path, необходимые для построения правил валидации.

### Meta
- **Methods:** `isArray`, `isSingle`, `hasValidationRules`

### Tags
`blueprint`, `validation`, `rule`


---

## FieldPathBuilder
**ID:** `domain_service:Blueprint/Validation/FieldPathBuilder`
**Path:** `app/Domain/Blueprint/Validation/FieldPathBuilder.php`

Построитель путей полей для валидации.

### Details
Преобразует full_path из Path в путь для валидации с учётом cardinality.
Учитывает специфику правил валидации (например, distinct для массивов).

### Meta
- **Methods:** `buildFieldPath`

### Tags
`blueprint`, `validation`


---

## GdImageProcessor
**ID:** `domain_service:Media/Images/GdImageProcessor`
**Path:** `app/Domain/Media/Images/GdImageProcessor.php`

Реализация ImageProcessor на базе GD.

### Details
Ограничения: отсутствует поддержка HEIC/AVIF для open/encode.

### Meta
- **Methods:** `open`, `width`, `height`, `resize`, `encode`, `destroy`, `supports`
- **Interface:** `App\Domain\Media\Images\ImageProcessor`

### Tags
`media`, `image`


---

## GenerateVariantJob
**ID:** `domain_service:Media/Jobs/GenerateVariantJob`
**Path:** `app/Domain/Media/Jobs/GenerateVariantJob.php`

Job для генерации варианта медиа-файла.

### Details
Выполняет генерацию варианта изображения (thumbnail, resize и т.д.)
в фоновом режиме через очередь.

### Meta
- **Methods:** `backoff`, `handle`, `dispatch`, `dispatchIf`, `dispatchUnless`, `dispatchSync`, `dispatchAfterResponse`, `withChain`, `attempts`, `delete`, `fail`, `release`, `withFakeQueueInteractions`, `assertDeleted`, `assertNotDeleted`, `assertFailed`, `assertFailedWith`, `assertNotFailed`, `assertReleased`, `assertNotReleased`, `setJob`, `onConnection`, `onQueue`, `onGroup`, `withDeduplicator`, `allOnConnection`, `allOnQueue`, `delay`, `withoutDelay`, `afterCommit`, `beforeCommit`, `through`, `chain`, `prependToChain`, `appendToChain`, `dispatchNextJobInChain`, `invokeChainCatchCallbacks`, `assertHasChain`, `assertDoesntHaveChain`, `restoreModel`
- **Interface:** `Illuminate\Contracts\Queue\ShouldQueue`

### Tags
`media`, `job`


---

## GetId3MediaMetadataPlugin
**ID:** `domain_service:Media/Services/GetId3MediaMetadataPlugin`
**Path:** `app/Domain/Media/Services/GetId3MediaMetadataPlugin.php`

Плагин метаданных, основанный на библиотеке getID3.

### Details
Использует getID3 для извлечения метаданных видео/аудио файлов.
getID3 - это чистая PHP библиотека, не требующая внешних утилит.

### Meta
- **Methods:** `supports`, `extract`
- **Dependencies:** `getID3`
- **Interface:** `App\Domain\Media\Services\MediaMetadataPlugin`

### Tags
`media`, `service`


---

## GlideImageProcessor
**ID:** `domain_service:Media/Images/GlideImageProcessor`
**Path:** `app/Domain/Media/Images/GlideImageProcessor.php`

Реализация ImageProcessor на базе Intervention Image (как backend для Glide-стека).

### Details
Поддержка форматов зависит от выбранного драйвера (gd/imagick).
Для AVIF/HEIC нужен imagick с соответствующими кодеками.

### Meta
- **Methods:** `open`, `width`, `height`, `resize`, `encode`, `destroy`, `supports`
- **Dependencies:** `Intervention\Image\ImageManager`
- **Interface:** `App\Domain\Media\Images\ImageProcessor`

### Tags
`media`, `image`


---

## ImageRef
**ID:** `domain_service:Media/Images/ImageRef`
**Path:** `app/Domain/Media/Images/ImageProcessor.php`

Opaque-хэндл изображения для разных бэкендов.

### Details
Нельзя полагаться на конкретный тип $native вне драйвера.

### Meta


### Tags
`media`, `image`


---

## JwtService
**ID:** `domain_service:Auth/JwtService`
**Path:** `app/Domain/Auth/JwtService.php`

Service for issuing and verifying JWT access and refresh tokens.

### Details
Uses HS256 (HMAC with SHA-256) algorithm with a secret key.

### Meta
- **Methods:** `issueAccessToken`, `issueRefreshToken`, `encode`, `verify`

### Tags
`auth`


---

## LaravelValidationAdapter
**ID:** `domain_service:Blueprint/Validation/Adapters/LaravelValidationAdapter`
**Path:** `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php`

Адаптер для преобразования доменных RuleSet в Laravel правила валидации.

### Details
Преобразует доменные Rule объекты в строки правил валидации Laravel
через систему handlers. Не добавляет базовые типы данных автоматически.
Пользователь сам отвечает за указание всех необходимых правил.

### Meta
- **Methods:** `adapt`
- **Dependencies:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry`
- **Interface:** `App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface`

### Tags
`blueprint`, `validation`, `adapter`


---

## ListMediaAction
**ID:** `domain_service:Media/Actions/ListMediaAction`
**Path:** `app/Domain/Media/Actions/ListMediaAction.php`

CQRS-действие: выборка списка медиа по параметрам запроса.

### Meta
- **Methods:** `execute`
- **Dependencies:** `App\Domain\Media\MediaRepository`

### Tags
`media`, `action`


---

## LogMediaEvent
**ID:** `domain_service:Media/Listeners/LogMediaEvent`
**Path:** `app/Domain/Media/Listeners/LogMediaEvent.php`

Слушатель для логирования событий медиа-файлов.

### Details
Логирует все события жизненного цикла медиа-файлов:
- загрузка (MediaUploaded)
- обработка вариантов (MediaProcessed)
- удаление (MediaDeleted)

### Meta
- **Methods:** `handleMediaUploaded`, `handleMediaProcessed`, `handleMediaDeleted`

### Tags
`media`, `listener`


---

## MaxRule
**ID:** `domain_service:Blueprint/Validation/Rules/MaxRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/MaxRule.php`

Правило валидации: максимальное значение/длина.

### Details
Может применяться к любым типам данных.
Пользователь сам отвечает за корректность применения правила.

### Meta
- **Methods:** `getType`, `getParams`, `getValue`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## MaxRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/MaxRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/MaxRuleHandler.php`

Обработчик правила MaxRule.

### Details
Преобразует MaxRule в строку Laravel правила валидации (например, 'max:500').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## MediaConfigValidator
**ID:** `domain_service:Media/Validation/MediaConfigValidator`
**Path:** `app/Domain/Media/Validation/MediaConfigValidator.php`

Валидатор конфигурации медиа-файлов.

### Details
Проверяет корректность конфигурации, включая обязательные варианты изображений.

### Meta
- **Methods:** `validate`

### Tags
`media`, `validation`


---

## MediaDeleted
**ID:** `domain_service:Media/Events/MediaDeleted`
**Path:** `app/Domain/Media/Events/MediaDeleted.php`

Событие: медиа-файл удалён.

### Details
Отправляется после мягкого удаления (soft delete) медиа-файла.
Используется для логирования, уведомлений и автоматических интеграций (CDN purge).

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`
- **Dependencies:** `App\Models\Media`

### Tags
`media`, `event`


---

## MediaForceDeleteAction
**ID:** `domain_service:Media/Actions/MediaForceDeleteAction`
**Path:** `app/Domain/Media/Actions/MediaForceDeleteAction.php`

Действие для окончательного удаления медиа-файла.

### Details
Выполняет полное (hard) удаление медиа-файла: удаляет физические файлы
(основной файл и все варианты) с диска, затем удаляет записи из БД.
Отправляет событие MediaDeleted после успешного удаления.

### Meta
- **Methods:** `execute`

### Tags
`media`, `action`


---

## MediaMetadataDTO
**ID:** `domain_service:Media/DTO/MediaMetadataDTO`
**Path:** `app/Domain/Media/DTO/MediaMetadataDTO.php`

DTO для нормализованных метаданных медиа-файла.

### Details
Представляет унифицированную структуру метаданных, извлечённых
из различных источников (ImageProcessor, ffprobe, mediainfo, exiftool).
Данные из DTO используются для создания записей в специализированных таблицах:
- width, height, exif → MediaImage (для изображений)
- durationMs, bitrateKbps, frameRate, frameCount, videoCodec, audioCodec → MediaAvMetadata (для видео/аудио)

### Meta
- **Methods:** `toArray`, `fromArray`

### Tags
`media`, `dto`


---

## MediaMetadataExtractor
**ID:** `domain_service:Media/Services/MediaMetadataExtractor`
**Path:** `app/Domain/Media/Services/MediaMetadataExtractor.php`

Сервис для извлечения метаданных из медиа-файлов.

### Details
Извлекает размеры изображений, EXIF данные и другую информацию
из загруженных файлов. Использует плагины (getID3, ffprobe/mediainfo/exiftool)
с graceful fallback и кэшированием результатов.

### Meta
- **Methods:** `extract`
- **Dependencies:** `App\Domain\Media\Images\ImageProcessor`, `Illuminate\Contracts\Cache\Repository`

### Tags
`media`, `service`


---

## MediaProcessed
**ID:** `domain_service:Media/Events/MediaProcessed`
**Path:** `app/Domain/Media/Events/MediaProcessed.php`

Событие: медиа-файл обработан (сгенерирован вариант).

### Details
Отправляется после успешной генерации варианта медиа-файла.
Используется для логирования, уведомлений и автоматических интеграций (CDN purge).

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`
- **Dependencies:** `App\Models\Media`, `App\Models\MediaVariant`

### Tags
`media`, `event`


---

## MediaQuery
**ID:** `domain_service:Media/MediaQuery`
**Path:** `app/Domain/Media/MediaQuery.php`

Value Object для параметров выборки медиа.

### Meta
- **Methods:** `search`, `kind`, `mimePrefix`, `deletedFilter`, `sort`, `order`, `page`, `perPage`
- **Dependencies:** `App\Domain\Media\MediaDeletedFilter`

### Tags
`media`


---

## MediaStoreAction
**ID:** `domain_service:Media/Actions/MediaStoreAction`
**Path:** `app/Domain/Media/Actions/MediaStoreAction.php`

Действие для сохранения медиа-файла.

### Details
Обрабатывает загрузку файла: сохранение на диск, извлечение метаданных,
создание записи Media в БД и (опционально) нормализованных AV-метаданных
в отдельной таблице.
Использует общую систему ошибок (HttpErrorException) для обработки ошибок валидации
и сохранения файла.

### Meta
- **Methods:** `execute`
- **Dependencies:** `App\Domain\Media\Services\MediaMetadataExtractor`, `App\Domain\Media\Services\StorageResolver`, `App\Domain\Media\Validation\MediaValidationPipeline`, `App\Support\Errors\ErrorFactory`, `App\Domain\Media\Services\ExifManager`

### Tags
`media`, `action`


---

## MediaUploaded
**ID:** `domain_service:Media/Events/MediaUploaded`
**Path:** `app/Domain/Media/Events/MediaUploaded.php`

Событие: медиа-файл загружен.

### Details
Отправляется после успешной загрузки и сохранения медиа-файла в БД.
Используется для логирования, уведомлений и автоматических интеграций (CDN purge).

### Meta
- **Methods:** `dispatch`, `dispatchIf`, `dispatchUnless`, `broadcast`, `restoreModel`
- **Dependencies:** `App\Models\Media`

### Tags
`media`, `event`


---

## MediaValidationPipeline
**ID:** `domain_service:Media/Validation/MediaValidationPipeline`
**Path:** `app/Domain/Media/Validation/MediaValidationPipeline.php`

Pipeline валидации медиа-файлов.

### Details
Последовательно применяет все зарегистрированные валидаторы к файлу.
Останавливается на первой ошибке валидации.

### Meta
- **Methods:** `validate`

### Tags
`media`, `validation`


---

## MediainfoMediaMetadataPlugin
**ID:** `domain_service:Media/Services/MediainfoMediaMetadataPlugin`
**Path:** `app/Domain/Media/Services/MediainfoMediaMetadataPlugin.php`

Плагин метаданных, основанный на утилите mediainfo.

### Details
Использует mediainfo для извлечения метаданных видео/аудио файлов
с более детальной информацией, чем ffprobe (например, для некоторых форматов).

### Meta
- **Methods:** `supports`, `extract`
- **Interface:** `App\Domain\Media\Services\MediaMetadataPlugin`

### Tags
`media`, `service`


---

## MimeSignatureValidator
**ID:** `domain_service:Media/Validation/MimeSignatureValidator`
**Path:** `app/Domain/Media/Validation/MimeSignatureValidator.php`

Валидатор MIME-типа по сигнатурам файла (magic bytes).

### Details
Определяет реальный MIME-тип файла по его сигнатурам и сравнивает
с заявленным типом. Защищает от подмены расширения файла.

### Meta
- **Methods:** `supports`, `validate`
- **Interface:** `App\Domain\Media\Validation\MediaValidatorInterface`

### Tags
`media`, `validation`


---

## MinRule
**ID:** `domain_service:Blueprint/Validation/Rules/MinRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/MinRule.php`

Правило валидации: минимальное значение/длина.

### Details
Может применяться к любым типам данных.
Пользователь сам отвечает за корректность применения правила.

### Meta
- **Methods:** `getType`, `getParams`, `getValue`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## MinRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/MinRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/MinRuleHandler.php`

Обработчик правила MinRule.

### Details
Преобразует MinRule в строку Laravel правила валидации (например, 'min:1').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## NotifyMediaEvent
**ID:** `domain_service:Media/Listeners/NotifyMediaEvent`
**Path:** `app/Domain/Media/Listeners/NotifyMediaEvent.php`

Слушатель для отправки уведомлений о событиях медиа-файлов.

### Details
Отправляет уведомления при событиях жизненного цикла медиа-файлов.
Может быть расширен для интеграции с email, Slack, webhooks и т.д.

### Meta
- **Methods:** `handleMediaUploaded`, `handleMediaProcessed`, `handleMediaDeleted`

### Tags
`media`, `listener`


---

## NullableRule
**ID:** `domain_service:Blueprint/Validation/Rules/NullableRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/NullableRule.php`

Правило валидации: поле опционально (nullable).

### Details
Указывает, что поле может отсутствовать или быть null.

### Meta
- **Methods:** `getType`, `getParams`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## NullableRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/NullableRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/NullableRuleHandler.php`

Обработчик правила NullableRule.

### Details
Преобразует NullableRule в строку Laravel правила валидации ('nullable').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## OnDemandVariantService
**ID:** `domain_service:Media/Services/OnDemandVariantService`
**Path:** `app/Domain/Media/Services/OnDemandVariantService.php`

Сервис для генерации вариантов медиа-файлов по требованию.

### Details
Генерирует варианты изображений (thumbnails, resized) на лету
через абстракцию ImageProcessor (gd/imagick/glide/external).

### Meta
- **Methods:** `ensureVariant`, `generateVariant`
- **Dependencies:** `App\Domain\Media\Images\ImageProcessor`

### Tags
`media`, `service`


---

## PathNormalizer
**ID:** `domain_service:Routing/PathNormalizer`
**Path:** `app/Domain/Routing/PathNormalizer.php`

Сервис для нормализации путей.

### Details
Приводит пути к единому формату: удаляет query/fragment, гарантирует ведущий слэш,
убирает trailing слэш, приводит к нижнему регистру, применяет Unicode NFC нормализацию.

### Meta
- **Methods:** `normalize`

### Tags
`routing`


---

## PathValidationRulesConverter
**ID:** `domain_service:Blueprint/Validation/PathValidationRulesConverter`
**Path:** `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`

Конвертер правил валидации из Path в доменные Rule объекты.

### Details
Преобразует validation_rules из модели Path в массив доменных Rule объектов.
Не выполняет проверок совместимости правил с типами данных или cardinality.
Пользователь сам отвечает за корректность настройки правил.

### Meta
- **Methods:** `convert`
- **Dependencies:** `App\Domain\Blueprint\Validation\Rules\RuleFactory`
- **Interface:** `App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface`

### Tags
`blueprint`, `validation`


---

## PatternRule
**ID:** `domain_service:Blueprint/Validation/Rules/PatternRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/PatternRule.php`

Правило валидации: регулярное выражение (pattern).

### Details
Применяется только к строковым типам данных (string, text).

### Meta
- **Methods:** `getType`, `getParams`, `getPattern`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## PatternRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/PatternRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/PatternRuleHandler.php`

Обработчик правила PatternRule.

### Details
Преобразует PatternRule в строку Laravel правила валидации (например, 'regex:/pattern/').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## PostTypeOptions
**ID:** `domain_service:PostTypes/PostTypeOptions`
**Path:** `app/Domain/PostTypes/PostTypeOptions.php`

Value Object для опций типа записи (PostType).

### Details
Представляет типобезопасную структуру options_json для PostType.
Гарантирует валидность данных и централизованную нормализацию.
Схема:
- taxonomies: array<int> - массив id таксономий, разрешённых для этого типа записи
- fields: array<string, mixed> - произвольные поля (расширяемые)

### Meta
- **Methods:** `fromArray`, `empty`, `toArray`, `toApiArray`, `getAllowedTaxonomies`, `isTaxonomyAllowed`, `getField`, `hasField`

### Tags
`posttype`


---

## PublishingService
**ID:** `domain_service:Entries/PublishingService`
**Path:** `app/Domain/Entries/PublishingService.php`

Сервис для применения правил публикации записей.

### Details
Обрабатывает логику публикации Entry: автозаполнение published_at,
валидация инвариантов (дата публикации не в будущем).

### Meta
- **Methods:** `applyAndValidate`

### Tags
`entry`


---

## PurgeCdnCache
**ID:** `domain_service:Media/Listeners/PurgeCdnCache`
**Path:** `app/Domain/Media/Listeners/PurgeCdnCache.php`

Слушатель для очистки кэша CDN при событиях медиа-файлов.

### Details
Очищает кэш CDN при загрузке, обработке и удалении медиа-файлов.
Поддерживает различные CDN провайдеры через конфигурацию.

### Meta
- **Methods:** `handleMediaUploaded`, `handleMediaProcessed`, `handleMediaDeleted`

### Tags
`media`, `listener`


---

## RefreshTokenDto
**ID:** `domain_service:Auth/RefreshTokenDto`
**Path:** `app/Domain/Auth/RefreshTokenDto.php`

Data Transfer Object для RefreshToken.

### Details
Предоставляет типобезопасный доступ к данным refresh токена
без раскрытия Eloquent модели.

### Meta
- **Methods:** `isValid`, `isInvalid`
- **Dependencies:** `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`, `Carbon\Carbon`

### Tags
`auth`


---

## RefreshTokenRepositoryImpl
**ID:** `domain_service:Auth/RefreshTokenRepositoryImpl`
**Path:** `app/Domain/Auth/RefreshTokenRepositoryImpl.php`

Реализация RefreshTokenRepository с использованием Eloquent.

### Meta
- **Methods:** `store`, `markUsedConditionally`, `revoke`, `revokeFamily`, `find`, `deleteExpired`
- **Interface:** `App\Domain\Auth\RefreshTokenRepository`

### Tags
`auth`


---

## RequiredRule
**ID:** `domain_service:Blueprint/Validation/Rules/RequiredRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/RequiredRule.php`

Правило валидации: поле обязательно.

### Details
Указывает, что поле должно присутствовать и не может быть null или пустым.

### Meta
- **Methods:** `getType`, `getParams`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## RequiredRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/RequiredRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/RequiredRuleHandler.php`

Обработчик правила RequiredRule.

### Details
Преобразует RequiredRule в строку Laravel правила валидации ('required').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## RichTextSanitizer
**ID:** `domain_service:Sanitizer/RichTextSanitizer`
**Path:** `app/Domain/Sanitizer/RichTextSanitizer.php`

Сервис для санитизации HTML контента.

### Details
Очищает HTML от потенциально опасных элементов и атрибутов через HTMLPurifier.
Автоматически добавляет rel="noopener noreferrer" к ссылкам с target="_blank"
для защиты от атак через window.opener.

### Meta
- **Methods:** `sanitize`

### Tags
`sanitizer`


---

## RuleArrayManipulator
**ID:** `domain_service:Blueprint/Validation/RuleArrayManipulator`
**Path:** `app/Domain/Blueprint/Validation/RuleArrayManipulator.php`

Манипулятор массивов правил валидации.

### Details
Инкапсулирует логику вставки и манипуляции правилами в массивах.

### Meta
- **Methods:** `insertAfterRequired`, `ensureArrayRule`, `mergeRules`

### Tags
`blueprint`, `validation`


---

## RuleFactoryImpl
**ID:** `domain_service:Blueprint/Validation/Rules/RuleFactoryImpl`
**Path:** `app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php`

Реализация фабрики правил валидации.

### Meta
- **Methods:** `createMinRule`, `createMaxRule`, `createPatternRule`, `createRequiredRule`, `createNullableRule`, `createConditionalRule`, `createDistinctRule`, `createFieldComparisonRule`, `createTypeRule`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\RuleFactory`

### Tags
`blueprint`, `validation`, `rule`


---

## RuleHandlerRegistry
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry.php`

Реестр обработчиков правил валидации.

### Details
Управляет регистрацией и получением handlers для различных типов правил.

### Meta
- **Methods:** `register`, `getHandler`, `hasHandler`, `getRegisteredTypes`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## RuleSet
**ID:** `domain_service:Blueprint/Validation/Rules/RuleSet`
**Path:** `app/Domain/Blueprint/Validation/Rules/RuleSet.php`

Набор правил валидации для полей.

### Details
Хранит правила валидации, сгруппированные по путям полей.
Используется для построения полного набора правил валидации для Blueprint.

### Meta
- **Methods:** `addRule`, `getRulesForField`, `getAllRules`, `hasRulesForField`, `getFieldPaths`, `isEmpty`

### Tags
`blueprint`, `validation`, `rule`


---

## SizeLimitValidator
**ID:** `domain_service:Media/Validation/SizeLimitValidator`
**Path:** `app/Domain/Media/Validation/SizeLimitValidator.php`

Валидатор ограничений размера файла и размеров изображений/видео.

### Details
Проверяет, что размер файла и размеры контента (ширина/высота для изображений,
длительность для видео/аудио) не превышают установленные лимиты.

### Meta
- **Methods:** `supports`, `validate`
- **Interface:** `App\Domain\Media\Validation\MediaValidatorInterface`

### Tags
`media`, `validation`


---

## StorageResolver
**ID:** `domain_service:Media/Services/StorageResolver`
**Path:** `app/Domain/Media/Services/StorageResolver.php`

Резолвер дисков для медиа-хранилища.

### Details
Инкапсулирует логику выбора диска по типу медиа (MIME/kind),
используя конфигурацию config/media.php:
- media.disks.kinds
- media.disks.default

### Meta
- **Methods:** `resolveDiskName`, `filesystemForUpload`

### Tags
`media`, `service`


---

## TemplatePathValidator
**ID:** `domain_service:View/TemplatePathValidator`
**Path:** `app/Domain/View/TemplatePathValidator.php`

Валидатор путей шаблонов.

### Details
Проверяет, что шаблон находится в папке templates или дочерних папках.
Все остальные директории считаются системными и недоступны для шаблонов.

### Meta
- **Methods:** `validate`, `normalize`, `ensurePrefix`

### Tags
`view`


---

## TypeRule
**ID:** `domain_service:Blueprint/Validation/Rules/TypeRule`
**Path:** `app/Domain/Blueprint/Validation/Rules/TypeRule.php`

Правило валидации: тип данных.

### Details
Автоматически создаётся на основе data_type из Path.
Преобразуется в соответствующее Laravel правило валидации (string, integer, numeric, boolean, date, array).

### Meta
- **Methods:** `getType`, `getParams`, `getDataType`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Rule`

### Tags
`blueprint`, `validation`, `rule`


---

## TypeRuleHandler
**ID:** `domain_service:Blueprint/Validation/Rules/Handlers/TypeRuleHandler`
**Path:** `app/Domain/Blueprint/Validation/Rules/Handlers/TypeRuleHandler.php`

Обработчик правила TypeRule.

### Details
Преобразует TypeRule в строку Laravel правила валидации
(например, 'string', 'integer', 'numeric', 'boolean', 'date', 'array').

### Meta
- **Methods:** `supports`, `handle`
- **Interface:** `App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerInterface`

### Tags
`blueprint`, `validation`, `rule`, `handler`


---

## UpdateMediaMetadataAction
**ID:** `domain_service:Media/Actions/UpdateMediaMetadataAction`
**Path:** `app/Domain/Media/Actions/UpdateMediaMetadataAction.php`

CQRS-действие: обновление метаданных медиа (title, alt).

### Meta
- **Methods:** `execute`

### Tags
`media`, `action`


---

## ValidationConstants
**ID:** `domain_service:Blueprint/Validation/ValidationConstants`
**Path:** `app/Domain/Blueprint/Validation/ValidationConstants.php`

Константы для системы валидации Blueprint.

### Meta
- **Methods:** `getRequiredNullableRules`

### Tags
`blueprint`, `validation`


---

## ValidationError
**ID:** `domain_service:Blueprint/Validation/ValidationError`
**Path:** `app/Domain/Blueprint/Validation/ValidationError.php`

Ошибка валидации с доменной семантикой.

### Details
Содержит структурированную информацию об ошибке валидации:
- путь поля
- код ошибки (для локализации и обработки на фронтенде)
- параметры ошибки
- текстовое сообщение (опционально)

### Meta
- **Methods:** `getParam`, `hasParam`

### Tags
`blueprint`, `validation`


---

## ValidationResult
**ID:** `domain_service:Blueprint/Validation/ValidationResult`
**Path:** `app/Domain/Blueprint/Validation/ValidationResult.php`

Результат валидации контента Entry.

### Details
Содержит информацию об ошибках валидации, сгруппированных по полям.

### Meta
- **Methods:** `addError`, `hasErrors`, `getErrors`, `getErrorsForField`, `getFieldsWithErrors`, `hasErrorsForField`

### Tags
`blueprint`, `validation`


---
