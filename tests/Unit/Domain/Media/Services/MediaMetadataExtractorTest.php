<?php

declare(strict_types=1);

use App\Domain\Media\DTO\MediaMetadataDTO;
use App\Domain\Media\Images\GdImageProcessor;
use App\Domain\Media\Services\MediaMetadataExtractor;
use App\Domain\Media\Services\MediaMetadataPlugin;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->imageProcessor = new GdImageProcessor();
    $this->plugins = [];
    $this->cache = null;
    $this->extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        $this->plugins,
        $this->cache
    );
});

afterEach(function () {
    Mockery::close();
});

test('extracts image dimensions from jpeg file', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 150);

    $metadata = $this->extractor->extract($file);

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->width)->toBe(200)
        ->and($metadata->height)->toBe(150);
});

test('extracts image dimensions from png file', function () {
    $file = UploadedFile::fake()->image('test.png', 300, 200);

    $metadata = $this->extractor->extract($file);

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->width)->toBe(300)
        ->and($metadata->height)->toBe(200);
});

test('returns null dimensions for non-image files', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $metadata = $this->extractor->extract($file);

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->width)->toBeNull()
        ->and($metadata->height)->toBeNull();
});

test('uses provided mime type instead of auto-detection', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    $metadata = $this->extractor->extract($file, 'image/png');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class);
});

test('tries plugins in order for video files', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin1 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin1->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(false);
    
    $plugin2 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin2->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin2->shouldReceive('extract')
        ->once()
        ->andReturn(['duration_ms' => 5000]);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin1, $plugin2],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->durationMs)->toBe(5000);
});

test('skips plugins that do not support mime type', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin = Mockery::mock(MediaMetadataPlugin::class);
    $plugin->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(false);
    $plugin->shouldNotReceive('extract');
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->durationMs)->toBeNull();
});

test('handles plugin errors gracefully', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin1 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin1->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin1->shouldReceive('extract')
        ->once()
        ->andThrow(new \Exception('Plugin error'));
    
    $plugin2 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin2->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin2->shouldReceive('extract')
        ->once()
        ->andReturn(['duration_ms' => 10000]);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin1, $plugin2],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->durationMs)->toBe(10000);
});

test('skips non-plugin objects in plugins list', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $notAPlugin = new \stdClass();
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$notAPlugin],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class);
});

test('uses cache when available', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    
    $cachedDto = new MediaMetadataDTO(
        width: 200,
        height: 150,
        durationMs: null,
        exif: null,
        bitrateKbps: null,
        frameRate: null,
        frameCount: null,
        videoCodec: null,
        audioCodec: null
    );
    
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn($cachedDto);
    $cache->shouldNotReceive('put');
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [],
        $cache
    );

    $metadata = $extractor->extract($file);

    expect($metadata)->toBe($cachedDto);
});

test('stores result in cache after extraction', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn(null);
    $cache->shouldReceive('put')
        ->once()
        ->with(Mockery::type('string'), Mockery::type(MediaMetadataDTO::class), 3600);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [],
        $cache
    );

    $metadata = $extractor->extract($file);

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class);
});

test('uses custom cache ttl when provided', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn(null);
    $cache->shouldReceive('put')
        ->once()
        ->with(Mockery::type('string'), Mockery::type(MediaMetadataDTO::class), 7200);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [],
        $cache,
        7200
    );

    $metadata = $extractor->extract($file);

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class);
});

test('extracts plugin data correctly', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin = Mockery::mock(MediaMetadataPlugin::class);
    $plugin->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin->shouldReceive('extract')
        ->once()
        ->andReturn([
            'duration_ms' => 5000,
            'bitrate_kbps' => 1000,
            'frame_rate' => 30.0,
            'frame_count' => 150,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ]);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata)->toBeInstanceOf(MediaMetadataDTO::class)
        ->and($metadata->durationMs)->toBe(5000)
        ->and($metadata->bitrateKbps)->toBe(1000)
        ->and($metadata->frameRate)->toBe(30.0)
        ->and($metadata->frameCount)->toBe(150)
        ->and($metadata->videoCodec)->toBe('h264')
        ->and($metadata->audioCodec)->toBe('aac');
});

test('stops on first plugin that returns data', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin1 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin1->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin1->shouldReceive('extract')
        ->once()
        ->andReturn(['duration_ms' => 5000]);
    
    $plugin2 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin2->shouldNotReceive('supports');
    $plugin2->shouldNotReceive('extract');
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin1, $plugin2],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata->durationMs)->toBe(5000);
});

test('continues to next plugin when plugin returns empty data', function () {
    $file = UploadedFile::fake()->create('video.mp4', 100);
    
    $plugin1 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin1->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin1->shouldReceive('extract')
        ->once()
        ->andReturn([]);
    
    $plugin2 = Mockery::mock(MediaMetadataPlugin::class);
    $plugin2->shouldReceive('supports')
        ->once()
        ->with('video/mp4')
        ->andReturn(true);
    $plugin2->shouldReceive('extract')
        ->once()
        ->andReturn(['duration_ms' => 10000]);
    
    $extractor = new MediaMetadataExtractor(
        $this->imageProcessor,
        [$plugin1, $plugin2],
        null
    );

    $metadata = $extractor->extract($file, 'video/mp4');

    expect($metadata->durationMs)->toBe(10000);
});

