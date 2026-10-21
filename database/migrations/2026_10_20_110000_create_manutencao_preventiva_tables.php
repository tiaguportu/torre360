<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_manutencao', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            // O alvo é um bem do patrimônio, um espaço (sala) ou os dois; a regra "ao menos um" fica no serviço/formulário.
            $table->foreignId('bem_patrimonial_id')->nullable()->constrained('bens_patrimoniais')->nullOnDelete();
            $table->foreignId('sala_id')->nullable()->constrained('sala')->nullOnDelete();
            $table->unsignedSmallInteger('periodicidade_dias');
            $table->date('proxima_execucao');
            $table->date('ultima_execucao')->nullable();
            // Quantos dias antes do vencimento a ordem de serviço é aberta.
            $table->unsignedSmallInteger('antecedencia_dias')->default(7);
            $table->string('prioridade')->default('Média');
            $table->foreignId('categoria_os_id')->nullable()->constrained('categoria_os')->nullOnDelete();
            $table->json('checklist')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ativo', 'proxima_execucao'], 'idx_plano_manutencao_ativo_proxima');
        });

        Schema::create('execucoes_manutencao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plano_manutencao_id')->constrained('planos_manutencao')->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordem_servicos')->nullOnDelete();
            $table->date('data_prevista');
            $table->string('status')->default('prevista');
            $table->date('concluida_em')->nullable();
            $table->json('checklist_resultado')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Uma execução por vencimento: impede reabrir uma ordem para o mesmo vencimento (por exemplo, depois de
            // cancelada) e dá idempotência ao agendador.
            $table->unique(['plano_manutencao_id', 'data_prevista'], 'uq_execucao_manutencao_plano_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execucoes_manutencao');
        Schema::dropIfExists('planos_manutencao');
    }
};
