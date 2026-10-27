<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_recorrentes', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao', 160);
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedors')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->nullOnDelete();
            $table->string('periodicidade', 12)->default('mensal');
            $table->unsignedTinyInteger('dia_vencimento')->default(10);
            $table->decimal('valor', 12, 2);
            // Valor estimado (energia, água): a conta gerada espera a revisão de quem paga.
            $table->boolean('valor_estimado')->default(false);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            // Quantos dias antes do vencimento a conta passa a existir.
            $table->unsignedSmallInteger('antecedencia_dias')->default(10);
            // Data do reajuste do contrato (aluguel, serviços): a Mesa avisa quando se aproxima.
            $table->date('proximo_reajuste_em')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'vigencia_inicio']);
        });

        Schema::table('conta_pagars', function (Blueprint $table): void {
            $table->foreignId('conta_recorrente_id')->nullable()->after('recebimento_compra_id')->constrained('contas_recorrentes')->nullOnDelete();
            // Primeiro dia do mês do vencimento: com o modelo, impede a mesma conta duas vezes no mesmo mês.
            $table->date('competencia')->nullable()->after('conta_recorrente_id');
            // Conta gerada de valor estimado nasce sem revisão; quem paga confere e confirma.
            $table->boolean('valor_revisado')->default(true)->after('competencia');

            $table->unique(['conta_recorrente_id', 'competencia'], 'conta_pagars_recorrente_competencia_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conta_pagars', function (Blueprint $table): void {
            $table->dropUnique('conta_pagars_recorrente_competencia_unique');
            $table->dropConstrainedForeignId('conta_recorrente_id');
            $table->dropColumn(['competencia', 'valor_revisado']);
        });

        Schema::dropIfExists('contas_recorrentes');
    }
};
