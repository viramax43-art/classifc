<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('okpd2_items', function (Blueprint $table) {
            $table->text('ancestors_path')->nullable()->after('description');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE VIRTUAL TABLE okpd2_items_fts USING fts5(
                code,
                name,
                idx,
                description,
                ancestors_path,
                content="okpd2_items",
                content_rowid="id"
            )');

            DB::statement('CREATE TRIGGER okpd2_items_ai AFTER INSERT ON okpd2_items BEGIN
                INSERT INTO okpd2_items_fts(rowid, code, name, idx, description, ancestors_path)
                VALUES (new.id, new.code, new.name, new.idx, COALESCE(new.description, ""), COALESCE(new.ancestors_path, ""));
            END');

            DB::statement('CREATE TRIGGER okpd2_items_ad AFTER DELETE ON okpd2_items BEGIN
                INSERT INTO okpd2_items_fts(okpd2_items_fts, rowid, code, name, idx, description, ancestors_path)
                VALUES ("delete", old.id, old.code, old.name, old.idx, COALESCE(old.description, ""), COALESCE(old.ancestors_path, ""));
            END');

            DB::statement('CREATE TRIGGER okpd2_items_au AFTER UPDATE ON okpd2_items BEGIN
                INSERT INTO okpd2_items_fts(okpd2_items_fts, rowid, code, name, idx, description, ancestors_path)
                VALUES ("delete", old.id, old.code, old.name, old.idx, COALESCE(old.description, ""), COALESCE(old.ancestors_path, ""));
                INSERT INTO okpd2_items_fts(rowid, code, name, idx, description, ancestors_path)
                VALUES (new.id, new.code, new.name, new.idx, COALESCE(new.description, ""), COALESCE(new.ancestors_path, ""));
            END');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS okpd2_items_ai');
            DB::statement('DROP TRIGGER IF EXISTS okpd2_items_ad');
            DB::statement('DROP TRIGGER IF EXISTS okpd2_items_au');
            DB::statement('DROP TABLE IF EXISTS okpd2_items_fts');
        }

        Schema::table('okpd2_items', function (Blueprint $table) {
            $table->dropColumn('ancestors_path');
        });
    }
};
