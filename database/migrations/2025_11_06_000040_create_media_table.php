<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mime');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
