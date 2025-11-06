<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_slugs', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_id');
            $table->string('slug');
            $table->boolean('is_current')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['entry_id','slug']);
            $table->foreign('entry_id')->references('id')->on('entries')->onDelete('cascade');
            $table->index(['entry_id','is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_slugs');
    }
};
