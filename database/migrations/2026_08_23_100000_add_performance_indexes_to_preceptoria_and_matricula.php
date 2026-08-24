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
        Schema::table('preceptoria', function (Blueprint $table) {
            $table->index(['data', 'hora_inicio', 'matricula_id'], 'preceptoria_data_hora_inicio_matricula_id_index');
        });

        Schema::table('matricula', function (Blueprint $table) {
            $table->index(['turma_id', 'situacao', 'pessoa_id'], 'matricula_turma_id_situacao_pessoa_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preceptoria', function (Blueprint $table) {
            $table->dropIndex('preceptoria_data_hora_inicio_matricula_id_index');
        });

        Schema::table('matricula', function (Blueprint $table) {
            $table->dropIndex('matricula_turma_id_situacao_pessoa_id_index');
        });
    }
};
