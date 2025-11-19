<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_type_id')
                ->nullable()
                ->constrained('post_types')
                ->onDelete('cascade');
            $table->string('slug', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['full', 'component'])->default('full');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // MySQL-совместимый уникальный индекс
            $table->unique(['post_type_id', 'slug', 'type'], 'unique_slug_type');
            $table->index('type', 'idx_type');
            $table->index(['post_type_id', 'is_default'], 'idx_default');
            $table->index('slug', 'idx_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};

