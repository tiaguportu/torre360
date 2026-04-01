<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // disciplinas
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('area_conhecimentos')->nullOnDelete();
            $table->string('nome');
            $table->string('sigla')->nullable();
            $table->boolean('flag_matricula_automatica')->default(true);
            $table->integer('carga_horaria_semanal')->default(0);
        });

        // area_conhecimentos
        Schema::table('area_conhecimentos', function (Blueprint $table) {
            $table->string('nome');
        });

        // situacao_matriculas
        Schema::table('situacao_matriculas', function (Blueprint $table) {
            $table->string('nome');
        });

        // cronograma_aulas
        Schema::table('cronograma_aulas', function (Blueprint $table) {
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->nullOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->date('data')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->text('conteudo_ministrado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disciplinas', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn(['area_id', 'nome', 'sigla', 'flag_matricula_automatica', 'carga_horaria_semanal']);
        });

        Schema::table('area_conhecimentos', function (Blueprint $table) {
            $table->dropColumn('nome');
        });

        Schema::table('situacao_matriculas', function (Blueprint $table) {
            $table->dropColumn('nome');
        });

        Schema::table('cronograma_aulas', function (Blueprint $table) {
            $table->dropForeign(['turma_id']);
            $table->dropForeign(['disciplina_id']);
            $table->dropForeign(['pessoa_id']);
            $table->dropColumn(['turma_id', 'disciplina_id', 'pessoa_id', 'data', 'hora_inicio', 'hora_fim', 'conteudo_ministrado']);
        });
    }
};
