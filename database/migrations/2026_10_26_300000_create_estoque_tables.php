<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque de consumíveis (Onda 16, fase 2): material de limpeza, papelaria, descartáveis. O saldo do item fica na própria linha e
 * cada entrada, saída ou ajuste deixa uma movimentação com o saldo depois dela, que é a trilha para conferir o inventário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_estoque', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->nullable()->unique();
            $table->string('nome', 150);
            $table->string('categoria', 60)->nullable();
            $table->string('unidade_medida', 10)->default('un');
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->decimal('quantidade_atual', 12, 3)->default(0);
            $table->decimal('custo_medio', 12, 4)->default(0);
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('nome', 'idx_item_estoque_nome');
        });

        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_estoque_id')->constrained('itens_estoque')->cascadeOnDelete();
            $table->string('tipo', 10);
            // Com sinal: entrada positiva, saída negativa, ajuste conforme a diferença contada.
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_apos', 12, 3);
            $table->decimal('custo_unitario', 12, 4)->nullable();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->nullOnDelete();
            $table->string('motivo', 160)->nullable();
            $table->nullableMorphs('origem');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_estoque_id', 'created_at'], 'idx_mov_estoque_item_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('itens_estoque');
    }
};
