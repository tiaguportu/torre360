<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_contratos', function (Blueprint $table) {
            $table->longText('cabecalho')->nullable()->after('nome');
            $table->longText('rodape')->nullable()->after('conteudo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_contratos', function (Blueprint $table) {
            $table->dropColumn(['cabecalho', 'rodape']);
        });
    }
};
