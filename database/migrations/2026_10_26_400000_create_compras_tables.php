<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compras e cotações (Onda 38): requisição do setor, cotações dos fornecedores, pedido aprovado por alçada, recebimento com a nota e
 * a conta a pagar que nasce dele. O status do processo fica na requisição; o pedido guarda os valores e as aprovações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisicoes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->string('titulo', 160);
            $table->text('justificativa');
            $table->string('urgencia', 10)->default('normal');
            $table->string('status', 24)->default('rascunho');
            $table->text('cotacao_dispensada_justificativa')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_requisicao_compra_status');
        });

        Schema::create('itens_requisicao_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_compra_id')->constrained('requisicoes_compra')->cascadeOnDelete();
            $table->string('descricao', 200);
            $table->decimal('quantidade', 12, 3);
            $table->string('unidade_medida', 10)->default('un');
            $table->decimal('estimativa_unitaria', 12, 2)->nullable();
            $table->string('destino', 12)->default('consumo');
            $table->foreignId('item_estoque_id')->nullable()->constrained('itens_estoque')->nullOnDelete();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('cotacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_compra_id')->constrained('requisicoes_compra')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedors')->cascadeOnDelete();
            $table->unsignedSmallInteger('prazo_entrega_dias')->nullable();
            $table->unsignedSmallInteger('prazo_pagamento_dias')->default(30);
            $table->text('condicoes')->nullable();
            $table->date('validade_ate')->nullable();
            $table->decimal('frete', 12, 2)->default(0);
            $table->boolean('escolhida')->default(false);
            $table->text('motivo_escolha')->nullable();
            $table->foreignId('registrada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['requisicao_compra_id', 'fornecedor_id'], 'uq_cotacao_requisicao_fornecedor');
        });

        Schema::create('itens_cotacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotacao_id')->constrained('cotacoes')->cascadeOnDelete();
            $table->foreignId('item_requisicao_compra_id')->constrained('itens_requisicao_compra')->cascadeOnDelete();
            $table->decimal('preco_unitario', 12, 4);
            $table->timestamps();

            $table->unique(['cotacao_id', 'item_requisicao_compra_id'], 'uq_item_cotacao');
        });

        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('requisicao_compra_id')->unique()->constrained('requisicoes_compra')->cascadeOnDelete();
            $table->foreignId('cotacao_id')->constrained('cotacoes')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedors')->cascadeOnDelete();
            $table->decimal('valor_total', 14, 2);
            $table->unsignedTinyInteger('nivel_alcada')->default(1);
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->text('justificativa_acima_do_orcamento')->nullable();
            $table->json('saldo_orcamentario')->nullable();
            $table->timestamps();
        });

        Schema::create('recebimentos_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->cascadeOnDelete();
            $table->foreignId('recebido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('data_recebimento');
            $table->string('nota_fiscal_numero', 40)->nullable();
            $table->string('nota_fiscal_path')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('divergencia')->default(false);
            $table->decimal('valor_recebido', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['pedido_compra_id', 'divergencia'], 'idx_recebimento_divergencia');
        });

        Schema::create('itens_recebimento_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recebimento_compra_id')->constrained('recebimentos_compra')->cascadeOnDelete();
            $table->foreignId('item_requisicao_compra_id')->constrained('itens_requisicao_compra')->cascadeOnDelete();
            $table->decimal('quantidade_recebida', 12, 3);
            $table->decimal('preco_unitario', 12, 4);
            $table->timestamps();
        });

        Schema::table('conta_pagars', function (Blueprint $table) {
            $table->foreignId('pedido_compra_id')->nullable()->constrained('pedidos_compra')->nullOnDelete();
            $table->foreignId('recebimento_compra_id')->nullable()->constrained('recebimentos_compra')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conta_pagars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recebimento_compra_id');
            $table->dropConstrainedForeignId('pedido_compra_id');
        });

        Schema::dropIfExists('itens_recebimento_compra');
        Schema::dropIfExists('recebimentos_compra');
        Schema::dropIfExists('pedidos_compra');
        Schema::dropIfExists('itens_cotacao');
        Schema::dropIfExists('cotacoes');
        Schema::dropIfExists('itens_requisicao_compra');
        Schema::dropIfExists('requisicoes_compra');
    }
};
