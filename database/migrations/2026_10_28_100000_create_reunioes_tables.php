<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reunião interna da escola (Ondas 27 e 32): conselho de classe, reunião pedagógica, devolutiva individual e formação.
        Schema::create('reunioes', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 20);
            $table->string('titulo', 160);
            $table->string('status', 16)->default('agendada');
            // Reunião sigilosa (a devolutiva individual é sempre): só quem organiza e quem participa a vê.
            $table->boolean('confidencial')->default(false);
            $table->dateTime('inicio_em');
            $table->dateTime('fim_em')->nullable();
            $table->string('local', 120)->nullable();
            $table->foreignId('sala_id')->nullable()->constrained('sala')->nullOnDelete();
            $table->foreignId('reserva_espaco_id')->nullable()->constrained('reservas_espaco')->nullOnDelete();
            // Só o conselho de classe tem turma e etapa avaliativa.
            $table->foreignId('turma_id')->nullable()->constrained('turma')->nullOnDelete();
            $table->foreignId('etapa_avaliativa_id')->nullable()->constrained('etapa_avaliativa')->nullOnDelete();
            $table->foreignId('organizador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('pauta')->nullable();
            $table->longText('ata')->nullable();
            $table->timestamp('realizada_em')->nullable();
            $table->timestamp('ata_fechada_em')->nullable();
            $table->foreignId('ata_fechada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelamento')->nullable();
            // Cópia assinada da ata (trait TemCopiaAssinada, a mesma da declaração e do histórico escolar).
            $table->string('arquivo_assinado_path')->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->string('assinatura_nivel', 20)->nullable();
            $table->timestamps();

            $table->index(['status', 'inicio_em'], 'idx_reuniao_status_inicio');
            $table->index(['tipo', 'turma_id'], 'idx_reuniao_tipo_turma');
        });

        Schema::create('reuniao_participantes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reuniao_id')->constrained('reunioes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // organizador, presidente (conselho) ou participante.
            $table->string('papel', 16)->default('participante');
            $table->string('convite', 10)->default('pendente');
            $table->timestamp('respondido_em')->nullable();
            // Nulo até a presença ser registrada.
            $table->boolean('presente')->nullable();
            $table->timestamps();

            $table->unique(['reuniao_id', 'user_id'], 'uq_reuniao_participante');
        });

        // Conselho de classe: cada aluno da turma com a fotografia dos indicadores e a deliberação.
        Schema::create('reuniao_alunos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reuniao_id')->constrained('reunioes')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->json('indicadores')->nullable();
            $table->string('deliberacao', 24)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('deliberado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deliberado_em')->nullable();
            $table->timestamps();

            $table->unique(['reuniao_id', 'matricula_id'], 'uq_reuniao_aluno');
        });

        // O que ficou combinado, com responsável e prazo. Em reunião de conselho, nasce da deliberação sobre o aluno.
        Schema::create('reuniao_encaminhamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reuniao_id')->constrained('reunioes')->cascadeOnDelete();
            $table->foreignId('reuniao_aluno_id')->nullable()->constrained('reuniao_alunos')->nullOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->nullOnDelete();
            $table->string('descricao', 300);
            $table->foreignId('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('prazo')->nullable();
            $table->string('status', 10)->default('aberto');
            $table->timestamp('concluido_em')->nullable();
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao_conclusao')->nullable();
            // A rotina diária avisa o vencimento uma vez só.
            $table->timestamp('vencimento_avisado_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'prazo'], 'idx_encaminhamento_status_prazo');
            $table->index(['responsavel_user_id', 'status'], 'idx_encaminhamento_responsavel');
        });

        // A ata fechada não muda: a correção entra como adendo.
        Schema::create('reuniao_adendos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reuniao_id')->constrained('reunioes')->cascadeOnDelete();
            $table->text('texto');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reuniao_adendos');
        Schema::dropIfExists('reuniao_encaminhamentos');
        Schema::dropIfExists('reuniao_alunos');
        Schema::dropIfExists('reuniao_participantes');
        Schema::dropIfExists('reunioes');
    }
};
