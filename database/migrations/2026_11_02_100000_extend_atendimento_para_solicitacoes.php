<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 (Ondas 83, 80 e 34): o chamado da Central de Atendimento ganha prazo, categoria, tipo de requerimento e resultado.
 *
 *  - Setor: prazos de primeira resposta e de resolução (horas úteis), regra de fechamento por inatividade e a marca de quem
 *    recebe requerimentos.
 *  - Chamado: prazos calculados na abertura, momento da primeira resposta, categoria, tipo de requerimento com os dados do
 *    formulário, marca de acesso restrito (pedido do titular da LGPD) e o resultado do atendimento.
 *  - Categorias de assunto e respostas prontas por setor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->constrained('atendimento_setores')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('atendimento_respostas_prontas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->nullable()->constrained('atendimento_setores')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('texto');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('atendimento_setores', function (Blueprint $table) {
            $table->unsignedSmallInteger('prazo_primeira_resposta_horas')->nullable()->default(24)->after('email_notificacao');
            $table->unsignedSmallInteger('prazo_resolucao_horas')->nullable()->default(72)->after('prazo_primeira_resposta_horas');
            $table->unsignedSmallInteger('fecha_aguardando_dias')->nullable()->default(7)->after('prazo_resolucao_horas');
            $table->boolean('recebe_requerimentos')->default(false)->after('fecha_aguardando_dias');
        });

        Schema::table('atendimento_chamados', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('setor_id')->constrained('atendimento_categorias')->nullOnDelete();
            $table->string('tipo_requerimento', 60)->nullable()->after('assunto');
            $table->json('dados')->nullable()->after('tipo_requerimento');
            $table->boolean('restrito')->default(false)->after('dados');
            $table->dateTime('prazo_primeira_resposta_em')->nullable()->after('restrito');
            $table->dateTime('prazo_resolucao_em')->nullable()->after('prazo_primeira_resposta_em');
            $table->dateTime('primeira_resposta_em')->nullable()->after('prazo_resolucao_em');
            $table->dateTime('aguardando_solicitante_desde')->nullable()->after('primeira_resposta_em');
            $table->string('resultado', 20)->nullable()->after('aguardando_solicitante_desde');
            $table->text('resultado_justificativa')->nullable()->after('resultado');
            $table->dateTime('aplicado_em')->nullable()->after('resultado_justificativa');
            $table->foreignId('aplicado_por_user_id')->nullable()->after('aplicado_em')->constrained('users')->nullOnDelete();

            $table->index('tipo_requerimento');
            $table->index('prazo_primeira_resposta_em');
        });
    }

    public function down(): void
    {
        Schema::table('atendimento_chamados', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropForeign(['aplicado_por_user_id']);
            $table->dropIndex(['tipo_requerimento']);
            $table->dropIndex(['prazo_primeira_resposta_em']);
            $table->dropColumn([
                'categoria_id', 'tipo_requerimento', 'dados', 'restrito', 'prazo_primeira_resposta_em', 'prazo_resolucao_em',
                'primeira_resposta_em', 'aguardando_solicitante_desde', 'resultado', 'resultado_justificativa', 'aplicado_em',
                'aplicado_por_user_id',
            ]);
        });

        Schema::table('atendimento_setores', function (Blueprint $table) {
            $table->dropColumn(['prazo_primeira_resposta_horas', 'prazo_resolucao_horas', 'fecha_aguardando_dias', 'recebe_requerimentos']);
        });

        Schema::dropIfExists('atendimento_respostas_prontas');
        Schema::dropIfExists('atendimento_categorias');
    }
};
