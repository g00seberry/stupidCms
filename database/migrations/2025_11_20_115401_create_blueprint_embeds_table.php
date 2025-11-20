<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blueprint_embeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embedded_blueprint_id')
                ->constrained('blueprints')->restrictOnDelete();
            $table->foreignId('host_path_id')->nullable()
                ->constrained('paths')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['blueprint_id', 'embedded_blueprint_id', 'host_path_id'],
                'uq_blueprint_embed'
            );
            $table->index('embedded_blueprint_id', 'idx_embeds_embedded');
            $table->index('blueprint_id', 'idx_embeds_blueprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_embeds');
    }
};
