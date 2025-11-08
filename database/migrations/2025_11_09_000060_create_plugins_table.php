<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('plugin_reserved');
        Schema::dropIfExists('plugin_migrations');
        Schema::dropIfExists('plugins');

        Schema::create('plugins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->string('version', 32);
            $table->string('provider_fqcn', 255);
            $table->string('path', 255);
            $table->boolean('enabled')->default(false)->index();
            $table->json('meta_json')->nullable();
            $table->timestampTz('last_synced_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');

        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('version');
            $table->boolean('enabled')->default(false);
            $table->json('manifest_json')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_migrations', function (Blueprint $table) {
            $table->unsignedBigInteger('plugin_id');
            $table->string('migration');
            $table->timestamp('applied_at')->useCurrent();

            $table->primary(['plugin_id', 'migration']);
            $table->foreign('plugin_id')->references('id')->on('plugins')->onDelete('cascade');
        });

        Schema::create('plugin_reserved', function (Blueprint $table) {
            $table->unsignedBigInteger('plugin_id');
            $table->string('path')->unique();
            $table->enum('kind', ['prefix', 'path'])->default('path');

            $table->foreign('plugin_id')->references('id')->on('plugins')->onDelete('cascade');
        });
    }
};

