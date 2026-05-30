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
        // Limpar duplicatas de avaliações existentes de forma dinâmica para evitar falhas na constraint única
        $duplicatas = DB::table('avaliacao')
            ->select('turma_id', 'disciplina_id', 'etapa_avaliativa_id', 'categoria_avaliacao_id', 'professor_id', DB::raw('count(*) as qtd'))
            ->groupBy('turma_id', 'disciplina_id', 'etapa_avaliativa_id', 'categoria_avaliacao_id', 'professor_id')
            ->having('qtd', '>', 1)
            ->get();

        foreach ($duplicatas as $dup) {
            $query = DB::table('avaliacao')
                ->where('turma_id', $dup->turma_id)
                ->where('disciplina_id', $dup->disciplina_id)
                ->where('etapa_avaliativa_id', $dup->etapa_avaliativa_id)
                ->where('categoria_avaliacao_id', $dup->categoria_avaliacao_id);

            if (is_null($dup->professor_id)) {
                $query->whereNull('professor_id');
            } else {
                $query->where('professor_id', $dup->professor_id);
            }

            // Ordena pelo menor ID e mantêm apenas o primeiro, removendo os demais
            $todosIds = $query->orderBy('id', 'asc')->pluck('id');
            $idsParaRemover = $todosIds->slice(1);

            if ($idsParaRemover->isNotEmpty()) {
                // Remove notas vinculadas a essas avaliações duplicadas
                DB::table('nota')->whereIn('avaliacao_id', $idsParaRemover)->delete();
                // Remove as avaliações duplicadas
                DB::table('avaliacao')->whereIn('id', $idsParaRemover)->delete();
            }
        }

        Schema::table('avaliacao', function (Blueprint $table) {
            $table->unique([
                'turma_id',
                'disciplina_id',
                'etapa_avaliativa_id',
                'categoria_avaliacao_id',
                'professor_id',
            ], 'avaliacao_composite_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avaliacao', function (Blueprint $table) {
            $table->dropUnique('avaliacao_composite_unique');
        });
    }
};
