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
        Schema::table('dia_nao_letivo', function (Blueprint $table) {
            $table->dropForeign(['curso_id']);
            $table->dropColumn('curso_id');
        });

        Schema::table('etapa_avaliativa', function (Blueprint $table) {
            $table->dropForeign(['turma_id']);
            $table->dropColumn('turma_id');
        });

        Schema::table('avaliacao', function (Blueprint $table) {
            // Se já existirem, adicionamos com controle
            if (!Schema::hasColumn('avaliacao', 'disciplina_id')) {
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->onDelete('cascade');
            }
            if (!Schema::hasColumn('avaliacao', 'turma_id')) {
                $table->foreignId('turma_id')->nullable()->constrained('turma')->onDelete('cascade');
            }
            if (!Schema::hasColumn('avaliacao', 'data_prevista')) {
                $table->date('data_prevista')->nullable();
            }
            if (!Schema::hasColumn('avaliacao', 'nota_maxima')) {
                $table->decimal('nota_maxima', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('avaliacao', 'peso_etapa_avaliativa')) {
                $table->decimal('peso_etapa_avaliativa', 5, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dia_nao_letivo', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->constrained('curso')->onDelete('cascade');
        });

        Schema::table('etapa_avaliativa', function (Blueprint $table) {
            $table->foreignId('turma_id')->nullable()->constrained('turma')->onDelete('cascade');
        });

        Schema::table('avaliacao', function (Blueprint $table) {
            $table->dropForeign(['disciplina_id']);
            $table->dropForeign(['turma_id']);
            $table->dropColumn(['disciplina_id', 'turma_id', 'data_prevista', 'nota_maxima', 'peso_etapa_avaliativa']);
        });
    }
};
