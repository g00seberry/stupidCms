<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_media', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('media_id');
            $table->string('field_key');

            $table->primary(['entry_id','media_id','field_key']);
            $table->foreign('entry_id')->references('id')->on('entries')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_media');
    }
};
