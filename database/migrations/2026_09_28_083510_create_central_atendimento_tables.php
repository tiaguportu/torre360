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
        Schema::create('atendimento_setores', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->string('email_notificacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('atendimento_chamados', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo')->unique();
            $table->foreignId('setor_id')->constrained('atendimento_setores')->restrictOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->nullOnDelete();
            $table->foreignId('solicitante_id')->constrained('pessoa')->restrictOnDelete();
            $table->foreignId('responsavel_atendimento_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assunto');
            $table->string('prioridade')->default('normal'); // 'baixa', 'normal', 'alta', 'urgente'
            $table->string('status')->default('aberto'); // 'aberto', 'em_andamento', 'aguardando_solicitante', 'resolvido', 'fechado'
            $table->tinyInteger('avaliacao_nota')->nullable();
            $table->text('avaliacao_comentario')->nullable();
            $table->dateTime('fechado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('atendimento_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamado_id')->constrained('atendimento_chamados')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->longText('mensagem');
            $table->string('anexo_path')->nullable();
            $table->dateTime('lida_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atendimento_mensagens');
        Schema::dropIfExists('atendimento_chamados');
        Schema::dropIfExists('atendimento_setores');
    }
};
