<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_lei_mensalidades', function (Blueprint $table) {
            $table->id();
            $table->integer('ano_base'); // Ex: 2026
            $table->integer('ano_letivo_destino'); // Ex: 2027
            $table->string('titulo');
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('curso')->nullOnDelete();

            // Ano Base (Histórico Consolidado)
            $table->integer('alunos_base')->default(0);
            $table->decimal('mensalidade_media_base', 10, 2)->default(0);
            $table->decimal('receita_anual_base', 12, 2)->default(0);
            $table->decimal('custo_pessoal_base', 12, 2)->default(0);
            $table->decimal('custo_custeio_base', 12, 2)->default(0);
            $table->decimal('custo_investimento_base', 12, 2)->default(0);
            $table->decimal('custo_total_base', 12, 2)->default(0);

            // Projeções e Variações da Lei 9.870/99
            $table->decimal('percentual_dissidio_pessoal', 5, 2)->default(0);
            $table->decimal('variacao_pessoal_valor', 12, 2)->default(0);
            $table->decimal('custo_pessoal_projetado', 12, 2)->default(0);

            $table->decimal('percentual_inflacao_custeio', 5, 2)->default(0);
            $table->decimal('variacao_custeio_valor', 12, 2)->default(0);
            $table->decimal('custo_custeio_projetado', 12, 2)->default(0);

            $table->decimal('valor_novos_investimentos', 12, 2)->default(0);
            $table->decimal('custo_investimento_projetado', 12, 2)->default(0);

            $table->decimal('custo_total_projetado', 12, 2)->default(0);
            $table->integer('meta_alunos_projetada')->default(0);

            // Índices de Reajuste
            $table->decimal('variacao_custo_total_percentual', 5, 2)->default(0);
            $table->decimal('percentual_reajuste_sugerido', 5, 2)->default(0);
            $table->decimal('percentual_reajuste_adotado', 5, 2)->default(0);
            $table->decimal('mensalidade_projetada', 10, 2)->default(0);
            $table->decimal('anuidade_projetada', 12, 2)->default(0);

            // Governança, Status e Justificativa Jurídico-Pedagógica
            $table->string('status')->default('rascunho');
            $table->text('justificativa_pedagogica')->nullable();
            $table->date('data_afixacao')->nullable();
            $table->foreignId('responsavel_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('homologado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('homologado_em')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_lei_mensalidades');
    }
};
