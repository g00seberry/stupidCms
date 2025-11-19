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
        Schema::create('doc_refs', function (Blueprint $table) {
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->onDelete('cascade');
            $table->foreignId('path_id')
                ->constrained('paths')
                ->onDelete('cascade');
            $table->unsignedInteger('idx')->default(0);
            $table->foreignId('target_entry_id')
                ->constrained('entries')
                ->onDelete('cascade');

            $table->timestamp('created_at')->nullable();

            $table->primary(['entry_id', 'path_id', 'idx']);
            $table->index(['entry_id', 'path_id'], 'idx_ref_entry_path');
            $table->index(['path_id', 'target_entry_id'], 'idx_ref_path_target');
            $table->index('target_entry_id', 'idx_ref_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_refs');
    }
};

