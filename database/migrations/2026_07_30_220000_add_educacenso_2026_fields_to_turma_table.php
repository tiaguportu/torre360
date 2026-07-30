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
        Schema::table('turma', function (Blueprint $table) {
            if (! Schema::hasColumn('turma', 'forma_organizacao')) {
                $table->unsignedTinyInteger('forma_organizacao')->nullable()->after('local_funcionamento_diferenciado');
            }
            if (! Schema::hasColumn('turma', 'modalidade_ensino')) {
                $table->unsignedTinyInteger('modalidade_ensino')->nullable()->after('forma_organizacao');
            }
            if (! Schema::hasColumn('turma', 'tipo_lingua_ministrada')) {
                $table->unsignedTinyInteger('tipo_lingua_ministrada')->default(1)->after('modalidade_ensino');
            }
            if (! Schema::hasColumn('turma', 'codigo_lingua_indigena')) {
                $table->string('codigo_lingua_indigena', 10)->nullable()->after('tipo_lingua_ministrada');
            }
            if (! Schema::hasColumn('turma', 'turma_educacao_bilingue_surdos')) {
                $table->boolean('turma_educacao_bilingue_surdos')->default(false)->after('codigo_lingua_indigena');
            }

            // Flags AEE
            if (! Schema::hasColumn('turma', 'flag_aee_ensino_libras')) {
                $table->boolean('flag_aee_ensino_libras')->default(false)->after('turma_educacao_bilingue_surdos');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_ensino_soroba')) {
                $table->boolean('flag_aee_ensino_soroba')->default(false)->after('flag_aee_ensino_libras');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_ensino_informatica_acessivel')) {
                $table->boolean('flag_aee_ensino_informatica_acessivel')->default(false)->after('flag_aee_ensino_soroba');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_ensino_caa')) {
                $table->boolean('flag_aee_ensino_caa')->default(false)->after('flag_aee_ensino_informatica_acessivel');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_tecnologia_assistiva')) {
                $table->boolean('flag_aee_tecnologia_assistiva')->default(false)->after('flag_aee_ensino_caa');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_processos_cognitivos')) {
                $table->boolean('flag_aee_processos_cognitivos')->default(false)->after('flag_aee_tecnologia_assistiva');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_enriquecimento_curricular')) {
                $table->boolean('flag_aee_enriquecimento_curricular')->default(false)->after('flag_aee_processos_cognitivos');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_portugues_segunda_lingua')) {
                $table->boolean('flag_aee_portugues_segunda_lingua')->default(false)->after('flag_aee_enriquecimento_curricular');
            }
            if (! Schema::hasColumn('turma', 'flag_aee_orientacao_mobilidade')) {
                $table->boolean('flag_aee_orientacao_mobilidade')->default(false)->after('flag_aee_portugues_segunda_lingua');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma', function (Blueprint $table) {
            $columnsToDrop = [
                'forma_organizacao',
                'modalidade_ensino',
                'tipo_lingua_ministrada',
                'codigo_lingua_indigena',
                'turma_educacao_bilingue_surdos',
                'flag_aee_ensino_libras',
                'flag_aee_ensino_soroba',
                'flag_aee_ensino_informatica_acessivel',
                'flag_aee_ensino_caa',
                'flag_aee_tecnologia_assistiva',
                'flag_aee_processos_cognitivos',
                'flag_aee_enriquecimento_curricular',
                'flag_aee_portugues_segunda_lingua',
                'flag_aee_orientacao_mobilidade',
            ];

            $toDrop = [];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('turma', $col)) {
                    $toDrop[] = $col;
                }
            }

            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
