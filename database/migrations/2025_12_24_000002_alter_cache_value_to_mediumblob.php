<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache')) {
            return;
        }

        $driver = DB::getDriverName();

        // Database cache store должен уметь хранить произвольные байты (PHP serialize может содержать бинарные строки).
        // MEDIUMTEXT + utf8/utf8mb4 может падать на не-UTF8 последовательностях ("Incorrect string value").
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `cache` MODIFY `value` MEDIUMBLOB NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "cache" ALTER COLUMN "value" TYPE bytea USING convert_to("value", \'UTF8\')');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cache')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `cache` MODIFY `value` MEDIUMTEXT NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "cache" ALTER COLUMN "value" TYPE text');
        }
    }
};


