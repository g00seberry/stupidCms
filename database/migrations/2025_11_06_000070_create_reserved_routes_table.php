<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reserved_routes', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->enum('kind', ['prefix','path'])->default('path');
            $table->enum('source', ['core','plugin'])->default('core');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_routes');
    }
};
