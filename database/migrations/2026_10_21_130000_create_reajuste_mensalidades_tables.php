<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cenário de reajuste de uma campanha de rematrícula: percentual, arredondamento e um item por série.
        Schema::create('cenarios_reajuste', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('periodo_rematricula_id')->constrained('periodo_rematriculas')->restrictOnDelete();
            $table->foreignId('planilha_lei_id')->nullable()->constrained('planilha_lei_mensalidades')->nullOnDelete();
            $table->string('nome');
            $table->decimal('percentual_padrao', 6, 2)->default(0);
            $table->string('arredondamento', 12)->default('centavos');
            $table->string('status', 12)->default('simulacao');
            $table->text('observacao')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->timestamp('aplicado_em')->nullable();
            $table->timestamp('comunicado_em')->nullable();
            $table->timestamps();

            $table->index(['periodo_rematricula_id', 'status'], 'idx_cenario_periodo_status');
        });

        Schema::create('cenario_reajuste_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cenario_id')->constrained('cenarios_reajuste')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('serie')->restrictOnDelete();
            // Nulo = usa o percentual padrão do cenário.
            $table->decimal('percentual', 6, 2)->nullable();
            $table->decimal('valor_atual', 12, 2)->default(0);
            $table->decimal('valor_novo', 12, 2)->default(0);
            $table->unsignedInteger('alunos')->default(0);
            $table->decimal('bolsa_media', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['cenario_id', 'serie_id'], 'uq_cenario_item_serie');
        });

        // Preço do novo contrato por série numa campanha de rematrícula. Sem linha para a série, vale o `valor_taxa` da campanha.
        Schema::create('periodo_rematricula_precos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('periodo_rematricula_id')->constrained('periodo_rematriculas')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('serie')->restrictOnDelete();
            $table->decimal('valor_total', 12, 2);
            $table->foreignId('cenario_reajuste_item_id')->nullable()->constrained('cenario_reajuste_itens')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodo_rematricula_id', 'serie_id'], 'uq_preco_periodo_serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_rematricula_precos');
        Schema::dropIfExists('cenario_reajuste_itens');
        Schema::dropIfExists('cenarios_reajuste');
    }
};
