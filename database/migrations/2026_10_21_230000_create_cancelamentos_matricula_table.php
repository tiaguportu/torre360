<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelamento de matrícula e distrato (Onda 61). Um pedido por vez por matrícula (regra do serviço): a família pede
 * (ou a secretaria registra), a secretaria decide o destino de cada fatura, imprime o distrato, anexa a cópia assinada
 * e conclui, e o cancelamento só então mexe na matrícula e nas faturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelamentos_matricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matricula')->restrictOnDelete();
            $table->string('origem', 10)->default('familia');
            $table->string('motivo', 30);
            // Criptografado: pode trazer saúde, família ou situação financeira da família.
            $table->text('motivo_detalhe')->nullable();
            $table->date('data_efetiva');
            $table->string('status', 20)->default('solicitado');
            // fatura_id => manter | cancelar | devolver
            $table->json('decisoes')->nullable();
            $table->decimal('valor_devido', 10, 2)->default(0);
            $table->decimal('multa_rescisoria', 10, 2)->default(0);
            $table->decimal('valor_a_devolver', 10, 2)->default(0);
            $table->boolean('gerar_transferencia')->default(false);
            $table->string('escola_destino', 160)->nullable();
            $table->text('observacao')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->timestamp('distrato_gerado_em')->nullable();
            $table->string('distrato_assinado_path')->nullable();
            $table->date('devolucao_realizada_em')->nullable();
            $table->foreignId('transferencia_escolar_id')->nullable()->constrained('transferencias_escolares')->nullOnDelete();
            $table->foreignId('solicitado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decidido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->foreignId('concluido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->index(['matricula_id', 'status'], 'idx_cancelamento_matricula_status');
            $table->index('status', 'idx_cancelamento_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelamentos_matricula');
    }
};
