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
        Schema::create('retencao_tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->foreignId('aluno_pessoa_id')->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('rematricula_id')->nullable()->constrained('rematriculas')->nullOnDelete();
            $table->unsignedTinyInteger('score_risco');
            $table->string('status', 30)->default('pendente')->index();
            $table->string('prioridade', 20)->default('alta');
            $table->string('titulo');
            $table->json('fatores_resumo')->nullable();
            $table->text('descricao')->nullable();
            $table->foreignId('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('data_limite')->nullable();
            $table->dateTime('alertado_coordenacao_em')->nullable()->index();
            $table->dateTime('resolvido_em')->nullable();
            $table->text('observacoes_resolucao')->nullable();
            $table->timestamps();

            $table->index(['matricula_id', 'status']);
        });

        Schema::table('matricula', function (Blueprint $table) {
            $table->dateTime('risco_evasao_alerta_em')->nullable()->after('risco_evasao_atualizado_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matricula', function (Blueprint $table) {
            $table->dropColumn('risco_evasao_alerta_em');
        });

        Schema::dropIfExists('retencao_tarefas');
    }
};
