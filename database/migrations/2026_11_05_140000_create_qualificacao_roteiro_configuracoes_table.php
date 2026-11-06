<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personalização do roteiro de qualificação do lead (SDR, Lote 2): texto sugerido das perguntas, ordem, quais campos são
 * necessários para marcar a visita e quais estão ativos. Uma linha por salvamento; a mais recente vale. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qualificacao_roteiro_configuracoes')) {
            return;
        }

        Schema::create('qualificacao_roteiro_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->json('valores');
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualificacao_roteiro_configuracoes');
    }
};
