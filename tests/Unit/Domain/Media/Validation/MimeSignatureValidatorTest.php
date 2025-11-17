<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media\Validation;

use App\Domain\Media\Validation\MediaValidationException;
use App\Domain\Media\Validation\MimeSignatureValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class MimeSignatureValidatorTest extends TestCase
{
    public function test_validates_jpeg_signature(): void
    {
        $validator = new MimeSignatureValidator();

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        // Не должно быть исключения
        $validator->validate($file, 'image/jpeg');

        $this->assertTrue(true);
    }

    public function test_validates_png_signature(): void
    {
        $validator = new MimeSignatureValidator();

        $file = UploadedFile::fake()->image('test.png', 100, 100);

        // Не должно быть исключения
        $validator->validate($file, 'image/png');

        $this->assertTrue(true);
    }

    public function test_validates_gif_signature(): void
    {
        $validator = new MimeSignatureValidator();

        $file = UploadedFile::fake()->image('test.gif', 100, 100);

        // Не должно быть исключения
        $validator->validate($file, 'image/gif');

        $this->assertTrue(true);
    }

    public function test_validates_webp_signature(): void
    {
        $validator = new MimeSignatureValidator();

        $file = UploadedFile::fake()->image('test.webp', 100, 100);

        // Не должно быть исключения
        $validator->validate($file, 'image/webp');

        $this->assertTrue(true);
    }

    public function test_validates_mp4_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой MP4 (ftyp box)
        $file = UploadedFile::fake()->create('test.mp4', 1000, 'video/mp4');
        $path = $file->getRealPath();
        
        // Записываем минимальный MP4 файл с ftyp box
        $mp4Header = hex2bin('000000206674797069736f6d0000020069736f6d69736f32');
        file_put_contents($path, $mp4Header . str_repeat("\x00", 1000 - strlen($mp4Header)));

        // Не должно быть исключения
        $validator->validate($file, 'video/mp4');

        $this->assertTrue(true);
    }

    public function test_validates_pdf_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой PDF
        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');
        $path = $file->getRealPath();
        
        // Записываем минимальный PDF файл
        $pdfContent = "%PDF-1.4\n" . str_repeat("test content\n", 100);
        file_put_contents($path, $pdfContent);

        // Не должно быть исключения
        $validator->validate($file, 'application/pdf');

        $this->assertTrue(true);
    }

    public function test_validates_mp3_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой MP3 (ID3)
        $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');
        $path = $file->getRealPath();
        
        // Записываем файл с ID3 заголовком
        $mp3Content = "ID3" . str_repeat("\x00", 1000 - 3);
        file_put_contents($path, $mp3Content);

        // Не должно быть исключения
        $validator->validate($file, 'audio/mpeg');

        $this->assertTrue(true);
    }

    public function test_validates_aiff_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой AIFF
        $file = UploadedFile::fake()->create('test.aiff', 1000, 'audio/aiff');
        $path = $file->getRealPath();
        
        // Записываем файл с FORM + AIFF заголовком
        $aiffContent = "FORM" . pack('N', 1000) . "AIFF" . str_repeat("\x00", 1000 - 12);
        file_put_contents($path, $aiffContent);

        // Не должно быть исключения
        $validator->validate($file, 'audio/aiff');

        $this->assertTrue(true);
    }

    public function test_validates_heic_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой HEIC
        $file = UploadedFile::fake()->create('test.heic', 1000, 'image/heic');
        $path = $file->getRealPath();
        
        // Записываем файл с ftyp box для HEIC
        $heicHeader = hex2bin('000000186674797068656963000000006d696631');
        file_put_contents($path, $heicHeader . str_repeat("\x00", 1000 - strlen($heicHeader)));

        // Не должно быть исключения
        $validator->validate($file, 'image/heic');

        $this->assertTrue(true);
    }

    public function test_validates_avif_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой AVIF
        $file = UploadedFile::fake()->create('test.avif', 1000, 'image/avif');
        $path = $file->getRealPath();
        
        // Записываем файл с ftyp box для AVIF
        $avifHeader = hex2bin('0000002066747970617669660000000061766966');
        file_put_contents($path, $avifHeader . str_repeat("\x00", 1000 - strlen($avifHeader)));

        // Не должно быть исключения
        $validator->validate($file, 'image/avif');

        $this->assertTrue(true);
    }

    public function test_rejects_mismatched_mime_and_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём JPEG файл, но указываем PNG MIME
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage('MIME type mismatch');

        $validator->validate($file, 'image/png');
    }

    public function test_handles_unknown_signature_gracefully(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с неизвестной сигнатурой
        $file = UploadedFile::fake()->create('test.unknown', 1000, 'application/octet-stream');
        $path = $file->getRealPath();
        file_put_contents($path, 'unknown file format data');

        // Не должно быть исключения, так как сигнатура неизвестна
        $validator->validate($file, 'application/octet-stream');

        $this->assertTrue(true);
    }

    public function test_handles_unreadable_file(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл, который затем удаляем
        $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');
        $path = $file->getRealPath();
        unlink($path);

        $this->expectException(MediaValidationException::class);
        $this->expectExceptionMessage('Cannot read file for MIME signature validation');

        $validator->validate($file, 'image/jpeg');
    }

    public function test_validates_mp4_audio_only_signature(): void
    {
        $validator = new MimeSignatureValidator();

        // Создаём файл с правильной сигнатурой MP4 audio-only
        $file = UploadedFile::fake()->create('test.m4a', 1000, 'audio/mp4');
        $path = $file->getRealPath();
        
        // Записываем минимальный MP4 файл с ftyp box
        $mp4Header = hex2bin('000000206674797069736f6d0000020069736f6d69736f32');
        file_put_contents($path, $mp4Header . str_repeat("\x00", 1000 - strlen($mp4Header)));

        // Не должно быть исключения
        $validator->validate($file, 'audio/mp4');

        $this->assertTrue(true);
    }

    public function test_validates_ftyp_box_at_different_offsets(): void
    {
        $validator = new MimeSignatureValidator();

        // Тест для ftyp box на позиции 0
        $file1 = UploadedFile::fake()->create('test1.mp4', 1000, 'video/mp4');
        $path1 = $file1->getRealPath();
        $mp4Header1 = hex2bin('000000206674797069736f6d0000020069736f6d69736f32');
        file_put_contents($path1, $mp4Header1 . str_repeat("\x00", 1000 - strlen($mp4Header1)));
        $validator->validate($file1, 'video/mp4');

        // Тест для ftyp box на позиции 4
        $file2 = UploadedFile::fake()->create('test2.mp4', 1000, 'video/mp4');
        $path2 = $file2->getRealPath();
        $mp4Header2 = "\x00\x00\x00\x04" . hex2bin('000000206674797069736f6d0000020069736f6d69736f32');
        file_put_contents($path2, $mp4Header2 . str_repeat("\x00", 1000 - strlen($mp4Header2)));
        $validator->validate($file2, 'video/mp4');

        // Тест для ftyp box на позиции 8
        $file3 = UploadedFile::fake()->create('test3.mp4', 1000, 'video/mp4');
        $path3 = $file3->getRealPath();
        $mp4Header3 = "\x00\x00\x00\x00\x00\x00\x00\x08" . hex2bin('000000206674797069736f6d0000020069736f6d69736f32');
        file_put_contents($path3, $mp4Header3 . str_repeat("\x00", 1000 - strlen($mp4Header3)));
        $validator->validate($file3, 'video/mp4');

        $this->assertTrue(true);
    }
}

