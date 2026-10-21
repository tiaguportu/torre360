<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Autoavaliação: o próprio professor dá nota aos mesmos critérios do instrumento do ciclo. Dado sensível: texto cifrado.
        Schema::create('autoavaliacoes_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos_avaliacao_docente')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->string('status', 15)->default('rascunho');
            $table->longText('comentario')->nullable();
            $table->decimal('pontuacao_percentual', 5, 2)->nullable();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();

            $table->unique(['ciclo_id', 'funcionario_id'], 'uq_autoavaliacao_ciclo_funcionario');
        });

        Schema::create('autoavaliacao_notas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('autoavaliacao_id')->constrained('autoavaliacoes_docente')->cascadeOnDelete();
            $table->foreignId('criterio_id')->constrained('criterios_avaliacao_docente')->restrictOnDelete();
            $table->unsignedTinyInteger('nota')->nullable();
            $table->longText('observacao')->nullable();
            $table->timestamps();

            $table->unique(['autoavaliacao_id', 'criterio_id'], 'uq_autoavaliacao_nota_criterio');
        });

        // Plano de desenvolvimento individual (PDI) que nasce de uma avaliação finalizada.
        Schema::create('planos_desenvolvimento_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_docente_id')->constrained('avaliacoes_docente')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->string('status', 15)->default('rascunho');
            $table->longText('objetivo')->nullable();
            $table->date('data_revisao')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ativado_em')->nullable();
            $table->timestamp('ciencia_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->unique('avaliacao_docente_id', 'uq_pdi_avaliacao');
            $table->index(['funcionario_id', 'status'], 'idx_pdi_funcionario_status');
        });

        Schema::create('acoes_desenvolvimento_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos_desenvolvimento_docente')->cascadeOnDelete();
            $table->foreignId('criterio_id')->nullable()->constrained('criterios_avaliacao_docente')->nullOnDelete();
            $table->longText('descricao');
            $table->date('prazo');
            $table->string('status', 15)->default('pendente');
            $table->date('concluida_em')->nullable();
            $table->longText('resultado')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['plano_id', 'ordem'], 'idx_acao_pdi_ordem');
            $table->index(['status', 'prazo'], 'idx_acao_pdi_status_prazo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acoes_desenvolvimento_docente');
        Schema::dropIfExists('planos_desenvolvimento_docente');
        Schema::dropIfExists('autoavaliacao_notas');
        Schema::dropIfExists('autoavaliacoes_docente');
    }
};
