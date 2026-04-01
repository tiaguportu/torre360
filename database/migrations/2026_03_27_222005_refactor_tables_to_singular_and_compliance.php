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
        // Renomear tabelas de plural para singular conformando com a matriz
        $renames = [
            'paises' => 'pais',
            'estados' => 'estado',
            'cidades' => 'cidade',
            'enderecos' => 'endereco',
            'sexos' => 'sexo',
            'cor_racas' => 'cor_raca',
            'pessoas' => 'pessoa',
            'unidades' => 'unidade',
            'cursos' => 'curso',
            'documento_obrigatorios' => 'documento_obrigatorio',
            'series' => 'serie',
            'turnos' => 'turno',
            'turmas' => 'turma',
            'situacao_matriculas' => 'situacao_matricula',
            'matriculas' => 'matricula',
            'area_conhecimentos' => 'area_conhecimento',
            'disciplinas' => 'disciplina',
            'contratos' => 'contrato',
            'responsavel_financeiros' => 'responsavel_financeiro', // se existir
            'titulos' => 'titulo',
            'cronograma_aulas' => 'cronograma_aula',
            'coordenadores' => 'coordenador',
            'tributacao_cursos' => 'tributacao_curso',
            'habilidades' => 'habilidade',
            'etapas' => 'etapa_avaliativa',
            'data_avaliacaos' => 'avaliacao',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        // Criar tabelas faltantes secundárias na hierarquia
        if (!Schema::hasTable('perfil')) {
            Schema::create('perfil', function (Blueprint $table) {
                $table->id();
                $table->string('nome'); // Aluno, Professor, Responsável, Coordenador
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pessoa_perfil')) {
            Schema::create('pessoa_perfil', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pessoa_id')->constrained('pessoa')->onDelete('cascade');
                $table->foreignId('perfil_id')->constrained('perfil')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('periodo_letivo')) {
            Schema::create('periodo_letivo', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->date('data_inicio');
                $table->date('data_fim');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dia_nao_letivo')) {
            Schema::create('dia_nao_letivo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('periodo_letivo_id')->constrained('periodo_letivo')->onDelete('cascade');
                $table->foreignId('curso_id')->nullable()->constrained('curso')->onDelete('cascade');
                $table->date('data');
                $table->string('descricao');
                $table->boolean('flag_ativo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nota')) {
            Schema::create('nota', function (Blueprint $table) {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacao')->onDelete('cascade');
                $table->foreignId('matricula_id')->constrained('matricula')->onDelete('cascade');
                $table->decimal('valor', 5, 2);
                $table->timestamps();
            });
        }

        // Adicionar campos estruturais faltantes
        if (Schema::hasTable('turma') && !Schema::hasColumn('turma', 'periodo_letivo_id')) {
            Schema::table('turma', function (Blueprint $table) {
                $table->foreignId('periodo_letivo_id')->nullable()->constrained('periodo_letivo')->onDelete('set null');
            });
        }

        if (Schema::hasTable('etapa_avaliativa') && !Schema::hasColumn('etapa_avaliativa', 'periodo_letivo_id')) {
            Schema::table('etapa_avaliativa', function (Blueprint $table) {
                $table->foreignId('periodo_letivo_id')->nullable()->constrained('periodo_letivo')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Implementação do rollback se necessário (visto que é uma reestruturação profunda)
    }
};
