<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Turma passa a ter ciclo de vida (`status`: planejada, ativa, concluida, cancelada) e o
     * período letivo deixa de ser opcional: o cadastro de Turma não pedia o período, então turmas
     * criadas pelo painel ficavam com `periodo_letivo_id` nulo e sumiam do wizard de matrícula,
     * do ensalamento e da rematrícula.
     *
     * A migration só altera a coluna do período depois de checar que não há turma sem período
     * (rodar `migrate` com linhas nulas derrubaria o deploy no meio da alteração).
     */
    public function up(): void
    {
        $semPeriodo = DB::table('turma')->whereNull('periodo_letivo_id')->count();

        if ($semPeriodo > 0) {
            throw new RuntimeException(
                "Existem {$semPeriodo} turma(s) sem período letivo. Vincule cada uma a um período (Acadêmico → Turmas) antes de rodar esta migration."
            );
        }

        if (! Schema::hasColumn('turma', 'status')) {
            Schema::table('turma', function (Blueprint $table) {
                $table->string('status')->default('ativa');
            });
        }

        $this->trocarForeignKeyDoPeriodo(restringir: true);
    }

    public function down(): void
    {
        $this->trocarForeignKeyDoPeriodo(restringir: false);

        if (Schema::hasColumn('turma', 'status')) {
            Schema::table('turma', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    /**
     * `restringir = true`: período obrigatório e FK `restrict` (não se apaga período com turmas).
     * `restringir = false`: volta ao estado anterior (nullable, FK `set null`).
     *
     * No SQLite dos testes a coluna foi criada sem FK (`ALTER TABLE ... ADD COLUMN`), então só a
     * nulidade muda ali.
     */
    private function trocarForeignKeyDoPeriodo(bool $restringir): void
    {
        $mysql = DB::getDriverName() !== 'sqlite';

        if ($mysql) {
            $existente = collect(Schema::getForeignKeys('turma'))
                ->first(fn (array $fk) => $fk['columns'] === ['periodo_letivo_id']);

            if ($existente) {
                Schema::table('turma', fn (Blueprint $table) => $table->dropForeign($existente['name']));
            }
        }

        Schema::table('turma', function (Blueprint $table) use ($restringir) {
            $coluna = $table->unsignedBigInteger('periodo_letivo_id');

            $restringir ? $coluna->nullable(false)->change() : $coluna->nullable()->change();
        });

        if ($mysql) {
            Schema::table('turma', function (Blueprint $table) use ($restringir) {
                $fk = $table->foreign('periodo_letivo_id')->references('id')->on('periodo_letivo');

                $restringir ? $fk->restrictOnDelete() : $fk->nullOnDelete();
            });
        }
    }
};
