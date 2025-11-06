<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('term_tree', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');

            $table->primary(['ancestor_id','descendant_id']);
            $table->foreign('ancestor_id')->references('id')->on('terms')->onDelete('cascade');
            $table->foreign('descendant_id')->references('id')->on('terms')->onDelete('cascade');

            $table->index('descendant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_tree');
    }
};
