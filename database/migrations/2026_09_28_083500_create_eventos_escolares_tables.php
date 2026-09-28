<?php

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
        Schema::create('eventos_escolares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('unidade_id')->nullable()->constrained('unidade')->nullOnDelete();
            $table->string('titulo');
            $table->string('tipo')->default('reuniao_pais');
            $table->longText('descricao')->nullable();
            $table->string('local')->nullable();
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim')->nullable();
            $table->integer('limite_vagas')->nullable();
            $table->dateTime('prazo_confirmacao')->nullable();
            $table->boolean('exige_autorizacao')->default(false);
            $table->longText('termo_autorizacao')->nullable();
            $table->decimal('valor_por_pessoa', 10, 2)->default(0.00);
            $table->string('publico_alvo')->default('todos'); // 'todos', 'turmas_especificas'
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('evento_escolar_turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_escolar_id')->constrained('eventos_escolares')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['evento_escolar_id', 'turma_id']);
        });

        Schema::create('evento_confirmacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_escolar_id')->constrained('eventos_escolares')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('responsavel_id')->constrained('pessoa')->restrictOnDelete();
            $table->string('status')->default('pendente'); // 'pendente', 'confirmado', 'recusado'
            $table->integer('quantidade_acompanhantes')->default(0);
            $table->boolean('autorizado')->nullable();
            $table->dateTime('data_resposta')->nullable();
            $table->string('ip_resposta')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['evento_escolar_id', 'matricula_id'], 'evento_matricula_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento_confirmacoes');
        Schema::dropIfExists('evento_escolar_turmas');
        Schema::dropIfExists('eventos_escolares');
    }
};
