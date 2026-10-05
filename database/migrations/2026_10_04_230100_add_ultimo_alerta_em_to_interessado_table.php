<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quando o consultor foi avisado pela última vez de que o lead está atrasado ou estagnado.
     * Sem isso, `crm:notificar-pendentes` repetia o mesmo alerta (e-mail + sino + activity log) todo dia.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('interessado', 'ultimo_alerta_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->timestamp('ultimo_alerta_em')->nullable()->after('data_proximo_contato');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('interessado', 'ultimo_alerta_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->dropColumn('ultimo_alerta_em');
            });
        }
    }
};
