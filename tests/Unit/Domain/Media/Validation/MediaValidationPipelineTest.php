<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Validation;

use App\Domain\Media\Validation\MediaValidationException;
use App\Domain\Media\Validation\MediaValidationPipeline;
use App\Domain\Media\Validation\MediaValidatorInterface;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

final class MediaValidationPipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_runs_all_supported_validators(): void
    {
        $validator1 = Mockery::mock(MediaValidatorInterface::class);
        $validator2 = Mockery::mock(MediaValidatorInterface::class);
        $validator3 = Mockery::mock(MediaValidatorInterface::class);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        $validator1->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator1->shouldReceive('validate')->with($file, $mime)->once();

        $validator2->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator2->shouldReceive('validate')->with($file, $mime)->once();

        $validator3->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator3->shouldReceive('validate')->with($file, $mime)->once();

        $pipeline = new MediaValidationPipeline([$validator1, $validator2, $validator3]);
        $pipeline->validate($file, $mime);

        $this->assertTrue(true);
    }

    public function test_stops_on_first_validation_error(): void
    {
        $validator1 = Mockery::mock(MediaValidatorInterface::class);
        $validator2 = Mockery::mock(MediaValidatorInterface::class);
        $validator3 = Mockery::mock(MediaValidatorInterface::class);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        $validator1->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator1->shouldReceive('validate')
            ->with($file, $mime)
            ->once()
            ->andThrow(new MediaValidationException('First error', 'Validator1'));

        // Второй и третий валидаторы не должны быть вызваны
        $validator2->shouldNotReceive('supports');
        $validator2->shouldNotReceive('validate');

        $validator3->shouldNotReceive('supports');
        $validator3->shouldNotReceive('validate');

        $pipeline = new MediaValidationPipeline([$validator1, $validator2, $validator3]);

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage('First error');

        $pipeline->validate($file, $mime);
    }

    public function test_skips_validators_that_dont_support_mime(): void
    {
        $validator1 = Mockery::mock(MediaValidatorInterface::class);
        $validator2 = Mockery::mock(MediaValidatorInterface::class);
        $validator3 = Mockery::mock(MediaValidatorInterface::class);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        $validator1->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator1->shouldReceive('validate')->with($file, $mime)->once();

        $validator2->shouldReceive('supports')->with($mime)->once()->andReturn(false);
        $validator2->shouldNotReceive('validate');

        $validator3->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator3->shouldReceive('validate')->with($file, $mime)->once();

        $pipeline = new MediaValidationPipeline([$validator1, $validator2, $validator3]);
        $pipeline->validate($file, $mime);

        $this->assertTrue(true);
    }

    public function test_handles_empty_validators_list(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        $pipeline = new MediaValidationPipeline([]);
        
        // Не должно быть исключения
        $pipeline->validate($file, $mime);

        $this->assertTrue(true);
    }

    public function test_handles_invalid_validator_interface(): void
    {
        $validator1 = Mockery::mock(MediaValidatorInterface::class);
        $invalidValidator = new \stdClass(); // Не реализует интерфейс
        $validator2 = Mockery::mock(MediaValidatorInterface::class);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        $validator1->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator1->shouldReceive('validate')->with($file, $mime)->once();

        // Невалидный валидатор должен быть пропущен
        // (не должно быть вызова методов на stdClass)

        $validator2->shouldReceive('supports')->with($mime)->once()->andReturn(true);
        $validator2->shouldReceive('validate')->with($file, $mime)->once();

        $pipeline = new MediaValidationPipeline([$validator1, $invalidValidator, $validator2]);
        $pipeline->validate($file, $mime);

        $this->assertTrue(true);
    }

    public function test_validates_in_correct_order(): void
    {
        $validator1 = Mockery::mock(MediaValidatorInterface::class);
        $validator2 = Mockery::mock(MediaValidatorInterface::class);
        $validator3 = Mockery::mock(MediaValidatorInterface::class);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $mime = 'image/jpeg';

        // Проверяем порядок вызовов через последовательность expects
        $validator1->shouldReceive('supports')->with($mime)->ordered()->once()->andReturn(true);
        $validator1->shouldReceive('validate')->with($file, $mime)->ordered()->once();

        $validator2->shouldReceive('supports')->with($mime)->ordered()->once()->andReturn(true);
        $validator2->shouldReceive('validate')->with($file, $mime)->ordered()->once();

        $validator3->shouldReceive('supports')->with($mime)->ordered()->once()->andReturn(true);
        $validator3->shouldReceive('validate')->with($file, $mime)->ordered()->once();

        $pipeline = new MediaValidationPipeline([$validator1, $validator2, $validator3]);
        $pipeline->validate($file, $mime);

        $this->assertTrue(true);
    }
}

