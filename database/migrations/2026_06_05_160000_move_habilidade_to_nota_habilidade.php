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
        // Alterar avaliacao_habilidades
        Schema::table('avaliacao_habilidades', function (Blueprint $table) {
            $table->dropForeign(['habilidade_id']);
            $table->dropColumn(['habilidade_id']);
        });

        // Alterar nota_habilidades
        Schema::table('nota_habilidades', function (Blueprint $table) {
            $table->foreignId('habilidade_id')->after('matricula_id')->constrained('habilidades')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter nota_habilidades
        Schema::table('nota_habilidades', function (Blueprint $table) {
            $table->dropForeign(['habilidade_id']);
            $table->dropColumn(['habilidade_id']);
        });

        // Reverter avaliacao_habilidades
        Schema::table('avaliacao_habilidades', function (Blueprint $table) {
            $table->foreignId('habilidade_id')->after('turma_id')->constrained('habilidades')->onDelete('cascade');
        });
    }
};
