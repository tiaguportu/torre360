<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acordo_inadimplencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ex: ACD-2026-00001
            $table->foreignId('contrato_id')->nullable()->constrained('contrato')->nullOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('responsavel_pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('criado_por_user_id')->constrained('users')->cascadeOnDelete();

            // Composição da Dívida Original
            $table->decimal('valor_original_total', 10, 2);
            $table->decimal('valor_multa_original', 10, 2)->default(0);
            $table->decimal('valor_juros_original', 10, 2)->default(0);
            $table->integer('quantidade_faturas_originais')->default(1);
            $table->json('faturas_originais_ids')->nullable();

            // Condições do Acordo / Negociação
            $table->decimal('percentual_desconto_concedido', 5, 2)->default(0);
            $table->decimal('valor_desconto', 10, 2)->default(0);
            $table->decimal('valor_total_acordo', 10, 2);
            $table->decimal('valor_entrada', 10, 2)->default(0);
            $table->date('data_vencimento_entrada')->nullable();
            $table->integer('quantidade_parcelas')->default(1);
            $table->decimal('valor_parcela', 10, 2)->default(0);
            $table->integer('dia_vencimento_parcelas')->default(10);
            $table->date('primeiro_vencimento');

            // Jurídico, Aceite e Status
            $table->string('token_publico', 64)->unique();
            $table->string('status')->default('simulado');
            $table->longText('termo_confissao_texto')->nullable();
            $table->dateTime('aceito_em')->nullable();
            $table->string('ip_aceite', 45)->nullable();
            $table->text('user_agent_aceite')->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();
        });

        Schema::create('acordo_parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acordo_inadimplencia_id')->constrained('acordo_inadimplencias')->cascadeOnDelete();
            $table->integer('numero_parcela'); // 0 = entrada, 1..N parcelas
            $table->decimal('valor', 10, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->decimal('valor_pago', 10, 2)->nullable();
            $table->string('status')->default('pendente'); // pendente, pago, atrasado, cancelado
            $table->string('forma_pagamento')->nullable(); // pix, boleto, cartao, dinheiro
            $table->foreignId('fatura_gerada_id')->nullable()->constrained('faturas')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acordo_parcelas');
        Schema::dropIfExists('acordo_inadimplencias');
    }
};
