<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('namespace', 64);
            $table->string('key', 64);
            $table->json('value_json');
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['namespace', 'key']);
            $table->index('namespace');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
