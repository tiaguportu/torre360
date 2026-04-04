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
        Schema::table('documento_obrigatorio', function (Blueprint $table) {
            $table->string('modelo_arquivo')->nullable();
            $table->string('modelo_link')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_obrigatorio', function (Blueprint $table) {
            $table->dropColumn(['modelo_arquivo', 'modelo_link']);
        });
    }
};
