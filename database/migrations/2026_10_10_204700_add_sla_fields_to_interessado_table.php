<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->unsignedInteger('sla_primeira_resposta_minutos')
                ->nullable()
                ->after('data_primeiro_contato')
                ->comment('Prazo de SLA de 1ª resposta em minutos comerciais para este lead (null usa o padrão do sistema)');

            $table->timestamp('sla_estouro_notificado_em')
                ->nullable()
                ->after('sla_primeira_resposta_minutos')
                ->comment('Data e hora em que a notificação de estouro do SLA foi disparada ao consultor');

            $table->index(['data_primeiro_contato', 'sla_estouro_notificado_em'], 'idx_interessado_sla_pendente');
        });
    }

    public function down(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            $table->dropIndex('idx_interessado_sla_pendente');
            $table->dropColumn(['sla_primeira_resposta_minutos', 'sla_estouro_notificado_em']);
        });
    }
};
