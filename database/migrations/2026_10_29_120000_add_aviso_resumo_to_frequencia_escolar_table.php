<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O padrão `false` é proposital: as faltas que já existem nunca entram no primeiro resumo, então não há backfill.
     */
    public function up(): void
    {
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->boolean('aviso_resumo_pendente')->default(false)->index()->after('motivo_justificativa');
            $table->timestamp('aviso_resumo_enviado_em')->nullable()->after('aviso_resumo_pendente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('frequencia_escolar', function (Blueprint $table) {
            $table->dropIndex(['aviso_resumo_pendente']);
            $table->dropColumn(['aviso_resumo_pendente', 'aviso_resumo_enviado_em']);
        });
    }
};
