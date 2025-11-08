<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('disk', 32);
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('ext', 16)->nullable();
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->json('exif_json')->nullable();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->string('collection', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mime');
            $table->index('collection');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
