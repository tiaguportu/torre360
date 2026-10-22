<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos que o Inep atribui na matrícula inicial do Censo Escolar e que a 2ª etapa (Situação do Aluno) exige:
 * ID Inep do aluno (12 dígitos), código Inep da turma (até 10) e código da matrícula (até 12). Chegam pelo arquivo
 * de exportação do Educacenso (retorno) ou pelo resultado da identificação de alunos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pessoa', 'codigo_inep')) {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->string('codigo_inep', 12)->nullable()->after('cpf');
                $table->index('codigo_inep', 'idx_pessoa_codigo_inep');
            });
        }

        if (! Schema::hasColumn('turma', 'codigo_inep')) {
            Schema::table('turma', function (Blueprint $table) {
                $table->string('codigo_inep', 10)->nullable()->after('codigo');
            });
        }

        if (! Schema::hasColumn('matricula', 'codigo_matricula_inep')) {
            Schema::table('matricula', function (Blueprint $table) {
                $table->string('codigo_matricula_inep', 12)->nullable()->after('turma_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matricula', 'codigo_matricula_inep')) {
            Schema::table('matricula', function (Blueprint $table) {
                $table->dropColumn('codigo_matricula_inep');
            });
        }

        if (Schema::hasColumn('turma', 'codigo_inep')) {
            Schema::table('turma', function (Blueprint $table) {
                $table->dropColumn('codigo_inep');
            });
        }

        if (Schema::hasColumn('pessoa', 'codigo_inep')) {
            Schema::table('pessoa', function (Blueprint $table) {
                $table->dropIndex('idx_pessoa_codigo_inep');
                $table->dropColumn('codigo_inep');
            });
        }
    }
};
