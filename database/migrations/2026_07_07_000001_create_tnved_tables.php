<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tnved_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('display_code', 16)->index();
            $table->string('name');
            $table->string('idx', 10)->index();
            $table->char('section', 2)->index();
            $table->text('description')->nullable();
            $table->text('ancestors_path')->nullable();
            $table->string('parent_code', 10)->nullable()->index();
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('has_children')->default(false);
            $table->date('date_begin')->nullable();
            $table->date('date_end')->nullable();
            $table->json('rates')->nullable();
            $table->timestamps();
        });

        Schema::create('tnved_meta', function (Blueprint $table) {
            $table->id();
            $table->string('version_number')->nullable();
            $table->string('version_date')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE VIRTUAL TABLE tnved_items_fts USING fts5(
                code,
                display_code,
                name,
                idx,
                description,
                ancestors_path,
                content="tnved_items",
                content_rowid="id"
            )');

            DB::statement('CREATE TRIGGER tnved_items_ai AFTER INSERT ON tnved_items BEGIN
                INSERT INTO tnved_items_fts(rowid, code, display_code, name, idx, description, ancestors_path)
                VALUES (new.id, new.code, new.display_code, new.name, new.idx, COALESCE(new.description, ""), COALESCE(new.ancestors_path, ""));
            END');

            DB::statement('CREATE TRIGGER tnved_items_ad AFTER DELETE ON tnved_items BEGIN
                INSERT INTO tnved_items_fts(tnved_items_fts, rowid, code, display_code, name, idx, description, ancestors_path)
                VALUES ("delete", old.id, old.code, old.display_code, old.name, old.idx, COALESCE(old.description, ""), COALESCE(old.ancestors_path, ""));
            END');

            DB::statement('CREATE TRIGGER tnved_items_au AFTER UPDATE ON tnved_items BEGIN
                INSERT INTO tnved_items_fts(tnved_items_fts, rowid, code, display_code, name, idx, description, ancestors_path)
                VALUES ("delete", old.id, old.code, old.display_code, old.name, old.idx, COALESCE(old.description, ""), COALESCE(old.ancestors_path, ""));
                INSERT INTO tnved_items_fts(rowid, code, display_code, name, idx, description, ancestors_path)
                VALUES (new.id, new.code, new.display_code, new.name, new.idx, COALESCE(new.description, ""), COALESCE(new.ancestors_path, ""));
            END');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS tnved_items_ai');
            DB::statement('DROP TRIGGER IF EXISTS tnved_items_ad');
            DB::statement('DROP TRIGGER IF EXISTS tnved_items_au');
            DB::statement('DROP TABLE IF EXISTS tnved_items_fts');
        }

        Schema::dropIfExists('tnved_meta');
        Schema::dropIfExists('tnved_items');
    }
};
