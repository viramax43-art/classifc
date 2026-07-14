<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okpd2_tnved_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('okpd2_code', 32)->index();
            $table->string('tnved_code', 10)->index();
            $table->string('source')->default('mineconom');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['okpd2_code', 'tnved_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okpd2_tnved_mappings');
    }
};
