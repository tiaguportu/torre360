<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Toda matrícula passa a ter turma. Período letivo e série da matrícula deixam de ser colunas
     * próprias: vêm sempre da turma (`matricula.turma_id` → `turma.periodo_letivo_id` / `serie_id`),
     * então não há mais como divergirem.
     *
     * Guarda: se existir matrícula sem turma a migration aborta ANTES de alterar qualquer coisa
     * (vincule cada uma a uma turma e rode de novo). Como as migrations rodam no "Git Pull" do
     * painel, falhar cedo e sem efeitos colaterais é o comportamento seguro.
     */
    public function up(): void
    {
        $semTurma = DB::table('matricula')->whereNull('turma_id')->count();

        if ($semTurma > 0) {
            throw new RuntimeException(
                "Existem {$semTurma} matrícula(s) sem turma. Vincule cada uma a uma turma (Acadêmico → Matrículas) antes de rodar esta migration."
            );
        }

        $this->tornarTurmaObrigatoria();

        foreach (['periodo_letivo_id', 'serie_id'] as $coluna) {
            $this->removerColuna($coluna);
        }
    }

    /**
     * Recria as colunas (nullable, como eram) e as preenche a partir da turma, então um rollback
     * devolve o esquema e os valores antigos sem perda.
     */
    public function down(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            if (! Schema::hasColumn('matricula', 'periodo_letivo_id')) {
                $table->foreignId('periodo_letivo_id')->nullable()->constrained('periodo_letivo');
            }

            if (! Schema::hasColumn('matricula', 'serie_id')) {
                $table->foreignId('serie_id')->nullable()->constrained('serie')->nullOnDelete();
            }
        });

        DB::table('matricula')->update([
            'periodo_letivo_id' => DB::raw('(select turma.periodo_letivo_id from turma where turma.id = matricula.turma_id)'),
            'serie_id' => DB::raw('(select turma.serie_id from turma where turma.id = matricula.turma_id)'),
        ]);

        $this->alterarTurma(obrigatoria: false);
    }

    private function tornarTurmaObrigatoria(): void
    {
        $this->alterarTurma(obrigatoria: true);
    }

    /**
     * `obrigatoria = true`: NOT NULL e FK `restrict` (apagar uma turma com matrículas deixa de apagar
     * as matrículas em cascata). `false`: volta a nullable com `cascade`, como antes.
     *
     * No SQLite dos testes a coluna foi criada sem FK (`ALTER TABLE ... ADD COLUMN`), então ali só
     * a nulidade muda.
     */
    private function alterarTurma(bool $obrigatoria): void
    {
        $mysql = DB::getDriverName() !== 'sqlite';

        if ($mysql) {
            $this->removerForeignKey('turma_id');
        }

        Schema::table('matricula', function (Blueprint $table) use ($obrigatoria) {
            $coluna = $table->unsignedBigInteger('turma_id');

            $obrigatoria ? $coluna->nullable(false)->change() : $coluna->nullable()->change();
        });

        if ($mysql) {
            Schema::table('matricula', function (Blueprint $table) use ($obrigatoria) {
                $fk = $table->foreign('turma_id')->references('id')->on('turma');

                $obrigatoria ? $fk->restrictOnDelete() : $fk->cascadeOnDelete();
            });
        }
    }

    private function removerColuna(string $coluna): void
    {
        if (! Schema::hasColumn('matricula', $coluna)) {
            return;
        }

        $this->removerForeignKey($coluna);

        Schema::table('matricula', function (Blueprint $table) use ($coluna) {
            $table->dropColumn($coluna);
        });
    }

    private function removerForeignKey(string $coluna): void
    {
        $existente = collect(Schema::getForeignKeys('matricula'))
            ->first(fn (array $fk) => $fk['columns'] === [$coluna]);

        if (! $existente) {
            return;
        }

        // MySQL: pelo nome real da FK (nasceu na tabela antes do rename e não segue a convenção).
        // SQLite: as FKs não têm nome; o Laravel recria a tabela sem a FK a partir das colunas.
        $alvo = ($existente['name'] ?? null) ?: [$coluna];

        Schema::table('matricula', fn (Blueprint $table) => $table->dropForeign($alvo));
    }
};
