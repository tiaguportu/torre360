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
        // Alterar avaliacao_habilidades se habilidade_id ainda existir nela
        if (Schema::hasColumn('avaliacao_habilidades', 'habilidade_id')) {
            Schema::table('avaliacao_habilidades', function (Blueprint $table) {
                $table->dropForeign(['habilidade_id']);
                $table->dropColumn(['habilidade_id']);
            });
        }

        // Alterar nota_habilidades se habilidade_id não existir nela
        if (! Schema::hasColumn('nota_habilidades', 'habilidade_id')) {
            Schema::table('nota_habilidades', function (Blueprint $table) {
                $table->foreignId('habilidade_id')->after('matricula_id')->constrained('habilidades')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter nota_habilidades
        if (Schema::hasColumn('nota_habilidades', 'habilidade_id')) {
            Schema::table('nota_habilidades', function (Blueprint $table) {
                $table->dropForeign(['habilidade_id']);
                $table->dropColumn(['habilidade_id']);
            });
        }

        // Reverter avaliacao_habilidades
        if (! Schema::hasColumn('avaliacao_habilidades', 'habilidade_id')) {
            Schema::table('avaliacao_habilidades', function (Blueprint $table) {
                $table->foreignId('habilidade_id')->after('turma_id')->constrained('habilidades')->onDelete('cascade');
            });
        }
    }
};
