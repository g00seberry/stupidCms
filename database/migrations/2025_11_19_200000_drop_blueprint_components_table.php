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
        Schema::dropIfExists('blueprint_components');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('blueprint_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blueprint_id');
            $table->unsignedBigInteger('component_id');
            $table->string('path_prefix', 100);
            $table->timestamps();

            $table->unique(['blueprint_id', 'component_id']);
            $table->unique(['blueprint_id', 'path_prefix']);

            $table->foreign('blueprint_id')
                ->references('id')->on('blueprints')
                ->onDelete('cascade');
            $table->foreign('component_id')
                ->references('id')->on('blueprints')
                ->onDelete('cascade');
        });
    }
};

