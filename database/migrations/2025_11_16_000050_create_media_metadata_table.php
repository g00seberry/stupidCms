<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_metadata', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('media_id')->unique();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedInteger('bitrate_kbps')->nullable();
            $table->decimal('frame_rate', 10, 4)->nullable();
            $table->unsignedBigInteger('frame_count')->nullable();
            $table->string('video_codec', 64)->nullable();
            $table->string('audio_codec', 64)->nullable();
            $table->timestamps();

            $table->foreign('media_id')
                ->references('id')
                ->on('media')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_metadata');
    }
};


