<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatos', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 255);
            $table->string('email', 255)->nullable();
            $table->string('telefone', 50)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->unsignedSmallInteger('idade')->nullable();
            $table->string('cargo_pretendido', 255); // Pretensão de vaga
            $table->string('escola_atual', 255)->nullable(); // Escola/empresa onde trabalha atualmente
            $table->string('local_moradia', 255)->nullable(); // Bairro, cidade ou endereço onde reside
            $table->decimal('pretensao_salarial', 12, 2)->nullable();
            $table->text('formacao_academica')->nullable();
            $table->text('experiencias_profissionais')->nullable();
            $table->text('habilidades')->nullable();
            $table->text('resumo_perfil')->nullable();

            // Arquivo do currículo para auditoria
            $table->string('curriculo_path', 500)->nullable();
            $table->string('curriculo_nome_original', 255)->nullable();

            // Foto da pessoa extraída pela IA ou enviada
            $table->string('foto_path', 500)->nullable();

            // Status no processo seletivo
            $table->string('status', 30)->default('novo');

            // Impressões do entrevistador de RH e dados do áudio de WhatsApp
            $table->text('impressoes_entrevistador')->nullable();
            $table->string('audio_entrevista_path', 500)->nullable();
            $table->text('audio_transcricao_resumo')->nullable();

            // Metadados brutos da IA para auditoria e histórico
            $table->json('dados_ia')->nullable();

            // Vínculos de usuários (auditoria)
            $table->foreignId('cadastrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entrevistador_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'cargo_pretendido'], 'idx_candidatos_status_cargo');
            $table->index('email', 'idx_candidatos_email');
            $table->index('telefone', 'idx_candidatos_telefone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatos');
    }
};
