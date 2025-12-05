<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_type_id')->constrained('post_types')->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->enum('status', ['draft','published'])->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data_json');
            $table->json('seo_json')->nullable();
            $table->string('template_override')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // Generated column to enforce uniqueness for active (non-deleted) rows
        // Only for MySQL/MariaDB - SQLite doesn't support generated columns
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `entries` ADD `is_active` TINYINT(1) AS (CASE WHEN `deleted_at` IS NULL THEN 1 ELSE 0 END) STORED");

            // Global unique constraint: slug must be unique across all entries (for active rows only)
            Schema::table('entries', function (Blueprint $table) {
                $table->unique(['slug','is_active'], 'entries_unique_active_slug');
            });

            // Trigger to enforce global uniqueness of slugs and check reserved routes
            DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_entries_slug_unique_before_ins
            BEFORE INSERT ON entries FOR EACH ROW
            BEGIN
                -- Global uniqueness: check if slug already exists for active (non-deleted) entries
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM entries e
                        WHERE e.slug = NEW.slug
                          AND e.deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate slug: slug must be globally unique for active entries';
                    END IF;
                END IF;

                -- Reserved routes: forbid slugs equal to reserved path (CI) or starting with reserved prefix (CI)
                IF EXISTS (
                    SELECT 1 FROM reserved_routes rr
                    WHERE (rr.kind = 'path' AND LOWER(rr.path) = LOWER(NEW.slug))
                       OR (rr.kind = 'prefix' AND (LOWER(NEW.slug) = LOWER(rr.path) OR LOWER(NEW.slug) LIKE CONCAT(LOWER(rr.path), '/%')))
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slug conflicts with reserved route';
                END IF;
            END
            SQL);

            DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_entries_slug_unique_before_upd
            BEFORE UPDATE ON entries FOR EACH ROW
            BEGIN
                -- Only act when slug or deleted_at changed
                IF (NEW.slug <> OLD.slug) OR (NEW.deleted_at <> OLD.deleted_at) THEN
                    -- Global uniqueness: check if slug already exists for active (non-deleted) entries
                    IF NEW.deleted_at IS NULL THEN
                        IF EXISTS (
                            SELECT 1 FROM entries e
                            WHERE e.slug = NEW.slug
                              AND e.deleted_at IS NULL
                              AND e.id <> OLD.id
                        ) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate slug: slug must be globally unique for active entries';
                        END IF;
                    END IF;

                    -- Reserved routes: forbid slugs equal to reserved path (CI) or starting with reserved prefix (CI)
                    IF EXISTS (
                        SELECT 1 FROM reserved_routes rr
                        WHERE (rr.kind = 'path' AND LOWER(rr.path) = LOWER(NEW.slug))
                           OR (rr.kind = 'prefix' AND (LOWER(NEW.slug) = LOWER(rr.path) OR LOWER(NEW.slug) LIKE CONCAT(LOWER(rr.path), '/%')))
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slug conflicts with reserved route';
                    END IF;
                END IF;
            END
            SQL);
        } else {
            // For SQLite, add a simple unique index (global uniqueness)
            Schema::table('entries', function (Blueprint $table) {
                $table->unique(['slug'], 'entries_unique_slug');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_entries_slug_unique_before_ins');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_entries_slug_unique_before_upd');
        }
        Schema::dropIfExists('entries');
    }
};
