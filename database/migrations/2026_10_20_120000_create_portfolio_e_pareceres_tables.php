<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->date('data_registro');
            // Caminhos no disco privado (fotos, PDFs, áudios e vídeos curtos do aluno). Servidos só por rota autorizada.
            $table->json('anexos')->nullable();
            // Foto ou vídeo em que aparecem outros alunos: nunca é compartilhado com a família de um aluno só.
            $table->boolean('mostra_outros_alunos')->default(false);
            $table->boolean('visivel_familia')->default(false);
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['matricula_id', 'data_registro'], 'idx_portfolio_matricula_data');
        });

        Schema::create('portfolio_item_habilidade', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_item_id')->constrained('portfolio_itens')->cascadeOnDelete();
            $table->foreignId('habilidade_id')->constrained('habilidades')->cascadeOnDelete();

            $table->unique(['portfolio_item_id', 'habilidade_id'], 'uq_portfolio_item_habilidade');
        });

        Schema::create('pareceres_descritivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('etapa_avaliativa_id')->constrained('etapa_avaliativa')->cascadeOnDelete();
            $table->longText('texto')->nullable();
            $table->string('status')->default('rascunho');
            $table->boolean('gerado_com_ia')->default(false);
            $table->foreignId('autor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_em')->nullable();
            $table->timestamps();

            $table->unique(['matricula_id', 'etapa_avaliativa_id'], 'uq_parecer_matricula_etapa');
            $table->index(['status', 'etapa_avaliativa_id'], 'idx_parecer_status_etapa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pareceres_descritivos');
        Schema::dropIfExists('portfolio_item_habilidade');
        Schema::dropIfExists('portfolio_itens');
    }
};
