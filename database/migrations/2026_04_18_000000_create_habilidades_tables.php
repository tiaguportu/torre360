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
        // Numa instalação do zero, a migração de 2026-03-27 que padronizou os
        // nomes de tabela para singular já renomeou "habilidades" para
        // "habilidade" antes de chegarmos aqui — mas com o schema antigo
        // (serie_id/disciplina_id/agrupamento/descricao), sem as colunas BNCC
        // (`codigo`, `nome`, `tipo`) que este projeto usa hoje. Checar apenas
        // a existência da tabela (como antes) fazia esta migração pular a
        // criação e deixar uma tabela com o schema errado. Por isso o critério
        // aqui é a presença da coluna `codigo`, não só do nome da tabela — no
        // banco real (que já tem o schema novo), isso continua um no-op.
        $possuiSchemaNovo = (Schema::hasTable('habilidades') && Schema::hasColumn('habilidades', 'codigo'))
            || (Schema::hasTable('habilidade') && Schema::hasColumn('habilidade', 'codigo'));

        if (! $possuiSchemaNovo) {
            // Tabela legada (se existir) não tem dados reais de habilidade
            // BNCC nesse ponto — só existia com o schema antigo, pré-refatoração.
            Schema::dropIfExists('habilidades');
            Schema::dropIfExists('habilidade');

            Schema::create('habilidades', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable()->index(); // BNCC code
                $table->string('nome');
                $table->text('descricao')->nullable();
                $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->onDelete('cascade');
                $table->enum('tipo', ['BNCC', 'Institucional'])->default('BNCC');
                $table->timestamps();
            });
        }

        $tabelaHabilidade = Schema::hasTable('habilidade') ? 'habilidade' : 'habilidades';

        if (! Schema::hasTable('turma_habilidade')) {
            Schema::create('turma_habilidade', function (Blueprint $table) use ($tabelaHabilidade) {
                $table->id();
                $table->foreignId('turma_id')->constrained('turma')->onDelete('cascade');
                $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('avaliacao_habilidades')) {
            Schema::create('avaliacao_habilidades', function (Blueprint $table) use ($tabelaHabilidade) {
                $table->id();
                $table->foreignId('matricula_id')->constrained('matricula')->onDelete('cascade');
                $table->foreignId('habilidade_id')->constrained($tabelaHabilidade)->onDelete('cascade');
                $table->foreignId('etapa_avaliativa_id')->constrained('etapa_avaliativa')->onDelete('cascade');
                $table->string('conceito'); // Ex: Pleno, Suficiente, etc
                $table->text('observacao')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avaliacao_habilidades');
        Schema::dropIfExists('turma_habilidade');
        Schema::dropIfExists('habilidades');
    }
};
