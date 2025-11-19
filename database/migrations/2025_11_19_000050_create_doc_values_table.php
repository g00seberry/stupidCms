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
        Schema::create('doc_values', function (Blueprint $table) {
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->onDelete('cascade');
            $table->foreignId('path_id')
                ->constrained('paths')
                ->onDelete('cascade');
            $table->unsignedInteger('idx')->default(0);

            $table->string('value_string', 500)->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->primary(['entry_id', 'path_id', 'idx']);
            $table->index(['entry_id', 'path_id'], 'idx_entry_path');
            $table->index(['path_id', 'value_string'], 'idx_path_string');
            $table->index(['path_id', 'value_int'], 'idx_path_int');
            $table->index(['path_id', 'value_float'], 'idx_path_float');
            $table->index(['path_id', 'value_bool'], 'idx_path_bool');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_values');
    }
};

