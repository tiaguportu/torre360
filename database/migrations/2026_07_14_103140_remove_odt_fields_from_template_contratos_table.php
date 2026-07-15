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
        Schema::table('template_contratos', function (Blueprint $table) {
            $table->dropColumn(['versao', 'arquivo_odt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_contratos', function (Blueprint $table) {
            $table->tinyInteger('versao')->default(1)->after('nome');
            $table->string('arquivo_odt')->nullable()->after('conteudo');
        });
    }
};
