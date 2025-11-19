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
        Schema::create('paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')
                ->constrained('blueprints')
                ->onDelete('cascade');

            // Для материализованных Paths
            $table->foreignId('source_component_id')
                ->nullable()
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->foreignId('source_path_id')
                ->nullable()
                ->constrained('paths')
                ->onDelete('cascade');

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('paths')
                ->onDelete('cascade');

            $table->string('name', 100);
            $table->string('full_path', 500);
            $table->enum('data_type', [
                'string', 'int', 'float', 'bool', 'text', 'json', 'ref'
            ]);
            $table->enum('cardinality', ['one', 'many'])->default('one');
            $table->boolean('is_indexed')->default(true);
            $table->boolean('is_required')->default(false);
            $table->string('ref_target_type', 100)->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('ui_options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blueprint_id', 'full_path'], 'unique_path_per_blueprint');
            $table->index(['blueprint_id', 'is_indexed'], 'idx_indexed');
            $table->index(['source_component_id', 'source_path_id'], 'idx_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paths');
    }
};

