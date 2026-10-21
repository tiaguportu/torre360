<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABELA = 'consentimento_matriculas';

    private const COLUNAS = ['matricula_id', 'tipo_consentimento_id'];

    private const INDICE = 'uq_consentimento_matricula_tipo';

    /**
     * A migration de criação da tabela gerava um índice único com nome de 66 caracteres, acima do limite de 64 do
     * MySQL/MariaDB. Como o MySQL não desfaz DDL, a falha deixava a tabela criada SEM o índice, e a migration (que só
     * cria a tabela se ela não existe) passava a ignorá-la nas execuções seguintes. Aqui o índice é garantido em
     * qualquer ambiente, com nome curto. Idempotente: se já existe um índice único nessas colunas, não faz nada.
     *
     * Se a tabela já tem duas linhas para o mesmo aluno e tipo de consentimento (possível enquanto o índice não
     * existia), a migration para com a lista do que precisa ser resolvido: apagar consentimento é decisão de
     * negócio, não de migration.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABELA) || Schema::hasIndex(self::TABELA, self::COLUNAS, 'unique')) {
            return;
        }

        $duplicados = DB::table(self::TABELA)
            ->select(self::COLUNAS)
            ->selectRaw('count(*) as total')
            ->groupBy(self::COLUNAS)
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicados->isNotEmpty()) {
            $exemplos = $duplicados->take(5)->map(fn ($d): string => "matrícula {$d->matricula_id} / tipo {$d->tipo_consentimento_id} ({$d->total} linhas)")->implode('; ');

            throw new RuntimeException(
                "Existem {$duplicados->count()} par(es) de matrícula e tipo de consentimento repetidos em ".self::TABELA
                ." ({$exemplos}). Mantenha uma linha por par (a mais recente resposta) e rode a migration de novo.",
            );
        }

        Schema::table(self::TABELA, function (Blueprint $table): void {
            $table->unique(self::COLUNAS, self::INDICE);
        });
    }

    public function down(): void
    {
        // Não remove o índice: ele faz parte do esquema original da tabela, que esta migration apenas completa.
    }
};
