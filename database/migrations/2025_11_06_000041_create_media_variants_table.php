<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id');
            $table->string('variant_key'); // backticks in name avoided in Laravel, use key as string
            $table->string('path')->unique();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->primary(['media_id','variant_key']);
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
