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
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('post_type_id')
                ->constrained('blueprints')
                ->onDelete('set null');

            $table->index('blueprint_id', 'idx_blueprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropIndex('idx_blueprint');
            $table->dropColumn('blueprint_id');
        });
    }
};

