<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_term', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('term_id');
            $table->primary(['entry_id','term_id']);
            $table->foreign('entry_id')->references('id')->on('entries')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_term');
    }
};
