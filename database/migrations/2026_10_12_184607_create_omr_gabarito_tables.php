<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avaliacao_gabaritos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacao')->cascadeOnDelete()->unique();
            $table->unsignedSmallInteger('total_questoes')->default(10);
            $table->unsignedTinyInteger('alternativas')->default(5);
            $table->unsignedSmallInteger('layout_versao')->default(1);
            $table->json('layout')->nullable();
            $table->string('anulacao_modo', 30)->default('pontua_todos');
            $table->timestamp('travado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('avaliacao_gabarito_questoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_gabarito_id')->constrained('avaliacao_gabaritos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->char('alternativa_correta', 1)->nullable();
            $table->decimal('peso', 5, 2)->default(1.00);
            $table->foreignId('habilidade_id')->nullable()->constrained('habilidades')->nullOnDelete();
            $table->boolean('anulada')->default(false);
            $table->timestamps();

            $table->unique(['avaliacao_gabarito_id', 'numero'], 'uq_gabarito_questao_numero');
        });

        Schema::create('gabarito_folhas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('avaliacao_id')->constrained('avaliacao')->cascadeOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->cascadeOnDelete();
            $table->unsignedSmallInteger('layout_versao')->default(1);
            $table->foreignId('gerada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrigida_em')->nullable();
            $table->timestamps();

            $table->index(['avaliacao_id', 'matricula_id'], 'idx_folha_avaliacao_matricula');
        });

        Schema::create('omr_correcoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacao')->cascadeOnDelete();
            $table->foreignId('gabarito_folha_id')->nullable()->constrained('gabarito_folhas')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('arquivo_path');
            $table->char('arquivo_sha256', 64)->nullable();
            $table->string('status', 30)->default('pendente');
            $table->json('respostas')->nullable();
            $table->json('respostas_finais')->nullable();
            $table->json('alertas')->nullable();
            $table->unsignedSmallInteger('acertos')->default(0);
            $table->unsignedSmallInteger('total_validas')->default(0);
            $table->decimal('nota_calculada', 5, 2)->nullable();
            $table->string('motor', 30)->default('gd');
            $table->unsignedSmallInteger('layout_versao')->default(1);
            $table->unsignedInteger('tempo_ms')->nullable();
            $table->foreignId('confirmada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmada_em')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->timestamps();

            $table->index(['avaliacao_id', 'matricula_id'], 'idx_omr_avaliacao_matricula');
            $table->index('status', 'idx_omr_status');
        });

        Schema::create('omr_correcao_habilidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('omr_correcao_id')->constrained('omr_correcoes')->cascadeOnDelete();
            $table->foreignId('habilidade_id')->constrained('habilidades')->cascadeOnDelete();
            $table->unsignedSmallInteger('acertos')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            $table->decimal('percentual', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['omr_correcao_id', 'habilidade_id'], 'uq_omr_habilidade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('omr_correcao_habilidades');
        Schema::dropIfExists('omr_correcoes');
        Schema::dropIfExists('gabarito_folhas');
        Schema::dropIfExists('avaliacao_gabarito_questoes');
        Schema::dropIfExists('avaliacao_gabaritos');
    }
};
