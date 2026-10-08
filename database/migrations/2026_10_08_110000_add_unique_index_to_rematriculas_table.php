<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDICE = 'rematriculas_campanha_matricula_origem_unique';

    /**
     * Uma matrícula só pode ter uma rematrícula por campanha. O `firstOrCreate` do serviço não era
     * atômico: dois cliques (ou Portal + secretaria) criavam duas linhas e, depois, duas matrículas
     * e dois contratos para o mesmo aluno.
     *
     * Se já existirem duplicatas a migration NÃO derruba o deploy: registra um aviso no log e deixa
     * o índice para depois de a secretaria resolver as linhas repetidas.
     */
    public function up(): void
    {
        $duplicadas = DB::table('rematriculas')
            ->select('periodo_rematricula_id', 'matricula_origem_id')
            ->groupBy('periodo_rematricula_id', 'matricula_origem_id')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicadas > 0) {
            Log::warning("rematriculas: {$duplicadas} par(es) (campanha, matrícula de origem) duplicado(s); índice único não criado. Remova as linhas repetidas e rode a migration novamente.");

            return;
        }

        Schema::table('rematriculas', function (Blueprint $table) {
            $table->unique(['periodo_rematricula_id', 'matricula_origem_id'], self::INDICE);
        });
    }

    public function down(): void
    {
        try {
            Schema::table('rematriculas', function (Blueprint $table) {
                $table->dropUnique(self::INDICE);
            });
        } catch (Throwable) {
            // Índice não existia (criação foi pulada por duplicatas).
        }
    }
};
