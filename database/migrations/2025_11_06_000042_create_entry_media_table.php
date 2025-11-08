<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_media', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignUlid('media_id')->constrained('media')->restrictOnDelete();
            $table->string('field_key');
            $table->unsignedInteger('order')->default(0);

            $table->primary(['entry_id', 'media_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_media');
    }
};
