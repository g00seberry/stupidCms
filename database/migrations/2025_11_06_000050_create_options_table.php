<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('namespace');
            $table->string('key');
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['namespace','key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
