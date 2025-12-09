<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('variant', 32);
            $table->string('path')->unique();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 16)->default('ready')->index();
            $table->string('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
