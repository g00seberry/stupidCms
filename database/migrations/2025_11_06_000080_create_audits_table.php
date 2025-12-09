<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('diff_json')->nullable();
            $table->json('meta')->nullable()->comment('Additional metadata for security events');
            $table->string('ip', 45)->nullable();
            $table->string('ua')->nullable();
            $table->timestamps();

            $table->index(['subject_type','subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
