<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okpd2_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('global_id')->unique();
            $table->unsignedInteger('number')->index();
            $table->string('name');
            $table->string('idx')->index();
            $table->char('section', 1)->index();
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();
            $table->string('parent_code', 32)->nullable()->index();
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('has_children')->default(false);
            $table->timestamps();
        });

        Schema::create('okpd2_meta', function (Blueprint $table) {
            $table->id();
            $table->string('version_number')->nullable();
            $table->string('version_date')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okpd2_meta');
        Schema::dropIfExists('okpd2_items');
    }
};
