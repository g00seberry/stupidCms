<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use Illuminate\Http\UploadedFile;

/**
 * Интерфейс валидатора медиа-файлов.
 *
 * Валидаторы проверяют файл на соответствие определённым критериям
 * (MIME по сигнатурам, corruption, размеры, длительность и т.д.).
 *
 * @package App\Domain\Media\Validation
 */
interface MediaValidatorInterface
{
    /**
     * Проверить, поддерживает ли валидатор указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если валидатор может обработать файл
     */
    public function supports(string $mime): bool;

    /**
     * Валидировать файл.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return void
     * @throws \App\Domain\Media\Validation\MediaValidationException Если валидация не пройдена
     */
    public function validate(UploadedFile $file, string $mime): void;
}

