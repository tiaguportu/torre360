<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validade do link público do Portal de Pré-Admissão (documentos de menores e responsáveis):
     * antes o token nunca expirava. Links já emitidos ganham 90 dias a partir de agora.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('interessado', 'token_documentos_expira_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->timestamp('token_documentos_expira_em')->nullable()->after('token_documentos');
            });
        }

        DB::table('interessado')
            ->whereNotNull('token_documentos')
            ->whereNull('token_documentos_expira_em')
            ->update(['token_documentos_expira_em' => now()->addDays(90)]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('interessado', 'token_documentos_expira_em')) {
            Schema::table('interessado', function (Blueprint $table) {
                $table->dropColumn('token_documentos_expira_em');
            });
        }
    }
};
