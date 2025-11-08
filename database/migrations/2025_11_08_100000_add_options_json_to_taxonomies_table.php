<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            if (! Schema::hasColumn('taxonomies', 'options_json')) {
                $table->json('options_json')->nullable()->after('hierarchical');
            }
        });
    }

    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            if (Schema::hasColumn('taxonomies', 'options_json')) {
                $table->dropColumn('options_json');
            }
        });
    }
};

