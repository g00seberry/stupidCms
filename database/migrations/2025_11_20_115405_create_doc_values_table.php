<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doc_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->integer('array_index')->nullable();

            // Скалярные значения (по типу поля)
            $table->string('value_string', 500)->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamps();

            $table->unique(['entry_id', 'path_id', 'array_index'], 'uq_doc_values_entry_path_idx');

            // Индексы для быстрых запросов
            $table->index('path_id');
            $table->index('value_string');
            $table->index('value_int');
            $table->index('value_float');
            $table->index('value_bool');
            $table->index('value_date');
            $table->index('value_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_values');
    }
};
