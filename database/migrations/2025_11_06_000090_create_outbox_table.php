<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->json('payload_json');
            $table->enum('status', ['pending','sent','failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index(['status','available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
