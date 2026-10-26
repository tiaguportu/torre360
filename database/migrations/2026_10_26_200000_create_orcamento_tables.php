<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orçamento anual (Onda 43): o previsto por conta do plano, centro de custo e mês. O orçamento aprovado não muda: a
 * revisão cria uma nova versão que aponta para a anterior, e a anterior fica como "substituída" (o histórico permanece).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->string('nome', 120);
            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('status', 12)->default('rascunho');
            $table->foreignId('origem_orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->foreignId('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['ano', 'versao'], 'uq_orcamento_ano_versao');
        });

        Schema::create('orcamento_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->foreignId('plano_conta_id')->constrained('plano_contas')->cascadeOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centro_custos')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->decimal('valor_previsto', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['orcamento_id', 'plano_conta_id'], 'idx_orcamento_linha_plano');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_linhas');
        Schema::dropIfExists('orcamentos');
    }
};
