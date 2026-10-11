<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interessado') && ! Schema::hasColumn('interessado', 'sla_gestao_notificado_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->timestamp('sla_gestao_notificado_em')
                    ->nullable()
                    ->after('sla_estouro_notificado_em')
                    ->comment('Data e hora em que o alerta de SLA estourado para lead sem consultor foi disparado para a gestão');

                $table->index(['usuario_id', 'data_primeiro_contato', 'sla_gestao_notificado_em'], 'idx_interessado_sla_gestao');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('interessado') && Schema::hasColumn('interessado', 'sla_gestao_notificado_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->dropIndex('idx_interessado_sla_gestao');
                $table->dropColumn('sla_gestao_notificado_em');
            });
        }
    }
};
