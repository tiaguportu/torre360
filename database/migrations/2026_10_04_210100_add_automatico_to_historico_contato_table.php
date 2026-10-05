<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distingue registros gerados pelo sistema/IA (e-mails da régua, análises de IA) dos contatos
     * humanos. Só estes últimos contam como "interação" para estagnação, recência e Lead Score.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('historico_contato', 'automatico')) {
            Schema::table('historico_contato', function (Blueprint $table) {
                $table->boolean('automatico')->default(false)->after('resultado');
                $table->index(['interessado_id', 'automatico', 'data_contato'], 'historico_contato_interacoes_index');
            });
        }

        // Registros anteriores à coluna: identifica os automáticos pelo texto que o sistema grava.
        DB::table('historico_contato')
            ->where('automatico', false)
            ->where(function ($query) {
                $query->where('relato', 'like', 'E-mail automático enviado pela Régua de Follow-up%')
                    ->orWhere('relato', 'like', '✨ IA analisou%')
                    ->orWhere('relato', 'like', '✨ Dossiê Estratégico gerado com IA%');
            })
            ->update(['automatico' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('historico_contato', 'automatico')) {
            Schema::table('historico_contato', function (Blueprint $table) {
                $table->dropIndex('historico_contato_interacoes_index');
                $table->dropColumn('automatico');
            });
        }
    }
};
