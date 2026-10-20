<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrumentos_avaliacao_docente', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedTinyInteger('escala_maxima')->default(5);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('criterios_avaliacao_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrumento_id')->constrained('instrumentos_avaliacao_docente')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('peso', 5, 2)->default(1);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['instrumento_id', 'ordem'], 'idx_criterio_docente_instrumento_ordem');
        });

        Schema::create('ciclos_avaliacao_docente', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->foreignId('instrumento_id')->constrained('instrumentos_avaliacao_docente')->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('avaliacoes_docente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos_avaliacao_docente')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->foreignId('avaliador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->date('data_observacao');
            $table->decimal('pontuacao_percentual', 5, 2)->nullable();
            // Parecer qualitativo: dado sensível de funcionário, guardado cifrado.
            $table->longText('parecer')->nullable();
            $table->string('status')->default('rascunho');
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();

            $table->unique(['ciclo_id', 'funcionario_id', 'avaliador_user_id', 'data_observacao'], 'uq_avaliacao_docente_observacao');
            $table->index(['funcionario_id', 'status'], 'idx_avaliacao_docente_funcionario_status');
        });

        Schema::create('avaliacao_docente_criterios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avaliacao_docente_id')->constrained('avaliacoes_docente')->cascadeOnDelete();
            $table->foreignId('criterio_id')->constrained('criterios_avaliacao_docente')->restrictOnDelete();
            $table->unsignedTinyInteger('nota')->nullable();
            // Observação do avaliador sobre o critério: cifrada, como o parecer.
            $table->longText('observacao')->nullable();
            $table->timestamps();

            $table->unique(['avaliacao_docente_id', 'criterio_id'], 'uq_avaliacao_docente_criterio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_docente_criterios');
        Schema::dropIfExists('avaliacoes_docente');
        Schema::dropIfExists('ciclos_avaliacao_docente');
        Schema::dropIfExists('criterios_avaliacao_docente');
        Schema::dropIfExists('instrumentos_avaliacao_docente');
    }
};
