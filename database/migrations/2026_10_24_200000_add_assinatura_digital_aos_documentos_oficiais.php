<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Declarações e histórico escolar com assinatura digital (Onda 74). O documento oficial guarda a cópia assinada, quando e
 * com que nível; o documento de assinatura (Onda 69) guarda o nível detectado no PDF e o resultado da conferência técnica.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['solicitacao_documentos', 'historico_escolars'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('arquivo_assinado_path')->nullable();
                $table->timestamp('assinado_em')->nullable();
                $table->string('assinatura_nivel', 20)->nullable();
            });
        }

        Schema::table('documentos_assinatura', function (Blueprint $table) {
            $table->string('nivel_assinatura', 20)->nullable();
            $table->json('assinatura_detalhes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_assinatura', function (Blueprint $table) {
            $table->dropColumn(['nivel_assinatura', 'assinatura_detalhes']);
        });

        foreach (['solicitacao_documentos', 'historico_escolars'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['arquivo_assinado_path', 'assinado_em', 'assinatura_nivel']);
            });
        }
    }
};
