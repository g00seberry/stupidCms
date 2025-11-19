<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blueprint_components', function (Blueprint $table) {
            $table->foreignId('blueprint_id')
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->foreignId('component_id')
                ->constrained('blueprints')
                ->onDelete('cascade');
            $table->string('path_prefix', 100);
            $table->timestamps();

            $table->primary(['blueprint_id', 'component_id']);
            $table->index('component_id', 'idx_component');
        });

        // CHECK constraint для MySQL
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE blueprint_components
                ADD CONSTRAINT chk_path_prefix_not_empty
                CHECK (LENGTH(path_prefix) > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_components');
    }
};

