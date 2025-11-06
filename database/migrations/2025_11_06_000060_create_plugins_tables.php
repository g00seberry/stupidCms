<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('version');
            $table->boolean('enabled')->default(false);
            $table->json('manifest_json');
            $table->timestamps();
        });

        Schema::create('plugin_migrations', function (Blueprint $table) {
            $table->unsignedBigInteger('plugin_id');
            $table->string('migration');
            $table->timestamp('applied_at')->useCurrent();

            $table->primary(['plugin_id','migration']);
            $table->foreign('plugin_id')->references('id')->on('plugins')->onDelete('cascade');
        });

        Schema::create('plugin_reserved', function (Blueprint $table) {
            $table->unsignedBigInteger('plugin_id');
            $table->string('path');
            $table->enum('kind', ['prefix','path'])->default('path');

            $table->unique('path');
            $table->foreign('plugin_id')->references('id')->on('plugins')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_reserved');
        Schema::dropIfExists('plugin_migrations');
        Schema::dropIfExists('plugins');
    }
};
