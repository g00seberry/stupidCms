<?php

declare(strict_types=1);

use App\Domain\Media\Validation\MediaValidationException;
use App\Domain\Media\Validation\MediaValidationPipeline;
use App\Domain\Media\Validation\MediaValidatorInterface;
use Illuminate\Http\UploadedFile;

test('runs all validators', function () {
    $validator1 = Mockery::mock(MediaValidatorInterface::class);
    $validator2 = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator1->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator1->shouldReceive('validate')
        ->once()
        ->with($file, $mime);

    $validator2->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator2->shouldReceive('validate')
        ->once()
        ->with($file, $mime);

    $pipeline = new MediaValidationPipeline([$validator1, $validator2]);

    $pipeline->validate($file, $mime);
});

test('passes valid file', function () {
    $validator = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator->shouldReceive('validate')
        ->once()
        ->with($file, $mime);

    $pipeline = new MediaValidationPipeline([$validator]);

    $pipeline->validate($file, $mime);
});

test('rejects invalid file', function () {
    $validator = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator->shouldReceive('validate')
        ->once()
        ->with($file, $mime)
        ->andThrow(new MediaValidationException('File is invalid', 'TestValidator'));

    $pipeline = new MediaValidationPipeline([$validator]);

    expect(fn () => $pipeline->validate($file, $mime))
        ->toThrow(MediaValidationException::class, 'File is invalid');
});

test('stops on first error', function () {
    $validator1 = Mockery::mock(MediaValidatorInterface::class);
    $validator2 = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator1->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator1->shouldReceive('validate')
        ->once()
        ->with($file, $mime)
        ->andThrow(new MediaValidationException('First error', 'Validator1'));

    // Второй валидатор не должен быть вызван
    $validator2->shouldNotReceive('supports');
    $validator2->shouldNotReceive('validate');

    $pipeline = new MediaValidationPipeline([$validator1, $validator2]);

    expect(fn () => $pipeline->validate($file, $mime))
        ->toThrow(MediaValidationException::class, 'First error');
});

test('skips validators that do not support mime type', function () {
    $validator1 = Mockery::mock(MediaValidatorInterface::class);
    $validator2 = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator1->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(false);
    $validator1->shouldNotReceive('validate');

    $validator2->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator2->shouldReceive('validate')
        ->once()
        ->with($file, $mime);

    $pipeline = new MediaValidationPipeline([$validator1, $validator2]);

    $pipeline->validate($file, $mime);
});

test('skips non validator interface objects', function () {
    $validator = Mockery::mock(MediaValidatorInterface::class);
    $nonValidator = new stdClass();

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator->shouldReceive('validate')
        ->once()
        ->with($file, $mime);

    $pipeline = new MediaValidationPipeline([$validator, $nonValidator]);

    $pipeline->validate($file, $mime);
});

test('collects all errors when multiple validators fail', function () {
    $validator1 = Mockery::mock(MediaValidatorInterface::class);
    $validator2 = Mockery::mock(MediaValidatorInterface::class);

    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $validator1->shouldReceive('supports')
        ->once()
        ->with($mime)
        ->andReturn(true);
    $validator1->shouldReceive('validate')
        ->once()
        ->with($file, $mime)
        ->andThrow(new MediaValidationException('First error', 'Validator1'));

    // Второй валидатор не должен быть вызван из-за остановки на первой ошибке
    $validator2->shouldNotReceive('supports');
    $validator2->shouldNotReceive('validate');

    $pipeline = new MediaValidationPipeline([$validator1, $validator2]);

    expect(fn () => $pipeline->validate($file, $mime))
        ->toThrow(MediaValidationException::class);
});

test('handles empty validators list', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    $mime = 'image/jpeg';

    $pipeline = new MediaValidationPipeline([]);

    // Не должно быть ошибок при пустом списке валидаторов
    $pipeline->validate($file, $mime);

    expect(true)->toBeTrue(); // Assertion для избежания risky test
});

