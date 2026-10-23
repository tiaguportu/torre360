<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exportação para a contabilidade (Onda 73). A escola e o escritório de contabilidade têm planos de contas diferentes: a conta
 * do plano da escola ganha o código que o escritório usa, a conta bancária ganha a conta contábil do banco, e os parâmetros
 * guardam as contas padrão (clientes, fornecedores e as contas de receita e despesa para o que ainda não foi classificado).
 * Cada exportação gerada fica registrada com o arquivo, o período e os totais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plano_contas', function (Blueprint $table) {
            $table->string('codigo_contabil', 30)->nullable()->after('codigo');
        });

        Schema::table('bancos', function (Blueprint $table) {
            $table->string('conta_contabil', 30)->nullable();
        });

        Schema::create('parametros_contabeis', function (Blueprint $table) {
            $table->id();
            $table->string('conta_clientes', 30)->nullable();
            $table->string('conta_fornecedores', 30)->nullable();
            $table->foreignId('plano_conta_receita_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('plano_conta_despesa_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('atualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lotes_contabeis', function (Blueprint $table) {
            $table->id();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->string('regime', 15);
            $table->string('formato', 20);
            $table->unsignedInteger('lancamentos');
            $table->decimal('total', 14, 2);
            $table->string('arquivo_path');
            $table->string('arquivo_hash', 64);
            $table->foreignId('gerado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['periodo_inicio', 'periodo_fim'], 'idx_lote_contabil_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_contabeis');
        Schema::dropIfExists('parametros_contabeis');

        Schema::table('bancos', function (Blueprint $table) {
            $table->dropColumn('conta_contabil');
        });

        Schema::table('plano_contas', function (Blueprint $table) {
            $table->dropColumn('codigo_contabil');
        });
    }
};
