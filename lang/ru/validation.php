<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'unique_page_slug' => 'Этот URL уже используется другой страницей. Измените слуг или сохраните с автоправкой.',
    'slug_reserved' => 'Значение поля :attribute конфликтует с зарезервированными маршрутами (например: admin, api).',
    'published_at_not_in_future' => 'Дата публикации не может быть в будущем для статуса "published"',
    'entry_not_found' => 'Запись с таким ID не найдена',
    'invalid_json_value' => 'Поле :attribute должно содержать допустимое JSON-значение.',
    'json_value_too_large' => 'Поле :attribute превышает допустимый размер :max байт после сериализации.',

];

