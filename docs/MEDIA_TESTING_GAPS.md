# Список недостающих тестов для системы управления мультимедиа

Документ содержит подробный список тестов, которые необходимо добавить для полного покрытия системы управления медиа-файлами.

**Дата составления:** 2025-01-17  
**Статус:** Требует реализации

---

## 1. Валидация (Validation)

### 1.1. SizeLimitValidator (`tests/Unit/Domain/Media/Validation/SizeLimitValidatorTest.php`)

-   [ ] **test_validates_file_size_limit_exceeded** — проверка превышения максимального размера файла
-   [ ] **test_validates_image_width_limit_exceeded** — проверка превышения максимальной ширины изображения
-   [ ] **test_validates_image_height_limit_exceeded** — проверка превышения максимальной высоты изображения
-   [ ] **test_passes_validation_when_limits_not_set** — валидация проходит, когда лимиты не заданы
-   [ ] **test_supports_all_mime_types** — валидатор поддерживает все MIME-типы
-   [ ] **test_validates_dimensions_for_different_image_formats** — проверка размеров для JPEG, PNG, WebP, GIF
-   [ ] **test_handles_corrupted_image_dimensions** — обработка случая, когда getimagesize возвращает false
-   [ ] **test_skips_dimension_validation_for_non_images** — пропуск проверки размеров для не-изображений

### 1.2. CorruptionValidator (`tests/Unit/Domain/Media/Validation/CorruptionValidatorTest.php`)

-   [ ] **test_validates_corrupted_jpeg_file** — проверка повреждённого JPEG файла
-   [ ] **test_validates_corrupted_png_file** — проверка повреждённого PNG файла
-   [ ] **test_validates_empty_file** — проверка пустого файла
-   [ ] **test_validates_unreadable_file** — проверка нечитаемого файла
-   [ ] **test_passes_validation_for_valid_image** — валидация проходит для валидного изображения
-   [ ] **test_supports_only_image_mime_types** — валидатор поддерживает только изображения
-   [ ] **test_handles_unsupported_image_format_gracefully** — обработка неподдерживаемых форматов (HEIC, AVIF) без ошибок
-   [ ] **test_handles_image_processor_exception** — обработка исключений ImageProcessor с fallback на getimagesize

### 1.3. MimeSignatureValidator (`tests/Unit/Domain/Media/Validation/MimeSignatureValidatorTest.php`)

-   [ ] **test_validates_jpeg_signature** — проверка сигнатуры JPEG (FFD8FF)
-   [ ] **test_validates_png_signature** — проверка сигнатуры PNG (89504E47)
-   [ ] **test_validates_gif_signature** — проверка сигнатуры GIF (47494638)
-   [ ] **test_validates_webp_signature** — проверка сигнатуры WebP (RIFF + WEBP)
-   [ ] **test_validates_mp4_signature** — проверка сигнатуры MP4 (ftyp box)
-   [ ] **test_validates_pdf_signature** — проверка сигнатуры PDF (25504446)
-   [ ] **test_validates_mp3_signature** — проверка сигнатуры MP3 (ID3 или frame sync)
-   [ ] **test_validates_aiff_signature** — проверка сигнатуры AIFF (FORM + AIFF/AIFC)
-   [ ] **test_validates_heic_signature** — проверка сигнатуры HEIC/HEIF (ftyp box)
-   [ ] **test_validates_avif_signature** — проверка сигнатуры AVIF (ftyp box)
-   [ ] **test_rejects_mismatched_mime_and_signature** — отклонение при несоответствии MIME и сигнатуры
-   [ ] **test_handles_unknown_signature_gracefully** — обработка неизвестной сигнатуры (пропуск валидации)
-   [ ] **test_handles_unreadable_file** — обработка нечитаемого файла
-   [ ] **test_validates_mp4_audio_only_signature** — проверка сигнатуры MP4 audio-only
-   [ ] **test_validates_ftyp_box_at_different_offsets** — проверка ftyp box на разных позициях (0, 4, 8)

### 1.4. MediaValidationPipeline (`tests/Unit/Domain/Media/Validation/MediaValidationPipelineTest.php`)

-   [ ] **test_runs_all_supported_validators** — запуск всех поддерживаемых валидаторов
-   [ ] **test_stops_on_first_validation_error** — остановка на первой ошибке валидации
-   [ ] **test_skips_validators_that_dont_support_mime** — пропуск валидаторов, не поддерживающих MIME
-   [ ] **test_handles_empty_validators_list** — обработка пустого списка валидаторов
-   [ ] **test_handles_invalid_validator_interface** — обработка валидаторов, не реализующих интерфейс
-   [ ] **test_validates_in_correct_order** — проверка порядка выполнения валидаторов

---

## 2. Actions (Действия)

### 2.1. MediaStoreAction (`tests/Unit/Domain/Media/Actions/MediaStoreActionTest.php`)

-   [ ] **test_stores_file_with_hash_shard_path_strategy** — сохранение файла с hash-shard стратегией путей
-   [ ] **test_stores_file_with_by_date_path_strategy** — сохранение файла с by-date стратегией путей
-   [ ] **test_handles_file_storage_failure** — обработка ошибки сохранения файла на диск
-   [ ] **test_extracts_exif_metadata_for_jpeg** — извлечение EXIF метаданных для JPEG
-   [ ] **test_filters_exif_by_whitelist** — фильтрация EXIF по whitelist
-   [ ] **test_strips_exif_when_configured** — удаление EXIF при соответствующей настройке
-   [ ] **test_creates_media_metadata_for_video** — создание MediaMetadata для видео
-   [ ] **test_creates_media_metadata_for_audio** — создание MediaMetadata для аудио
-   [ ] **test_skips_media_metadata_for_images** — пропуск MediaMetadata для изображений
-   [ ] **test_handles_checksum_calculation_failure** — обработка ошибки вычисления checksum
-   [ ] **test_handles_metadata_extraction_failure** — обработка ошибки извлечения метаданных
-   [ ] **test_updates_existing_media_metadata_on_deduplication** — обновление метаданных существующего медиа при дедупликации
-   [ ] **test_dispatches_media_uploaded_event** — отправка события MediaUploaded
-   [ ] **test_handles_empty_file_size** — обработка случая, когда размер файла = 0
-   [ ] **test_handles_missing_file_extension** — обработка отсутствующего расширения файла
-   [ ] **test_normalizes_collection_slug** — нормализация slug коллекции

### 2.2. UpdateMediaMetadataAction (`tests/Unit/Domain/Media/Actions/UpdateMediaMetadataActionTest.php`)

-   [ ] **test_updates_title_alt_collection** — обновление title, alt, collection
-   [ ] **test_throws_exception_for_missing_media** — выбрасывание исключения для несуществующего медиа
-   [ ] **test_updates_soft_deleted_media** — обновление мягко удалённого медиа
-   [ ] **test_handles_partial_updates** — обработка частичных обновлений (только title, только alt и т.д.)
-   [ ] **test_normalizes_collection_on_update** — нормализация коллекции при обновлении

### 2.3. ListMediaAction (`tests/Unit/Domain/Media/Actions/ListMediaActionTest.php`)

-   [ ] **test_paginates_media_correctly** — корректная пагинация медиа
-   [ ] **test_delegates_to_repository** — делегирование запроса в репозиторий
-   [ ] **test_handles_empty_results** — обработка пустых результатов

---

## 3. Services (Сервисы)

### 3.1. CollectionRulesResolver (`tests/Unit/Domain/Media/Services/CollectionRulesResolverTest.php`)

-   [ ] **test_returns_collection_specific_rules** — возврат правил для конкретной коллекции
-   [ ] **test_returns_global_rules_for_null_collection** — возврат глобальных правил для null коллекции
-   [ ] **test_returns_global_rules_for_empty_collection** — возврат глобальных правил для пустой коллекции
-   [ ] **test_merges_collection_rules_with_global** — объединение правил коллекции с глобальными
-   [ ] **test_returns_allowed_mimes_for_collection** — возврат разрешённых MIME для коллекции
-   [ ] **test_returns_max_size_bytes_for_collection** — возврат максимального размера для коллекции
-   [ ] **test_handles_missing_collection_config** — обработка отсутствующей конфигурации коллекции

### 3.2. StorageResolver (`tests/Unit/Domain/Media/Services/StorageResolverTest.php`)

-   [ ] **test_resolves_disk_by_collection** — определение диска по коллекции
-   [ ] **test_resolves_disk_by_kind** — определение диска по типу медиа (image/video/audio/document)
-   [ ] **test_resolves_default_disk_when_no_match** — определение диска по умолчанию при отсутствии совпадений
-   [ ] **test_resolves_media_disk_as_fallback** — использование 'media' как fallback
-   [ ] **test_detects_kind_from_mime** — определение kind из MIME-типа
-   [ ] **test_handles_null_mime_gracefully** — обработка null MIME
-   [ ] **test_returns_filesystem_for_upload** — возврат Filesystem для загрузки

### 3.3. ExifManager (`tests/Unit/Domain/Media/Services/ExifManagerTest.php`)

-   [ ] **test_filters_exif_by_whitelist** — фильтрация EXIF по whitelist
-   [ ] **test_returns_null_for_empty_whitelist** — возврат null для пустого whitelist
-   [ ] **test_handles_missing_exif_fields** — обработка отсутствующих полей EXIF
-   [ ] **test_extracts_color_profile_from_exif** — извлечение цветового профиля (ICC) из EXIF
-   [ ] **test_handles_base64_encoded_icc_profile** — обработка base64-закодированного ICC профиля
-   [ ] **test_handles_hex_encoded_icc_profile** — обработка hex-закодированного ICC профиля
-   [ ] **test_returns_null_when_no_icc_profile** — возврат null при отсутствии ICC профиля
-   [ ] **test_get_orientation_from_exif** — получение Orientation из EXIF (1-8)
-   [ ] **test_handles_auto_rotate_placeholder** — обработка placeholder для auto-rotate (TODO)
-   [ ] **test_handles_strip_exif_placeholder** — обработка placeholder для strip EXIF (TODO)

### 3.4. MediaMetadataExtractor (дополнительные тесты)

-   [ ] **test_extracts_exif_for_jpeg_with_orientation** — извлечение EXIF с Orientation для JPEG
-   [ ] **test_handles_missing_exif_data** — обработка отсутствующих EXIF данных
-   [ ] **test_extracts_metadata_for_webp** — извлечение метаданных для WebP
-   [ ] **test_extracts_metadata_for_gif** — извлечение метаданных для GIF
-   [ ] **test_handles_plugin_extraction_failure** — обработка ошибки извлечения через плагин
-   [ ] **test_uses_multiple_plugins_in_order** — использование нескольких плагинов в порядке приоритета

### 3.5. OnDemandVariantService (дополнительные тесты)

-   [ ] **test_generates_variant_with_custom_format** — генерация варианта с кастомным форматом
-   [ ] **test_generates_variant_with_custom_quality** — генерация варианта с кастомным качеством
-   [ ] **test_handles_original_smaller_than_max** — обработка случая, когда оригинал меньше max
-   [ ] **test_handles_file_read_failure** — обработка ошибки чтения файла
-   [ ] **test_handles_image_processing_failure** — обработка ошибки обработки изображения
-   [ ] **test_updates_existing_variant_record** — обновление существующей записи варианта
-   [ ] **test_dispatches_media_processed_event** — отправка события MediaProcessed
-   [ ] **test_builds_variant_path_correctly** — корректное построение пути для варианта
-   [ ] **test_handles_variant_without_extension** — обработка варианта без расширения
-   [ ] **test_handles_dot_directory_path** — обработка пути с '.' в директории

---

## 4. Jobs (Задачи)

### 4.1. GenerateVariantJob (`tests/Unit/Domain/Media/Jobs/GenerateVariantJobTest.php`)

-   [ ] **test_dispatches_job_for_variant_generation** — отправка job для генерации варианта
-   [ ] **test_marks_variant_as_processing** — пометка варианта как Processing
-   [ ] **test_handles_missing_media_gracefully** — обработка отсутствующего медиа без ошибки
-   [ ] **test_handles_soft_deleted_media** — обработка мягко удалённого медиа
-   [ ] **test_retries_on_failure** — повторные попытки при ошибке (tries = 3)
-   [ ] **test_uses_backoff_strategy** — использование стратегии backoff (5, 15, 60 секунд)
-   [ ] **test_calls_on_demand_variant_service** — вызов OnDemandVariantService::generateVariant

---

## 5. Listeners (Слушатели)

### 5.1. NotifyMediaEvent (`tests/Unit/Domain/Media/Listeners/NotifyMediaEventTest.php`)

-   [x] **test_logs_large_file_upload** — логирование загрузки большого файла (>10MB)
-   [x] **test_handles_media_uploaded_event** — обработка события MediaUploaded
-   [x] **test_handles_media_processed_event** — обработка события MediaProcessed (placeholder)
-   [x] **test_handles_media_deleted_event** — обработка события MediaDeleted (placeholder)
-   [x] **test_does_not_log_small_file_upload** — отсутствие логирования для маленьких файлов

---

## 6. HTTP Controllers (Контроллеры)

### 6.1. MediaController (дополнительные Feature тесты)

-   [x] **test_show_returns_404_for_missing_media** — show возвращает 404 для несуществующего медиа
-   [x] **test_show_returns_soft_deleted_media** — show возвращает мягко удалённое медиа
-   [x] **test_update_returns_404_for_missing_media** — update возвращает 403 для несуществующего медиа (UpdateMediaRequest::authorize() проверяет существование раньше)
-   [x] **test_destroy_returns_404_for_missing_media** — destroy возвращает 404 для несуществующего медиа
-   [x] **test_destroy_dispatches_media_deleted_event** — destroy отправляет событие MediaDeleted
-   [x] **test_restore_returns_404_for_not_deleted_media** — restore возвращает 404 для не удалённого медиа
-   [x] **test_store_returns_200_on_deduplication** — store возвращает 200 при дедупликации
-   [x] **test_store_returns_201_on_new_upload** — store возвращает 201 при новой загрузке
-   [x] **test_store_handles_validation_exception** — store обрабатывает MediaValidationException
-   [ ] **test_store_handles_storage_failure** — store обрабатывает ошибку сохранения на диск (требует мокирования Storage в MediaStoreAction)
-   [x] **test_index_validates_per_page_range** — index валидирует диапазон per_page (1-100)
-   [x] **test_index_handles_invalid_sort_field** — index обрабатывает невалидное поле сортировки
-   [x] **test_index_handles_invalid_order_direction** — index обрабатывает невалидное направление сортировки

### 6.2. MediaPreviewController (`tests/Feature/Admin/Media/MediaPreviewControllerTest.php`)

-   [x] **test_preview_returns_404_for_missing_media** — preview возвращает 404 для несуществующего медиа
-   [x] **test_preview_returns_422_for_invalid_variant** — preview возвращает 422 для невалидного варианта
-   [x] **test_preview_returns_500_on_generation_failure** — preview возвращает 500 при ошибке генерации
-   [x] **test_preview_serves_local_file_directly** — preview отдаёт локальный файл напрямую
-   [ ] **test_preview_redirects_to_signed_url_for_cloud** — preview редиректит на подписанный URL для облака (требует настройки облачного диска в тестовой среде)
-   [x] **test_preview_uses_default_variant_when_not_specified** — preview использует 'thumbnail' по умолчанию
-   [x] **test_download_returns_404_for_missing_media** — download возвращает 404 для несуществующего медиа
-   [x] **test_download_returns_500_on_url_generation_failure** — download возвращает 500 при ошибке генерации URL
-   [x] **test_download_serves_local_file_directly** — download отдаёт локальный файл напрямую
-   [ ] **test_download_redirects_to_signed_url_for_cloud** — download редиректит на подписанный URL для облака (требует настройки облачного диска в тестовой среде)
-   [x] **test_download_respects_signed_ttl_config** — download учитывает конфигурацию signed_ttl
-   [x] **test_preview_authorizes_access** — preview проверяет авторизацию доступа
-   [x] **test_download_authorizes_access** — download проверяет авторизацию доступа

---

## 7. Models (Модели)

### 7.1. Media Model (`tests/Unit/Models/MediaTest.php`)

-   [x] **test_kind_returns_image_for_image_mime** — kind возвращает 'image' для image/\* MIME
-   [x] **test_kind_returns_video_for_video_mime** — kind возвращает 'video' для video/\* MIME
-   [x] **test_kind_returns_audio_for_audio_mime** — kind возвращает 'audio' для audio/\* MIME
-   [x] **test_kind_returns_document_for_other_mime** — kind возвращает 'document' для других MIME
-   [x] **test_has_unique_constraint_on_disk_and_path** — проверка уникального ограничения на (disk, path)
-   [x] **test_soft_deletes_media** — мягкое удаление медиа
-   [x] **test_restores_soft_deleted_media** — восстановление мягко удалённого медиа
-   [x] **test_uses_ulid_as_primary_key** — использование ULID в качестве первичного ключа
-   [x] **test_has_variants_relationship** — наличие отношения variants
-   [x] **test_has_metadata_relationship** — наличие отношения metadata

### 7.2. MediaMetadata Model (`tests/Unit/Models/MediaMetadataTest.php`)

-   [x] **test_belongs_to_media** — проверка отношения belongsTo Media
-   [x] **test_stores_normalized_av_metadata** — сохранение нормализованных AV метаданных

### 7.3. MediaVariant Model (`tests/Unit/Models/MediaVariantTest.php`)

-   [x] **test_belongs_to_media** — проверка отношения belongsTo Media
-   [x] **test_has_status_enum** — наличие enum статуса (Processing, Ready, Failed)
-   [x] **test_tracks_generation_timestamps** — отслеживание временных меток генерации (started_at, finished_at)

---

## 8. Events (События)

### 8.1. MediaUploaded Event (`tests/Unit/Domain/Media/Events/MediaUploadedTest.php`)

-   [ ] **test_event_contains_media_model** — событие содержит модель Media
-   [ ] **test_event_is_serializable** — событие сериализуемо

### 8.2. MediaDeleted Event (`tests/Unit/Domain/Media/Events/MediaDeletedTest.php`)

-   [ ] **test_event_contains_media_model** — событие содержит модель Media
-   [ ] **test_event_is_serializable** — событие сериализуемо

### 8.3. MediaProcessed Event (`tests/Unit/Domain/Media/Events/MediaProcessedTest.php`)

-   [ ] **test_event_contains_media_and_variant** — событие содержит Media и MediaVariant
-   [ ] **test_event_is_serializable** — событие сериализуемо

---

## 9. Repository (Репозиторий)

### 9.1. EloquentMediaRepository (дополнительные тесты)

-   [ ] **test_paginates_with_complex_filters** — пагинация со сложными фильтрами
-   [ ] **test_searches_by_title_and_original_name** — поиск по title и original_name
-   [ ] **test_filters_by_mime_prefix** — фильтрация по префиксу MIME
-   [ ] **test_sorts_by_custom_fields** — сортировка по кастомным полям
-   [ ] **test_handles_soft_deleted_filter_correctly** — корректная обработка фильтра soft-deleted
-   [ ] **test_handles_empty_search_query** — обработка пустого поискового запроса
-   [ ] **test_handles_special_characters_in_search** — обработка специальных символов в поиске

---

## 10. Edge Cases & Integration (Граничные случаи и интеграция)

### 10.1. Path Strategies (`tests/Feature/Admin/Media/MediaPathStrategyTest.php`)

-   [ ] **test_hash_shard_creates_correct_directory_structure** — hash-shard создаёт корректную структуру директорий
-   [ ] **test_by_date_creates_correct_directory_structure** — by-date создаёт корректную структуру директорий
-   [ ] **test_hash_shard_falls_back_to_date_when_no_checksum** — hash-shard использует дату при отсутствии checksum
-   [ ] **test_path_normalizes_backslashes** — нормализация обратных слешей в пути
-   [ ] **test_path_handles_empty_directory** — обработка пустой директории в пути

### 10.2. Deduplication (`tests/Feature/Admin/Media/MediaDeduplicationTest.php`)

-   [ ] **test_deduplicates_identical_files_with_different_names** — дедупликация идентичных файлов с разными именами
-   [ ] **test_deduplicates_identical_files_with_different_metadata** — дедупликация идентичных файлов с разными метаданными
-   [ ] **test_updates_metadata_on_deduplication** — обновление метаданных при дедупликации
-   [ ] **test_does_not_deduplicate_different_files** — отсутствие дедупликации для разных файлов
-   [ ] **test_handles_checksum_collision_theoretically** — теоретическая обработка коллизии checksum (SHA256)

### 10.3. EXIF Handling (`tests/Feature/Admin/Media/MediaExifTest.php`)

-   [ ] **test_preserves_exif_when_whitelist_empty** — сохранение EXIF при пустом whitelist
-   [ ] **test_filters_exif_by_whitelist** — фильтрация EXIF по whitelist
-   [ ] **test_strips_exif_when_configured** — удаление EXIF при соответствующей настройке
-   [ ] **test_handles_malformed_exif_data** — обработка некорректных EXIF данных
-   [ ] **test_extracts_orientation_from_exif** — извлечение Orientation из EXIF

### 10.4. Variant Generation (`tests/Feature/Admin/Media/MediaVariantGenerationTest.php`)

-   [ ] **test_generates_thumbnail_variant** — генерация варианта thumbnail
-   [ ] **test_generates_multiple_variants** — генерация нескольких вариантов
-   [ ] **test_regenerates_missing_variant_file** — регенерация отсутствующего файла варианта
-   [ ] **test_handles_variant_generation_failure** — обработка ошибки генерации варианта
-   [ ] **test_updates_variant_status_on_failure** — обновление статуса варианта при ошибке
-   [ ] **test_generates_variant_with_different_format** — генерация варианта с другим форматом
-   [ ] **test_generates_variant_with_different_quality** — генерация варианта с другим качеством
-   [ ] **test_handles_very_large_image** — обработка очень большого изображения
-   [ ] **test_handles_very_small_image** — обработка очень маленького изображения
-   [ ] **test_preserves_aspect_ratio** — сохранение соотношения сторон

### 10.5. Cloud Storage Integration (`tests/Feature/Admin/Media/MediaCloudStorageTest.php`)

-   [ ] **test_uploads_to_s3_disk** — загрузка на S3 диск
-   [ ] **test_generates_signed_url_for_s3** — генерация подписанного URL для S3
-   [ ] **test_handles_s3_upload_failure** — обработка ошибки загрузки на S3
-   [ ] **test_handles_s3_url_generation_failure** — обработка ошибки генерации URL для S3
-   [ ] **test_respects_signed_url_ttl** — соблюдение TTL подписанного URL

### 10.6. Media Metadata Plugins (`tests/Unit/Domain/Media/Services/Plugins/`)

-   [ ] **test_ffprobe_plugin_extracts_video_metadata** — плагин ffprobe извлекает метаданные видео
-   [ ] **test_mediainfo_plugin_extracts_audio_metadata** — плагин mediainfo извлекает метаданные аудио
-   [ ] **test_exiftool_plugin_extracts_image_metadata** — плагин exiftool извлекает метаданные изображения
-   [ ] **test_plugins_handle_missing_executables** — плагины обрабатывают отсутствие исполняемых файлов
-   [ ] **test_plugins_handle_execution_failure** — плагины обрабатывают ошибки выполнения
-   [ ] **test_plugins_support_correct_mime_types** — плагины поддерживают корректные MIME-типы

---

## 11. Authorization & Policies (Авторизация и политики)

### 11.1. MediaPolicy (`tests/Unit/Policies/MediaPolicyTest.php`)

-   [ ] **test_view_any_requires_media_read_permission** — viewAny требует права media.read
-   [ ] **test_view_requires_media_read_permission** — view требует права media.read
-   [ ] **test_create_requires_media_create_permission** — create требует права media.create
-   [ ] **test_update_requires_media_update_permission** — update требует права media.update
-   [ ] **test_delete_requires_media_delete_permission** — delete требует права media.delete
-   [ ] **test_restore_requires_media_restore_permission** — restore требует права media.restore
-   [ ] **test_admin_has_full_access** — администратор имеет полный доступ

---

## 12. Request Validation (Валидация запросов)

### 12.1. StoreMediaRequest (`tests/Unit/Http/Requests/Admin/Media/StoreMediaRequestTest.php`)

-   [ ] **test_validates_file_is_required** — проверка обязательности файла
-   [ ] **test_validates_file_mime_type** — проверка MIME-типа файла
-   [ ] **test_validates_file_size_limit** — проверка ограничения размера файла
-   [ ] **test_validates_title_min_length** — проверка минимальной длины title
-   [ ] **test_validates_title_max_length** — проверка максимальной длины title
-   [ ] **test_validates_alt_min_length** — проверка минимальной длины alt
-   [ ] **test_validates_alt_max_length** — проверка максимальной длины alt
-   [ ] **test_validates_collection_format** — проверка формата коллекции

### 12.2. UpdateMediaRequest (`tests/Unit/Http/Requests/Admin/Media/UpdateMediaRequestTest.php`)

-   [ ] **test_validates_title_min_length** — проверка минимальной длины title
-   [ ] **test_validates_title_max_length** — проверка максимальной длины title
-   [ ] **test_validates_alt_min_length** — проверка минимальной длины alt
-   [ ] **test_validates_alt_max_length** — проверка максимальной длины alt
-   [ ] **test_allows_partial_updates** — разрешение частичных обновлений

### 12.3. IndexMediaRequest (`tests/Unit/Http/Requests/Admin/Media/IndexMediaRequestTest.php`)

-   [ ] **test_validates_per_page_range** — проверка диапазона per_page (1-100)
-   [ ] **test_validates_sort_field** — проверка поля сортировки
-   [ ] **test_validates_order_direction** — проверка направления сортировки
-   [ ] **test_validates_deleted_filter** — проверка фильтра deleted

---

## 13. Resources (Ресурсы)

### 13.1. MediaResource (дополнительные тесты)

-   [ ] **test_includes_preview_urls_for_images** — включение preview_urls для изображений
-   [ ] **test_includes_download_url** — включение download_url
-   [ ] **test_excludes_sensitive_data** — исключение чувствительных данных
-   [ ] **test_formats_dates_correctly** — корректное форматирование дат
-   [ ] **test_handles_null_values** — обработка null значений

---

## 14. Performance & Load (Производительность и нагрузка)

### 14.1. Performance Tests (`tests/Performance/Media/`)

-   [ ] **test_handles_concurrent_uploads** — обработка одновременных загрузок
-   [ ] **test_handles_large_batch_uploads** — обработка больших пакетных загрузок
-   [ ] **test_variant_generation_performance** — производительность генерации вариантов
-   [ ] **test_search_performance_with_large_dataset** — производительность поиска с большим набором данных
-   [ ] **test_pagination_performance** — производительность пагинации

---

## Приоритеты реализации

### Высокий приоритет (критично для стабильности)

1. Валидация (SizeLimitValidator, CorruptionValidator, MimeSignatureValidator)
2. MediaStoreAction (особенно обработка ошибок)
3. OnDemandVariantService (обработка ошибок генерации)
4. MediaController (обработка ошибок в HTTP endpoints)
5. MediaPreviewController (обработка ошибок)

### Средний приоритет (важно для функциональности)

1. ExifManager (фильтрация и обработка EXIF)
2. CollectionRulesResolver и StorageResolver
3. GenerateVariantJob
4. Models (Media, MediaMetadata, MediaVariant)
5. Events и Listeners

### Низкий приоритет (улучшения и edge cases)

1. Performance тесты
2. Дополнительные edge cases
3. Интеграционные тесты с облачными хранилищами
4. Расширенные тесты плагинов метаданных

---

## Примечания

-   Все тесты должны использовать `declare(strict_types=1);`
-   Все тесты должны следовать PSR-12
-   Использовать Pest для написания тестов
-   Покрытие должно быть не менее 80% для критичных компонентов
-   После добавления тестов запускать `php artisan test`, `composer scribe:gen` и `php artisan docs:generate`
