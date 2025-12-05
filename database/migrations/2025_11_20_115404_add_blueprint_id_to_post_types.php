<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('post_types', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('options_json')
                ->constrained('blueprints')
                ->restrictOnDelete();

            $table->index('blueprint_id', 'idx_post_types_blueprint');
        });
    }

    public function down(): void
    {
        Schema::table('post_types', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropIndex('idx_post_types_blueprint');
            $table->dropColumn('blueprint_id');
        });
    }
};
