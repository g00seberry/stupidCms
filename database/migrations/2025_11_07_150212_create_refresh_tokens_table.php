<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('jti', 36)->unique()->comment('JWT ID from claims');
            $table->string('kid', 20)->comment('Key ID used for signing');
            $table->dateTime('expires_at')->comment('Token expiration time in UTC');
            $table->dateTime('used_at')->nullable()->comment('One-time use timestamp');
            $table->dateTime('revoked_at')->nullable()->comment('Revocation timestamp (logout/admin)');
            $table->char('parent_jti', 36)->nullable()->comment('Parent token JTI in refresh chain');
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
            $table->index(['used_at', 'revoked_at']);
            $table->index('parent_jti');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
