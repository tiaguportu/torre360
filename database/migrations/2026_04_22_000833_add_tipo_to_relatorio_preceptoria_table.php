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
        Schema::table('relatorio_preceptoria', function (Blueprint $table) {
            $table->string('tipo')->default('Analise Geral')->after('preceptoria_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('relatorio_preceptoria', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
