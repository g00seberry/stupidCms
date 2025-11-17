<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use Illuminate\Http\UploadedFile;

/**
 * Pipeline валидации медиа-файлов.
 *
 * Последовательно применяет все зарегистрированные валидаторы к файлу.
 * Останавливается на первой ошибке валидации.
 *
 * @package App\Domain\Media\Validation
 */
class MediaValidationPipeline
{
    /**
     * @param iterable<\App\Domain\Media\Validation\MediaValidatorInterface> $validators Список валидаторов
     */
    public function __construct(
        private readonly iterable $validators = []
    ) {
    }

    /**
     * Валидировать файл через все зарегистрированные валидаторы.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return void
     * @throws \App\Domain\Media\Validation\MediaValidationException Если валидация не пройдена
     */
    public function validate(UploadedFile $file, string $mime): void
    {
        foreach ($this->validators as $validator) {
            if (! $validator instanceof MediaValidatorInterface) {
                continue;
            }

            if (! $validator->supports($mime)) {
                continue;
            }

            $validator->validate($file, $mime);
        }
    }
}

