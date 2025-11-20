<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->foreign('blueprint_embed_id', 'fk_paths_blueprint_embed')
                ->references('id')
                ->on('blueprint_embeds')
                ->cascadeOnDelete();

            $table->index('blueprint_embed_id', 'idx_paths_embed');
        });
    }

    public function down(): void
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->dropForeign('fk_paths_blueprint_embed');
            $table->dropIndex('idx_paths_embed');
        });
    }
};
