<?php

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
        Schema::create('route_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique()->comment('Канонический путь в нижнем регистре');
            $table->string('source', 100)->comment('Источник резервирования (system:name, plugin:name, module:name)');
            $table->string('reason', 255)->nullable()->comment('Необязательное описание причины резервирования');
            $table->timestamps();

            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_reservations');
    }
};
