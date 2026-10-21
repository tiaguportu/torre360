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
        // Limpar eventuais duplicatas existentes antes de criar o índice único
        $duplicatas = DB::table('nota')
            ->select('matricula_id', 'avaliacao_id', DB::raw('count(*) as qtd'))
            ->groupBy('matricula_id', 'avaliacao_id')
            ->having('qtd', '>', 1)
            ->get();

        foreach ($duplicatas as $dup) {
            $todosIds = DB::table('nota')
                ->where('matricula_id', $dup->matricula_id)
                ->where('avaliacao_id', $dup->avaliacao_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id');

            // Mantém o registro mais recente e remove os duplicados mais antigos
            $idsParaRemover = $todosIds->slice(1);
            if ($idsParaRemover->isNotEmpty()) {
                DB::table('nota')->whereIn('id', $idsParaRemover)->delete();
            }
        }

        Schema::table('nota', function (Blueprint $table) {
            $table->unique(['matricula_id', 'avaliacao_id'], 'nota_matricula_avaliacao_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota', function (Blueprint $table) {
            $table->dropUnique('nota_matricula_avaliacao_unique');
        });
    }
};
