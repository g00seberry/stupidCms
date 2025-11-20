<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doc_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->integer('array_index')->nullable();
            $table->foreignId('target_entry_id')->constrained('entries')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['entry_id', 'path_id', 'array_index'], 'uq_doc_refs_entry_path_idx');

            $table->index('path_id');
            $table->index('target_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_refs');
    }
};
