<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entry_term', function (Blueprint $table) {
            if (! Schema::hasColumn('entry_term', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entry_term', function (Blueprint $table) {
            if (Schema::hasColumn('entry_term', 'created_at')) {
                $table->dropColumn(['created_at', 'updated_at']);
            }
        });
    }
};

