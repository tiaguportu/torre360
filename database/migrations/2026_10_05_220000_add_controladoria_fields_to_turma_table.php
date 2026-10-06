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
        Schema::table('turma', function (Blueprint $table) {
            $table->decimal('mensalidade_base', 10, 2)->nullable()->after('vagas_maximas')
                ->comment('Valor padrão de tabela da mensalidade para os alunos desta turma');
            $table->decimal('custo_docente_mensal', 10, 2)->default(0)->after('mensalidade_base')
                ->comment('Custo mensal direto com professores e encargos alocados na turma');
            $table->decimal('custo_operacional_rateado', 10, 2)->default(0)->after('custo_docente_mensal')
                ->comment('Rateio mensal de custos indiretos de sala e operação da turma');
            $table->decimal('meta_margem_lucro', 5, 2)->default(20.00)->after('custo_operacional_rateado')
                ->comment('Meta percentual de margem de contribuição esperada para a turma');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turma', function (Blueprint $table) {
            $table->dropColumn([
                'mensalidade_base',
                'custo_docente_mensal',
                'custo_operacional_rateado',
                'meta_margem_lucro',
            ]);
        });
    }
};
