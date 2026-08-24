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
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->index(['matricula_id', 'cronograma_aula_id'], 'frequencia_escolar_matricula_id_cronograma_aula_id_index');
        });

        Schema::table('faturas', function (Blueprint $table) {
            $table->index(['status', 'vencimento'], 'faturas_status_vencimento_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->dropIndex('frequencia_escolar_matricula_id_cronograma_aula_id_index');
        });

        Schema::table('faturas', function (Blueprint $table) {
            $table->dropIndex('faturas_status_vencimento_index');
        });
    }
};
