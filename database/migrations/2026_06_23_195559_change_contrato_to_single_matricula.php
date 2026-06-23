<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Adicionar a coluna matricula_id em contrato se não existir
        if (! Schema::hasColumn('contrato', 'matricula_id')) {
            Schema::table('contrato', function (Blueprint $table) {
                $table->foreignId('matricula_id')->nullable()->constrained('matricula')->onDelete('set null');
            });
        }

        // 2. Migrar os dados de matricula.contrato_id para contrato.matricula_id
        if (Schema::hasColumn('matricula', 'contrato_id')) {
            $matriculas = DB::table('matricula')->whereNotNull('contrato_id')->get();
            foreach ($matriculas as $matricula) {
                DB::table('contrato')
                    ->where('id', $matricula->contrato_id)
                    ->whereNull('matricula_id') // Garante que pegamos a primeira ou única matrícula
                    ->update(['matricula_id' => $matricula->id]);
            }
        }

        // 3. Remover a coluna contrato_id de matricula se ela existir
        if (Schema::hasColumn('matricula', 'contrato_id')) {
            if (config('database.default') !== 'sqlite') {
                Schema::table('matricula', function (Blueprint $table) {
                    $table->dropForeign(['contrato_id']);
                });
            }
            Schema::table('matricula', function (Blueprint $table) {
                $table->dropColumn('contrato_id');
            });
        }

        // 4. Modificar {{ALUNOS.TABELA}} para {{ALUNO.TABELA}} no template de ID 1
        DB::table('template_contratos')
            ->where('id', 1)
            ->update([
                'conteudo' => DB::raw("REPLACE(conteudo, '{{ALUNOS.TABELA}}', '{{ALUNO.TABELA}}')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Adicionar a coluna contrato_id de volta em matricula se não existir
        if (! Schema::hasColumn('matricula', 'contrato_id')) {
            Schema::table('matricula', function (Blueprint $table) {
                $table->foreignId('contrato_id')->nullable()->constrained('contrato')->onDelete('set null');
            });
        }

        // 2. Migrar os dados de contrato.matricula_id para matricula.contrato_id
        if (Schema::hasColumn('contrato', 'matricula_id')) {
            $contratos = DB::table('contrato')->whereNotNull('matricula_id')->get();
            foreach ($contratos as $contrato) {
                DB::table('matricula')
                    ->where('id', $contrato->matricula_id)
                    ->update(['contrato_id' => $contrato->id]);
            }
        }

        // 3. Remover a coluna matricula_id de contrato se ela existir
        if (Schema::hasColumn('contrato', 'matricula_id')) {
            if (config('database.default') !== 'sqlite') {
                Schema::table('contrato', function (Blueprint $table) {
                    $table->dropForeign(['matricula_id']);
                });
            }
            Schema::table('contrato', function (Blueprint $table) {
                $table->dropColumn('matricula_id');
            });
        }

        // 4. Restaurar {{ALUNOS.TABELA}} no template de ID 1
        DB::table('template_contratos')
            ->where('id', 1)
            ->update([
                'conteudo' => DB::raw("REPLACE(conteudo, '{{ALUNO.TABELA}}', '{{ALUNOS.TABELA}}')"),
            ]);
    }
};
