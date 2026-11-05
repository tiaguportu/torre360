<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Um aluno tem no máximo um registro de frequência por aula.
 *
 * Até aqui a regra vivia só no código ("ler e criar se não existir"), que não é atômica: duas abas, dois professores ou
 * um recarregamento da chamada criavam duas linhas para o mesmo aluno e a mesma aula, e a contagem de presenças e faltas
 * (`count(*)`) passava a contar o aluno em dobro. A tabela `nota` ganhou o mesmo índice pelo mesmo motivo.
 *
 * As duplicatas existentes saem antes do índice. Fica a linha mais informativa: a que tem situação lançada, depois a que
 * carrega justificativa, abono ou atestado, depois a mais recente.
 */
return new class extends Migration
{
    private const INDICE = 'frequencia_escolar_matricula_aula_unique';

    public function up(): void
    {
        $removidas = $this->removerDuplicatas();

        if ($removidas->isNotEmpty()) {
            Log::warning('frequencia_escolar: registros duplicados removidos antes do índice único.', [
                'quantidade' => $removidas->count(),
                'ids' => $removidas->all(),
            ]);
        }

        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->unique(['matricula_id', 'cronograma_aula_id'], self::INDICE);
        });
    }

    public function down(): void
    {
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->dropUnique(self::INDICE);
        });
    }

    /**
     * @return Collection<int, int> ids das linhas apagadas
     */
    private function removerDuplicatas(): Collection
    {
        $removidas = collect();

        $grupos = DB::table('frequencia_escolar')
            ->select('matricula_id', 'cronograma_aula_id')
            ->groupBy('matricula_id', 'cronograma_aula_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            $linhas = DB::table('frequencia_escolar')
                ->where('matricula_id', $grupo->matricula_id)
                ->where('cronograma_aula_id', $grupo->cronograma_aula_id)
                ->get();

            $ordenadas = $linhas->sort(fn (object $a, object $b): int => $this->peso($b) <=> $this->peso($a))->values();

            $apagar = $ordenadas->slice(1)->pluck('id');

            DB::table('frequencia_escolar')->whereIn('id', $apagar->all())->delete();

            $removidas = $removidas->merge($apagar);
        }

        return $removidas;
    }

    /**
     * Quanto maior, mais a linha merece ficar: situação lançada, depois informação de justificativa, depois a mais
     * recente (e, no empate, a de id maior).
     *
     * @return array{0: int, 1: int, 2: string, 3: int}
     */
    private function peso(object $linha): array
    {
        $comJustificativa = $linha->justificada || $linha->abonada || $linha->atestado_aluno_id !== null;

        return [
            $linha->situacao !== null ? 1 : 0,
            $comJustificativa ? 1 : 0,
            (string) ($linha->updated_at ?? ''),
            (int) $linha->id,
        ];
    }
};
