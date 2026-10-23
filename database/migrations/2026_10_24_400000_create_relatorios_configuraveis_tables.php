<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relatórios configuráveis e agendados (Onda 72). O relatório salvo guarda a fonte de dados (uma classe do catálogo, nunca SQL
 * livre), as colunas, os filtros, a ordem, o formato e o agendamento; cada execução fica registrada com o arquivo gerado, que
 * é apagado ao fim do prazo de retenção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorios_salvos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('fonte', 40);
            $table->json('colunas');
            $table->json('filtros')->nullable();
            $table->string('ordenar_por', 40)->nullable();
            $table->string('direcao', 4)->default('asc');
            $table->string('formato', 4)->default('csv');

            // Agendamento: frequência (diaria, semanal, mensal), dia (da semana ISO 1-7 ou do mês 1-28), hora e quem recebe o aviso.
            $table->boolean('agendado')->default(false);
            $table->string('frequencia', 10)->nullable();
            $table->unsignedTinyInteger('dia')->nullable();
            $table->string('hora', 5)->nullable();
            $table->json('destinatarios')->nullable();
            $table->timestamp('proxima_execucao_em')->nullable();
            $table->timestamp('ultima_execucao_em')->nullable();

            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agendado', 'proxima_execucao_em'], 'idx_relatorio_agendado');
        });

        Schema::create('execucoes_relatorio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatorio_salvo_id')->constrained('relatorios_salvos')->cascadeOnDelete();
            $table->string('origem', 10);
            $table->string('status', 10);
            $table->string('formato', 4);
            $table->unsignedInteger('linhas')->default(0);
            $table->boolean('truncado')->default(false);
            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_hash', 64)->nullable();
            $table->text('erro')->nullable();
            $table->foreignId('executado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expira_em')->nullable();
            $table->timestamps();

            $table->index('expira_em', 'idx_execucao_relatorio_expira');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execucoes_relatorio');
        Schema::dropIfExists('relatorios_salvos');
    }
};
